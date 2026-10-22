<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * NFR-SEC-02: security headers + CSP cơ bản cho mọi response.
 * Chặn script ngoài, chặn clickjacking (frame-ancestors/X-Frame-Options),
 * không lộ referrer; cho phép origin Vite dev khi chạy local.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // Dev: Vite dev server tách origin (JS/HMR). Đọc URL thực từ
        // file `public/hot` để không cứng cổng 5173 — dev server có thể
        // nhảy cổng (5174, 5175…) khi cổng cũ bị chiếm, và CSP cứng cổng
        // sẽ chặn toàn bộ JS → React không hydrate → mọi nút bấm chết.
        $dev = '';
        if (config('app.env') !== 'production') {
            $dev = ' '.$this->viteDevOrigins();
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'".$dev,
            // React inline style attributes + Tailwind.
            "style-src 'self' 'unsafe-inline'".$dev,
            // Blob URL của DocumentViewer (xem PDF/ảnh đã giải mã).
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'".$dev,
            "frame-src 'self' blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Danh sách origin Vite dev (http + ws) cho CSP, đọc từ `public/hot`.
     * Khi không có file (production build) hoặc đọc lỗi → fallback 5173.
     */
    private function viteDevOrigins(): string
    {
        $hot = base_path('public/hot');
        $url = is_file($hot) ? trim((string) file_get_contents($hot)) : '';

        if ($url === '' || ! preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $url)) {
            $url = 'http://127.0.0.1:5173';
        }

        $ws = preg_replace('#^http#', 'ws', $url);

        return $url.' '.$ws;
    }
}
