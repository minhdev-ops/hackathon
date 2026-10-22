<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Append-only audit log with a sha256 hash chain (FR-AUD-01).
 *
 * hash = sha256(prev_hash . canonical_json(payload))
 *
 * Never pass plaintext, tokens or K_g in $meta (NFR-SEC-01).
 */
class AuditService
{
    public const ACTOR_USER = 'user';

    public const ACTOR_AGENT = 'agent';

    public const ACTOR_SYSTEM = 'system';

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $action,
        string $actorType,
        string|int|null $actorId,
        string $subjectType,
        string|int|null $subjectId,
        array $meta = [],
    ): AuditLog {
        return DB::transaction(function () use ($action, $actorType, $actorId, $subjectType, $subjectId, $meta) {
            $previous = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();

            $payload = self::payloadFor([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'meta' => $meta,
            ]);

            $prevHash = $previous?->hash;

            return AuditLog::create([
                'actor_type' => $payload['actor_type'],
                'actor_id' => $payload['actor_id'],
                'action' => $payload['action'],
                'subject_type' => $payload['subject_type'],
                'subject_id' => $payload['subject_id'],
                'meta' => $payload['meta'],
                'prev_hash' => $prevHash,
                'hash' => self::chainHash($payload, $prevHash),
            ]);
        });
    }

    /**
     * Recompute the whole chain and report the first broken row.
     *
     * @return array{valid: bool, checked: int, broken_at: int|null, reason?: string, expected_hash?: string|null, actual_hash?: string|null}
     */
    public function verifyChain(): array
    {
        $previousHash = null;
        $checked = 0;

        foreach (AuditLog::query()->orderBy('id')->cursor() as $row) {
            $checked++;

            $payload = self::payloadFor([
                'actor_type' => $row->actor_type,
                'actor_id' => $row->actor_id,
                'action' => $row->action,
                'subject_type' => $row->subject_type,
                'subject_id' => $row->subject_id,
                'meta' => $row->meta ?? [],
            ]);

            $expected = self::chainHash($payload, $previousHash);
            $storedPrev = $row->prev_hash === null ? null : (string) $row->prev_hash;

            if ($storedPrev !== $previousHash) {
                return [
                    'valid' => false,
                    'checked' => $checked,
                    'broken_at' => (int) $row->getKey(),
                    'reason' => 'prev_hash_mismatch',
                    'expected_hash' => $previousHash,
                    'actual_hash' => $storedPrev,
                ];
            }

            if (! hash_equals($expected, (string) $row->hash)) {
                return [
                    'valid' => false,
                    'checked' => $checked,
                    'broken_at' => (int) $row->getKey(),
                    'reason' => 'hash_mismatch',
                    'expected_hash' => $expected,
                    'actual_hash' => (string) $row->hash,
                ];
            }

            $previousHash = (string) $row->hash;
        }

        return [
            'valid' => true,
            'checked' => $checked,
            'broken_at' => null,
        ];
    }

    /**
     * The exact payload that goes into the chain, normalised so it can be
     * recomputed from a stored row (used by GET /audit/verify later).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function payloadFor(array $row): array
    {
        return [
            'actor_type' => (string) $row['actor_type'],
            'actor_id' => $row['actor_id'] === null ? null : (string) $row['actor_id'],
            'action' => (string) $row['action'],
            'subject_type' => (string) $row['subject_type'],
            'subject_id' => $row['subject_id'] === null ? null : (string) $row['subject_id'],
            'meta' => self::canonicalize($row['meta'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function chainHash(array $payload, ?string $prevHash): string
    {
        return hash('sha256', ($prevHash ?? '').json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = array_map(self::canonicalize(...), $value);

        if (! array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
    }
}
