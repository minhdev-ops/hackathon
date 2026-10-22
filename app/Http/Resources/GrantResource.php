<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\Grant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Grant detail for the Owner consent screen (FR-GRANT-01) plus the countdown
 * data the client needs (FR-GRANT-05).
 *
 * @mixin Grant
 */
class GrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Grant $grant */
        $grant = $this->resource;
        $grant->loadMissing(['agent', 'task.playbook']);

        $task = $grant->task;
        $playbook = $task?->playbook;
        $proposal = is_array($task?->proposal) ? $task->proposal : [];

        $required = array_values(array_unique([
            ...($playbook?->required_doc_types ?? []),
            ...$this->stringList($proposal['required'] ?? null),
        ]));
        $optional = $this->stringList($proposal['optional'] ?? null);

        if ($required === [] && $optional === [] && $playbook !== null) {
            $optional = $this->stringList($playbook->optional_doc_types);
        }

        $docTypes = array_values(array_unique([...$required, ...$optional]));

        $documents = $docTypes === []
            ? collect()
            : Document::query()
                ->where('owner_id', $grant->owner_id)
                ->whereIn('doc_type', $docTypes)
                ->orderBy('doc_type')
                ->orderBy('created_at')
                ->get()
                ->map(fn (Document $document) => [
                    'id' => $document->id,
                    'doc_type' => $document->doc_type,
                    'title_ct' => $document->title_ct,
                    'iv' => $document->iv,
                    'sha256' => $document->sha256,
                    'size_bytes' => $document->size_bytes,
                    'enc_mode' => $document->enc_mode,
                    'required' => in_array($document->doc_type, $required, true),
                    'selected' => true,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                ])
                ->values();

        $ttlOptions = $playbook !== null
            ? $playbook->allowedTtlOptions(config('trustgate.grant_ttl_options'))
            : [];

        $defaultTtl = $playbook?->default_ttl_minutes;

        if ($defaultTtl !== null && ! in_array($defaultTtl, $ttlOptions, true)) {
            $defaultTtl = $ttlOptions[0] ?? null;
        }

        return [
            'id' => $grant->id,
            'task_id' => $grant->task_id,
            'owner_id' => $grant->owner_id,
            'agent' => [
                'id' => $grant->agent_id,
                'name' => $grant->agent?->name,
            ],
            'purpose' => $grant->purpose,
            'status' => $grant->status,
            'doc_ids' => $grant->docIds(),
            'approved_at' => $grant->approved_at?->toIso8601String(),
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'revoked_at' => $grant->revoked_at?->toIso8601String(),
            'remaining_seconds' => $grant->remainingSeconds(),
            'server_time' => now()->toIso8601String(),
            'is_openable' => $grant->isActive(),
            'review' => [
                'recipient_label' => $playbook?->recipient_label,
                'playbook' => $playbook === null ? null : [
                    'slug' => $playbook->slug,
                    'name' => $playbook->name,
                    'max_ttl_minutes' => $playbook->max_ttl_minutes,
                    'default_ttl_minutes' => $playbook->default_ttl_minutes,
                ],
                'rationale' => $proposal['rationale'] ?? null,
                'required_doc_types' => $required,
                'optional_doc_types' => $optional,
                'ttl_options' => $ttlOptions,
                'default_ttl_minutes' => $defaultTtl,
                'documents' => $documents,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
