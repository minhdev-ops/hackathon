<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn user chưa xác nhận email khỏi khu vực đã đăng nhập (FR-AUTH mở rộng).
 *
 * - Web: redirect về trang `verification.notice` (kèm intended URL để quay lại).
 * - JSON/API: 403 EMAIL_NOT_VERIFIED để client hiển thị đúng lỗi.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'EMAIL_NOT_VERIFIED',
                        'message' => 'Bạn cần xác nhận địa chỉ email trước khi dùng hệ thống.',
                    ],
                ], 403);
            }

            return new RedirectResponse(URL::route('verification.notice'));
        }

        return $next($request);
    }
}
