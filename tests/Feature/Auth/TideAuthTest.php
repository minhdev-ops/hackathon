<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-AUTH-01..04 (issue 01): xác thực Bearer JWT RS256 theo JWKS của realm.
 * Test dùng khoá RSA sinh trong tiến trình + JWKS giả — không phụ thuộc mạng.
 */
class TideAuthTest extends TestCase
{
    use RefreshDatabase;

    private const KID = 'trustgate-test-key';

    /** @var array{private: \OpenSSLAsymmetricKey, n: string, e: string} */
    private array $rsa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rsa = $this->generateRsa();

        config([
            'tidecloak.auth_mode' => 'tide',
            'tidecloak.url' => 'http://tide.test',
            'tidecloak.realm' => 'testrealm',
            'tidecloak.client_id' => 'mytest',
            'tidecloak.audience' => 'mytest',
            'tidecloak.issuer' => 'http://tide.test/realms/testrealm',
        ]);

        Http::fake([
            'http://tide.test/realms/testrealm/protocol/openid-connect/certs' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'kid' => self::KID,
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'n' => $this->rsa['n'],
                    'e' => $this->rsa['e'],
                ]],
            ]),
        ]);

        Cache::flush();
    }

    public function test_missing_token_is_401(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_token_with_bad_signature_is_401(): void
    {
        $other = $this->generateRsa();

        $token = $this->makeToken(kid: self::KID, signingKey: $other['private']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_token_is_401(): void
    {
        $token = $this->makeToken(exp: time() - 60);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_wrong_issuer_and_audience_are_401(): void
    {
        $wrongIssuer = $this->makeToken(iss: 'http://khac.test/realms/myrealm');
        $this->withHeaders(['Authorization' => 'Bearer '.$wrongIssuer])
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $wrongAudience = $this->makeToken(aud: 'client-khac', azp: 'client-khac');
        $this->withHeaders(['Authorization' => 'Bearer '.$wrongAudience])
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_valid_token_maps_sub_to_single_user_and_role_from_realm_roles(): void
    {
        $token = $this->makeToken(sub: 'tide-user-1', name: 'Lan Tide', email: 'lan.tide@example.com');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'lan.tide@example.com')
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.tide_sub', 'tide-user-1')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'tide_sub', 'vault_kdf_salt']]);

        // FR-AUTH-04: gọi lần hai không tạo user thứ hai.
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertNotNull(User::first()->vault_kdf_salt);
    }

    public function test_admin_realm_role_gives_admin_api_access_and_owner_is_forbidden(): void
    {
        $adminToken = $this->makeToken(sub: 'tide-admin', email: 'admin.tide@example.com', roles: ['admin']);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/api/v1/admin/quorum/meta')
            ->assertOk();

        $ownerToken = $this->makeToken(sub: 'tide-owner', email: 'owner.tide@example.com', roles: ['owner']);

        $this->withHeaders(['Authorization' => 'Bearer '.$ownerToken])
            ->getJson('/api/v1/admin/quorum/meta')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_ROLE');
    }

    public function test_token_is_never_written_to_the_log(): void
    {
        $token = $this->makeToken(sub: 'tide-log-check', email: 'log@example.com');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/me')
            ->assertOk();

        $logPath = storage_path('logs/laravel.log');
        $contents = is_file($logPath) ? (string) file_get_contents($logPath) : '';

        $this->assertStringNotContainsString($token, $contents);
        $this->assertStringNotContainsString($this->rsa['n'], $contents);
    }

    public function test_session_mode_ignores_bearer_and_uses_session(): void
    {
        config(['tidecloak.auth_mode' => 'session']);

        // Bearer token bị bỏ qua hoàn toàn ở chế độ session: không verify,
        // không tạo user từ sub (chỉ phiên session mới xác thực được).
        $this->withHeaders(['Authorization' => 'Bearer '.$this->makeToken()])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->assertDatabaseCount('users', 0);

        // Session vẫn hoạt động bình thường.
        $owner = User::factory()->owner()->create(['email' => 'session@example.com']);

        $this->actingAs($owner)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'session@example.com');
    }

    /**
     * @param  list<string>  $roles
     */
    private function makeToken(
        string $sub = 'tide-user-1',
        string $name = 'Lan',
        string $email = 'lan@example.com',
        array $roles = ['owner'],
        ?int $exp = null,
        ?string $iss = null,
        string $aud = 'mytest',
        string $azp = 'mytest',
        string $kid = self::KID,
        ?\OpenSSLAsymmetricKey $signingKey = null,
    ): string {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid];
        $payload = [
            'iss' => $iss ?? 'http://tide.test/realms/testrealm',
            'aud' => $aud,
            'azp' => $azp,
            'sub' => $sub,
            'name' => $name,
            'email' => $email,
            'exp' => $exp ?? time() + 600,
            'iat' => time(),
            'realm_access' => ['roles' => $roles],
        ];

        $encode = fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $signingInput = $encode($header).'.'.$encode($payload);

        openssl_sign($signingInput, $signature, $signingKey ?? $this->rsa['private'], OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * @return array{private: \OpenSSLAsymmetricKey, n: string, e: string}
     */
    private function generateRsa(): array
    {
        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $details = openssl_pkey_get_details($private);

        $toBase64Url = fn (string $binary): string => rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');

        return [
            'private' => $private,
            'n' => $toBase64Url($details['rsa']['n']),
            'e' => $toBase64Url($details['rsa']['e']),
        ];
    }
}
