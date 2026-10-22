<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * FR-AUTH-03: RequireRole:<role>.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                throw new ApiException(
                    'FORBIDDEN_ROLE',
                    'Bạn không có vai trò cần thiết để thực hiện hành động này.',
                    403,
                );
            }

            abort(403);
        }

        return $next($request);
    }
}
