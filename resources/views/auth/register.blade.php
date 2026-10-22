<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Đăng ký · TrustGate</title>
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
                <h2 class="text-base font-semibold">Đăng ký tài khoản</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Tài khoản mới luôn là <span class="font-medium">owner</span> — sở hữu két tài liệu của riêng bạn.
                </p>

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="/register" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="mb-1 block text-xs font-medium text-slate-600">
                            Họ và tên
                        </label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            required
                            autofocus
                            autocomplete="name"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        >
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-xs font-medium text-slate-600">
                            Email
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-xs font-medium text-slate-600">
                            Mật khẩu
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        >
                        <p class="mt-1 text-[11px] text-slate-500">Tối thiểu 8 ký tự, gồm cả chữ và số.</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-xs font-medium text-slate-600">
                            Xác nhận mật khẩu
                        </label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        Tạo tài khoản
                    </button>
                </form>

                <p class="mt-4 text-center text-xs text-slate-600">
                    Đã có tài khoản?
                    <a href="/login" class="font-medium text-indigo-600 hover:underline">Đăng nhập</a>
                </p>
            </div>
        </div>
    </body>
</html>
