<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-AUTH-02: Verify TideCloak JWT (RS256 via JWKS, cache 10 phút),
 * kiểm tra iss / aud-azp / exp / nbf, map sub → users.tide_sub,
 * tạo user lần đầu nếu chưa có. Không ghi token vào log (NFR-SEC-01).
 */
class VerifyTideJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || ! preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $bearer)) {
            throw new ApiException('UNAUTHENTICATED', 'Chưa đăng nhập hoặc phiên đã hết hạn.', 401);
        }

        $payload = $this->verifyAndDecode($bearer);

        $sub = $payload['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            throw new ApiException('UNAUTHENTICATED', 'Token không chứa sub.', 401);
        }

        $user = User::where('tide_sub', $sub)->first();

        if ($user === null) {
            $roles = is_array($payload['realm_access']['roles'] ?? null)
                ? $payload['realm_access']['roles']
                : [];
            $role = in_array('admin', $roles, true)
                ? 'admin'
                : (in_array('agent', $roles, true) ? 'agent' : 'owner');

            $user = User::create([
                'name' => $payload['name'] ?? $payload['email'] ?? 'User',
                'email' => $payload['email'] ?? '',
                'role' => $role,
                'tide_sub' => $sub,
                'password' => bcrypt(Str::random(24)),
                'email_verified_at' => now(),
                'vault_kdf_salt' => base64_encode(random_bytes(16)),
            ]);
        }

        auth()->setUser($user);

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyAndDecode(string $token): array
    {
        [$headerB64, $payloadB64, $signatureB64] = explode('.', $token, 3);

        $header = json_decode($this->base64UrlDecode($headerB64), true) ?? [];
        $payload = json_decode($this->base64UrlDecode($payloadB64), true) ?? [];

        $kid = $header['kid'] ?? null;
        if ($kid === null) {
            throw new ApiException('UNAUTHENTICATED', 'Token không có kid.', 401);
        }

        $publicKey = $this->publicKeyForKid($kid);

        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = $this->base64UrlDecode($signatureB64);

        if (openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new ApiException('UNAUTHENTICATED', 'Token sai chữ ký.', 401);
        }

        $this->validateClaims($payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClaims(array $payload): void
    {
        $now = time();

        if (isset($payload['exp']) && (int) $payload['exp'] < $now) {
            throw new ApiException('UNAUTHENTICATED', 'Token đã hết hạn.', 401);
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new ApiException('UNAUTHENTICATED', 'Token chưa được kích hoạt.', 401);
        }

        $issuer = config('tidecloak.issuer');
        if ($issuer !== '' && ($payload['iss'] ?? null) !== $issuer) {
            throw new ApiException('UNAUTHENTICATED', 'Sai issuer.', 401);
        }

        $audience = config('tidecloak.audience');
        if ($audience !== '') {
            $aud = $payload['aud'] ?? null;
            $azp = $payload['azp'] ?? null;
            $matched = is_array($aud) ? in_array($audience, $aud, true) : ($aud === $audience || $azp === $audience);
            if (! $matched) {
                throw new ApiException('UNAUTHENTICATED', 'Sai đối tượng nhận token.', 401);
            }
        }
    }

    /**
     * Lấy public key PEM của kid từ JWKS (cache theo realm).
     */
    private function publicKeyForKid(string $kid): string
    {
        $realm = config('tidecloak.realm');
        $cacheKey = 'tidecloak.jwks.' . $realm;

        $pemMap = Cache::remember($cacheKey, config('tidecloak.jwks_cache_seconds'), function (): array {
            $url = config('tidecloak.url') . '/realms/' . $realm . '/protocol/openid-connect/certs';

            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                throw new ApiException('UNAUTHENTICATED', 'Không thể lấy JWKS từ TideCloak.', 401);
            }

            $map = [];

            foreach ($response->json('keys') ?? [] as $key) {
                if (($key['kty'] ?? null) !== 'RSA' || ! isset($key['n'], $key['e'], $key['kid'])) {
                    continue;
                }

                $map[(string) $key['kid']] = $this->buildRsaPublicKeyPem((string) $key['n'], (string) $key['e']);
            }

            return $map;
        });

        if (! isset($pemMap[$kid])) {
            throw new ApiException('UNAUTHENTICATED', 'Không tìm thấy public key cho kid ' . $kid, 401);
        }

        return $pemMap[$kid];
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Xây dựng PEM public key RSA từ JWK n (modulus) và e (exponent).
     */
    private function buildRsaPublicKeyPem(string $nB64Url, string $eB64Url): string
    {
        $n = $this->base64UrlDecode($nB64Url);
        $e = $this->base64UrlDecode($eB64Url);

        if ($n[0] & 0x80) {
            $n = "\x00" . $n;
        }

        $nDer = "\x02" . $this->derLength(strlen($n)) . $n;
        $eDer = "\x02" . $this->derLength(strlen($e)) . $e;

        $seqInner = "\x30" . $this->derLength(strlen($nDer) + strlen($eDer)) . $nDer . $eDer;

        // rsaEncryption OID: 1.2.840.113549.1.1.1
        $algOid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

        $spkiInner = $algOid . "\x03" . $this->derLength(1 + strlen($seqInner)) . "\x00" . $seqInner;
        $spki = "\x30" . $this->derLength(strlen($spkiInner)) . $spkiInner;

        $pemBase64 = base64_encode($spki);
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($pemBase64, 64, "\n") . "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = [];
        while ($length > 0) {
            $bytes[] = $length & 0xff;
            $length >>= 8;
        }

        $bytes = array_reverse($bytes);

        return chr(0x80 | count($bytes)) . implode('', array_map('chr', $bytes));
    }
}
