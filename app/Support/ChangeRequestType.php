<?php

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\ChangeRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Payload schema + labels for the five CR types (FR-QRM-01).
 */
class ChangeRequestType
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            ChangeRequest::TYPE_ROLE_GRANT,
            ChangeRequest::TYPE_ROLE_REVOKE,
            ChangeRequest::TYPE_PLAYBOOK_UPDATE,
            ChangeRequest::TYPE_AGENT_ALLOWLIST_UPDATE,
            ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            ChangeRequest::TYPE_ROLE_GRANT => 'Cấp vai trò',
            ChangeRequest::TYPE_ROLE_REVOKE => 'Thu hồi vai trò',
            ChangeRequest::TYPE_PLAYBOOK_UPDATE => 'Cập nhật playbook',
            ChangeRequest::TYPE_AGENT_ALLOWLIST_UPDATE => 'Cập nhật allowlist agent',
            ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE => 'Đổi ngưỡng quorum',
            default => $type,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $type) => ['value' => $type, 'label' => self::label($type)],
            self::all(),
        );
    }

    /**
     * Validate and normalise the CR payload before it is stored.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validate(string $type, array $payload): array
    {
        if (! in_array($type, self::all(), true)) {
            throw new ApiException(
                'INVALID_CHANGE_REQUEST_TYPE',
                'Loại Change Request không hợp lệ.',
                422,
                ['allowed_types' => self::all()],
            );
        }

        $rules = [];

        foreach (self::rules($type) as $field => $fieldRules) {
            $rules['payload.'.$field] = $fieldRules;
        }

        $validated = Validator::make(['payload' => $payload], $rules)->validate();

        /** @var array<string, mixed> $data */
        $data = $validated['payload'];

        if ($type === ChangeRequest::TYPE_PLAYBOOK_UPDATE) {
            self::guardPlaybookUpdate($data);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rules(string $type): array
    {
        $docTypes = config('trustgate.doc_types', []);

        return match ($type) {
            ChangeRequest::TYPE_ROLE_GRANT => [
                'email' => ['required', 'email', 'exists:users,email'],
                'role' => ['required', 'string', 'in:owner,admin,agent'],
            ],
            ChangeRequest::TYPE_ROLE_REVOKE => [
                'email' => ['required', 'email', 'exists:users,email'],
                'role' => ['required', 'string', 'in:admin,agent'],
            ],
            ChangeRequest::TYPE_PLAYBOOK_UPDATE => [
                'slug' => ['required', 'string', 'exists:playbooks,slug'],
                'required_doc_types' => ['sometimes', 'array'],
                'required_doc_types.*' => ['string', 'in:'.implode(',', $docTypes)],
                'optional_doc_types' => ['sometimes', 'array'],
                'optional_doc_types.*' => ['string', 'in:'.implode(',', $docTypes)],
                'forbidden_doc_types' => ['sometimes', 'array'],
                'forbidden_doc_types.*' => ['string', 'in:'.implode(',', $docTypes)],
                'recipient_label' => ['sometimes', 'string', 'max:100'],
                'default_ttl_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
                'max_ttl_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
            ],
            ChangeRequest::TYPE_AGENT_ALLOWLIST_UPDATE => [
                'client_id' => ['required', 'string', 'exists:agents,client_id'],
                'allowed_playbooks' => ['required', 'array', 'min:1'],
                'allowed_playbooks.*' => ['string', 'distinct', 'exists:playbooks,slug'],
            ],
            ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE => [
                'required_approvals' => ['required', 'integer', 'min:1', 'max:5'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function guardPlaybookUpdate(array $payload): void
    {
        $mutable = [
            'required_doc_types',
            'optional_doc_types',
            'forbidden_doc_types',
            'recipient_label',
            'default_ttl_minutes',
            'max_ttl_minutes',
        ];

        if (array_intersect(array_keys($payload), $mutable) === []) {
            throw ValidationException::withMessages([
                'payload' => 'Payload playbook_update phải có ít nhất một trường thay đổi.',
            ]);
        }
    }
}
