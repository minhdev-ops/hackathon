<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Đăng nhập/đăng ký cho production (mở rộng FR-AUTH ngoài TideCloak):
 *  - đăng ký tạo owner + vault_kdf_salt, không cho tự chọn vai trò;
 *  - mật khẩu mạnh, email trùng bị chặn;
 *  - throttle chống brute-force cho /login và /register.
 */
class RegisterLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:127.0.0.1');
        RateLimiter::clear('register:127.0.0.1');
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Đăng ký tài khoản');
    }

    public function test_register_creates_owner_with_kdf_salt_and_logs_in(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Nguyễn Văn Test',
            'email' => 'NewUser@Example.com',
            'password' => 'matkhau9x',
            'password_confirmation' => 'matkhau9x',
        ]);

        // Sau đăng ký: về trang xác nhận email, tài khoản chưa dùng được.
        $response->assertRedirect('/email/verify');

        $user = User::where('email', 'newuser@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('owner', $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->vault_kdf_salt);
        $this->assertSame(16, strlen(base64_decode($user->vault_kdf_salt, true) ?: ''));
        $this->assertTrue(Hash::check('matkhau9x', $user->password));

        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_register_ignores_attempted_privilege_escalation(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'matkhau9x',
            'password_confirmation' => 'matkhau9x',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'attacker@example.com',
            'role' => 'owner',
        ]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'lan@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Giả Mạo',
            'email' => 'lan@example.com',
            'password' => 'matkhau9x',
            'password_confirmation' => 'matkhau9x',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Yếu Đuối',
            'email' => 'weak@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_register_rejects_mismatched_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Nhập Lầm',
            'email' => 'mismatch@example.com',
            'password' => 'matkhau9x',
            'password_confirmation' => 'matkhau9y',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_login_still_works_for_seeded_user(): void
    {
        $user = User::factory()->create(['email' => 'lan@example.com']);

        $response = $this->post('/login', [
            'email' => 'lan@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'lan@example.com']);

        // 10 lần sai liên tiếp = ngưỡng throttle 10/phút được cho phép.
        foreach (range(1, 10) as $i) {
            $this->post('/login', [
                'email' => 'lan@example.com',
                'password' => 'sai-mat-khau-'.$i,
            ])->assertSessionHasErrors('email');
        }

        // Lần thứ 11 phải bị 429 Too Many Requests.
        $this->post('/login', [
            'email' => 'lan@example.com',
            'password' => 'password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_guest_area_requires_login(): void
    {
        $this->get('/vault')->assertRedirect('/login');
        $this->get('/timeline')->assertRedirect('/login');
    }

    /*
    |--------------------------------------------------------------------------
    | Xác thực email (signed link 60 phút)
    |--------------------------------------------------------------------------
    */

    public function test_unverified_user_is_redirected_to_notice_and_blocked_from_api(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/vault')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/timeline')->assertRedirect('/email/verify');

        // /me vẫn trả 200 để UI biết trạng thái và điều hướng đúng.
        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);

        // API nghiệp vụ trả JSON lỗi đúng chuẩn, không redirect.
        $this->actingAs($user)->getJson('/api/v1/documents')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_verification_notice_renders_and_resend_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/email/verify')
            ->assertOk()
            ->assertSee('Xác nhận địa chỉ email');

        // Gửi lại: 6 lần đầu OK, lần 7 bị throttle 429.
        foreach (range(1, 6) as $i) {
            $this->actingAs($user)->post('/email/verify/resend')->assertRedirect('/email/verify');
        }

        $this->actingAs($user)->post('/email/verify/resend')->assertStatus(429);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_signed_link_verifies_email_and_grants_access(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Sau khi verify: vào được route nghiệp vụ và API.
        $this->actingAs($user)->get('/timeline')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);
        $this->actingAs($user)->getJson('/api/v1/documents')->assertOk();
    }

    public function test_tampered_or_expired_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        // Link hết hạn → 403.
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinutes(1), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->actingAs($user)->get($expired)->assertStatus(403);

        // Link bị sửa tham số → 403.
        $tampered = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]).'x';
        $this->actingAs($user)->get($tampered)->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verified_user_is_not_redirected_to_notice(): void
    {
        $user = User::factory()->create(); // factory đã set email_verified_at

        $this->actingAs($user)->get('/email/verify')->assertRedirect('/');
    }
}
