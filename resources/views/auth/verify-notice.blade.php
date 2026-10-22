<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Xác nhận email · TrustGate</title>
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50 text-slate-900">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-10">
            <div class="mb-6 flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white">
                    TG
                </span>
                <div>
                    <h1 class="text-lg font-semibold">TrustGate</h1>
                    <p class="text-xs text-slate-500">Két dữ liệu &amp; Consent Broker cho AI Agent</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Xác nhận địa chỉ email</h2>

                @if (session('status') === 'verification-link-sent')
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        Đã gửi lại email xác nhận — kiểm tra hộp thư của bạn.
                    </div>
                @endif

                <p class="mt-3 text-sm text-slate-600">
                    Chúng tôi đã gửi liên kết xác nhận tới
                    <span class="font-medium text-slate-900">{{ auth()->user()->email }}</span>.
                    Bấm liên kết trong email để kích hoạt tài khoản (hiệu lực 60 phút).
                </p>

                <p class="mt-2 text-xs text-slate-500">
                    Không thấy email? Kiểm tra thư mục spam, hoặc gửi lại bên dưới.
                    Liên kết có hiệu lực trong 60 phút.
                </p>

                @if ($devVerifyUrl)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <p class="font-medium">Chế độ demo — chưa cấu hình SMTP.</p>
                        <p class="mt-1">Email được ghi vào log; bấm nút bên dưới để xác nhận ngay:</p>
                        <a
                            href="{{ $devVerifyUrl }}"
                            class="mt-2 inline-block rounded-lg bg-amber-600 px-3 py-1.5 font-medium text-white hover:bg-amber-700"
                        >
                            Xác nhận email ngay (demo)
                        </a>
                    </div>
                @endif

                <form method="POST" action="/email/verify/resend" class="mt-5">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        Gửi lại email xác nhận
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="text-xs text-slate-500 hover:underline">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
