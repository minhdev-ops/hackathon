<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực nhóm route /api/v1 theo `AUTH_MODE` (issue 01 / SRS mục 12):
 *
 *  - `session` (mặc định, demo tạm): Laravel session guard như cũ.
 *  - `tide` (chính thức khi có token TideCloak/OIDC): VerifyTideJwt
 *    verify RS256 theo JWKS của realm, map `sub` → `users.tide_sub`.
 *
 * Middleware tự quyết định lúc request nên test/demo đổi `AUTH_MODE`
 * bằng config mà không cần đăng ký lại route.
 */
class ApiAuth
{
    public function __construct(private readonly VerifyTideJwt $verifyTideJwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('tidecloak.auth_mode') === 'tide') {
            return $this->verifyTideJwt->handle($request, $next);
        }

        return app(Authenticate::class)->handle($request, $next);
    }
}
