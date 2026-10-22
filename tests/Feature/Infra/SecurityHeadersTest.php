<?php

namespace Tests\Feature\Infra;

use Tests\TestCase;

/**
 * NFR-SEC-02 (issue 11): security headers, CSP và cấu hình CORS.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_and_csp_are_present_on_web_response(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        // DocumentViewer mở PDF/ảnh đã giải mã qua Blob URL.
        $this->assertStringContainsString('blob:', $csp);
    }

    public function test_api_error_response_also_carries_security_headers(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_cors_allows_vite_dev_origin_without_credentials(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertContains('http://localhost:5173', $origins);
        $this->assertNotContains('*', $origins);
        $this->assertFalse(config('cors.supports_credentials'));
    }

    public function test_cors_headers_are_returned_for_the_allowed_dev_origin(): void
    {
        $response = $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->getJson('/api/v1/me');

        $response->assertStatus(401);
        $this->assertSame(
            'http://localhost:5173',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_cors_rejects_unknown_origin(): void
    {
        $response = $this->withHeaders(['Origin' => 'http://evil.example'])
            ->getJson('/api/v1/me');

        $response->assertStatus(401);
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
