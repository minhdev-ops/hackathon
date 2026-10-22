<?php

namespace App\Services;

use App\Exceptions\GrantUnavailableException;

/**
 * AES-256-GCM envelope for the approved document bundle.
 *
 * Wire format matches WebCrypto in the browser: the 16-byte GCM tag is
 * appended to the ciphertext, the IV (12 bytes) and the key travel as base64.
 * Plaintext payload: {"documents":[{"doc_id","doc_type","name","content_b64"}]}
 */
class GrantBundleCipher
{
    public function decrypt(string $ciphertext, string $ivB64, string $keyB64): array
    {
        $key = base64_decode($keyB64, true);
        $iv = base64_decode($ivB64, true);

        if ($key === false || strlen($key) !== (int) config('trustgate.grant_bundle_key_bytes')) {
            throw GrantUnavailableException::make();
        }

        if ($iv === false || strlen($iv) !== (int) config('trustgate.grant_bundle_iv_bytes')) {
            throw GrantUnavailableException::make();
        }

        $tagLength = (int) config('trustgate.grant_bundle_tag_bytes');

        if (strlen($ciphertext) <= $tagLength) {
            throw GrantUnavailableException::make();
        }

        $tag = substr($ciphertext, -$tagLength);
        $raw = substr($ciphertext, 0, -$tagLength);

        $plaintext = openssl_decrypt($raw, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw GrantUnavailableException::make();
        }

        $payload = json_decode($plaintext, true);
        $documents = is_array($payload) ? ($payload['documents'] ?? null) : null;

        if (! is_array($documents)) {
            throw GrantUnavailableException::make();
        }

        return ['documents' => array_values($documents)];
    }

    /**
     * Helper used by tooling/tests to build a bundle the same way the browser does.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ciphertext: string, iv: string, key: string}
     */
    public function encrypt(array $payload, ?string $keyB64 = null, ?string $ivB64 = null): array
    {
        $key = $keyB64 !== null
            ? base64_decode($keyB64, true)
            : random_bytes((int) config('trustgate.grant_bundle_key_bytes'));
        $iv = $ivB64 !== null
            ? base64_decode($ivB64, true)
            : random_bytes((int) config('trustgate.grant_bundle_iv_bytes'));

        if ($key === false || $iv === false) {
            throw new \InvalidArgumentException('Invalid key or IV for bundle encryption.');
        }

        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        return [
            'ciphertext' => $ciphertext.$tag,
            'iv' => base64_encode($iv),
            'key' => base64_encode($key),
        ];
    }
}
