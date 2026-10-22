<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Xác thực email qua signed URL (FR-AUTH mở rộng cho production).
 *
 * Thay `Illuminate\Foundation\Auth\EmailVerificationController` (không còn
 * trong Laravel 12 core). Link = signed URL 60 phút tới
 * `/email/verify/{id}/{hash}` với hash = sha1(email).
 */
class VerifyEmailController extends Controller
{
    /**
     * GET /email/verify/{id}/{hash} — xác nhận email (middleware `signed` đã verify chữ ký).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->route('id') != $user->getKey()
            || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended('/');
    }

    /**
     * POST /email/verify/resend — gửi lại email (throttle 6 lần/phút ở route).
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect('/');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
