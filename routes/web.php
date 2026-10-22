<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

Route::get('/', function () {
    return view('welcome');
});

// SPA shell cho các trang React (client-side router).
// `email.verified`: user mới đăng ký chưa xác nhận email chỉ vào được trang /email/verify.
Route::get('/vault', fn () => view('welcome'))
    ->middleware(['auth', 'email.verified', 'role:owner'])
    ->name('vault');

Route::get('/playbooks', fn () => view('welcome'))
    ->middleware(['auth', 'email.verified', 'role:owner,admin'])
    ->name('playbooks');

Route::get('/tasks/new', fn () => view('welcome'))
    ->middleware(['auth', 'email.verified', 'role:owner'])
    ->name('tasks.new');

Route::get('/tasks/{task}', fn () => view('welcome'))
    ->middleware(['auth', 'email.verified', 'role:owner,admin'])
    ->name('tasks.show');

Route::get('/grants/{grant}', fn () => view('welcome'))
    ->middleware(['auth', 'email.verified', 'role:owner'])
    ->name('grants.show');

Route::get('/agent-console', fn () => view('welcome'))
    ->middleware(['auth', 'email.verified', 'role:owner,admin'])
    ->name('agent.console');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // Chống brute-force: tối đa 10 lần/phút cho mỗi IP.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Xác nhận email (FR-AUTH mở rộng cho production)
|--------------------------------------------------------------------------
| Link trong email là signed URL 60 phút tới /email/verify/{id}/{hash}.
| User chưa xác thực bị chặn ở mọi route nghiệp vụ bởi middleware `verified`.
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        // Dev (APP_DEBUG=true) hoặc demo (APP_DEMO=true): mail chỉ ghi vào log
        // nên hiển thị luôn link xác nhận trên trang để không bị kẹt khi
        // không có SMTP thật. Production (APP_DEBUG=false) không hiện.
        $devVerifyUrl = null;
        if (config('app.debug') || config('trustgate.app_demo')) {
            $devVerifyUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id' => auth()->id(),
                'hash' => sha1(auth()->user()->getEmailForVerification()),
            ]);
        }

        return view('auth.verify-notice', ['devVerifyUrl' => $devVerifyUrl]);
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verify/resend', [VerifyEmailController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.resend');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/admin/quorum', fn () => view('admin.quorum'))
    ->middleware(['auth', 'email.verified', 'role:admin'])
    ->name('admin.quorum');

Route::get('/admin/evil', fn () => view('admin.evil'))
    ->middleware(['auth', 'email.verified', 'role:admin', 'demo.enabled'])
    ->name('admin.evil');

Route::get('/timeline', fn () => view('timeline'))
    ->middleware(['auth', 'email.verified', 'role:owner,admin'])
    ->name('timeline');
