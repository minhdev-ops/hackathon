<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Đăng ký tài khoản owner mới (chế độ session, FR-AUTH mở rộng cho production).
 *
 * Người dùng tự đăng ký luôn nhận vai trò `owner` — không bao giờ tự chọn
 * `admin`/`agent` từ form (quyền admin chỉ qua Change Request quorum).
 * `vault_kdf_salt` sinh ngẫu nhiên 16 byte ngay khi tạo user để trình duyệt
 * derive khoá két (PBKDF2) không cần vòng lặp thêm.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'Email này đã được đăng ký.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự, gồm cả chữ và số.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        /** @var User $user */
        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => 'owner',
            'vault_kdf_salt' => base64_encode(random_bytes(16)),
        ]);

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        // Gửi link xác nhận (signed URL 60 phút) — tài khoản chỉ dùng được
        // sau khi xác thực (middleware `email.verified`).
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice');
    }
}
