<?php

namespace App\Services;

use App\Models\Grant;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Stores the per-grant AES key (K_g) in Redis with a TTL equal to the grant
 * lifetime. Revoking or expiring a grant deletes the key (crypto-shredding).
 *
 * K_g is never written to the database or to a log line.
 */
class GrantKeyStore
{
    public function put(Grant $grant, string $keyB64, int $ttlSeconds): void
    {
        $this->store()->put($this->cacheKey($grant), $keyB64, max(1, $ttlSeconds));
    }

    public function get(Grant $grant): ?string
    {
        $key = $this->store()->get($this->cacheKey($grant));

        return is_string($key) ? $key : null;
    }

    public function forget(Grant $grant): void
    {
        $this->store()->forget($this->cacheKey($grant));
    }

    public function cacheKey(Grant $grant): string
    {
        return config('trustgate.grant_key_prefix').$grant->getKey();
    }

    private function store(): Repository
    {
        return Cache::store(config('trustgate.grant_key_store'));
    }
}
