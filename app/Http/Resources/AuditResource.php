<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditResource extends JsonResource
{
    private const LABELS = [
        'task.created' => 'Tạo việc cho agent',
        'proposal.generated' => 'Agent tạo đề xuất',
        'grant.approved' => 'Duyệt grant',
        'grant.denied' => 'Từ chối grant',
        'grant.revoked' => 'Thu hồi grant',
        'grant.expired' => 'Grant hết hạn',
        'grant.opened' => 'Agent mở grant',
        'grant.open_denied' => 'Agent bị chặn mở grant',
        'document.uploaded' => 'Tải tài liệu lên',
        'document.viewed' => 'Xem tài liệu',
        'document.deleted' => 'Xoá tài liệu',
        'agent.document_request.out_of_scope' => 'Agent xin tài liệu ngoài phạm vi',
        'change_request.created' => 'Tạo yêu cầu quorum',
        'change_request.approved' => 'Duyệt yêu cầu quorum',
        'change_request.vote_rejected' => 'Từ chối yêu cầu quorum',
        'change_request.executed' => 'Thực thi yêu cầu quorum',
        'change_request.rejected' => 'Yêu cầu quorum bị từ chối',
        'role.granted' => 'Cấp vai trò',
        'role.revoked' => 'Thu hồi vai trò',
        'agent.allowlist_updated' => 'Cập nhật quyền agent',
        'playbook.updated' => 'Cập nhật playbook',
        'quorum.threshold_updated' => 'Cập nhật ngưỡng quorum',
    ];

    private const FORBIDDEN_META_KEYS = [
        'access_token',
        'authorization',
        'ciphertext',
        'content_b64',
        'file',
        'grant_key',
        'grant_key_b64',
        'instruction',
        'key',
        'password',
        'plaintext',
        'refresh_token',
        'title',
        'title_ct',
        'token',
    ];

    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        return [
            'id' => (int) $log->getKey(),
            'action' => $log->action,
            'action_label' => self::LABELS[$log->action] ?? $log->action,
            'actor_type' => $log->actor_type,
            'actor_id' => $log->actor_id,
            'actor_name' => $log->actor_name ?? null,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'meta' => self::safeMeta($log->meta ?? []),
            'prev_hash' => $log->prev_hash,
            'hash' => $log->hash,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function safeMeta(array $meta): array
    {
        $safe = [];

        foreach ($meta as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_META_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::safeMeta($value);

                continue;
            }

            if (is_string($value) && strlen($value) > 500) {
                $safe[$key] = substr($value, 0, 500).'…';

                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }
}
