<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ cho phép truy cập nhóm route /demo/* khi APP_DEMO=true.
 * Khi tắt, trả 404 để không lộ sự tồn tại của tính năng demo.
 */
class EnsureDemoEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('trustgate.app_demo')) {
            abort(404);
        }

        return $next($request);
    }
}
