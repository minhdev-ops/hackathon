<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Đăng nhập · TrustGate</title>
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
                    <p class="text-xs text-slate-500">Quorum quản trị · TideCloak Hackathon</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Đăng nhập admin</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Chỉ tài khoản có vai trò <span class="font-medium">admin</span> mới vào được màn quorum.
                </p>

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="/login" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-xs font-medium text-slate-600">
                            Email
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', 'admin1@example.com') }}"
                            required
                            autofocus
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
                            value="password"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        >
                    </div>

                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300">
                        Ghi nhớ đăng nhập
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        Đăng nhập
                    </button>
                </form>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-white/80 p-4 text-xs text-slate-600">
                <p class="font-medium text-slate-700">Tài khoản demo (mật khẩu: password)</p>
                <ul class="mt-2 space-y-1 font-mono text-[11px]">
                    <li>admin1@example.com · admin</li>
                    <li>admin2@example.com · admin</li>
                    <li>admin3@example.com · admin</li>
                    <li>lan@example.com · owner</li>
                </ul>
            </div>

            <p class="mt-4 text-center text-xs text-slate-500">
                Đăng nhập tạm cho demo cục bộ — TideCloak OIDC sẽ thay thế ở WP3.
            </p>
        </div>
    </body>
</html>
