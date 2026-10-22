<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Playbook;
use App\Models\User;
use App\Services\QuorumSettings;
use App\Support\ChangeRequestType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuorumMetaController extends Controller
{
    public function __construct(private readonly QuorumSettings $settings) {}

    /**
     * GET /admin/quorum/meta — ngưỡng, trạng thái QEA, re-auth, dữ liệu form.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $minutes = max(1, (int) config('quorum.reauth_minutes', 5));
        $reauthAt = $request->session()->get('admin_reauth_at');
        $reauthUntil = is_int($reauthAt)
            ? now()->setTimestamp($reauthAt)->addMinutes($minutes)
            : null;

        $qeaEnabled = (bool) config('quorum.qea_enabled', false);

        return response()->json([
            'data' => [
                'threshold' => $this->settings->threshold(),
                'config_threshold' => (int) config('quorum.threshold', 2),
                'reauth' => [
                    'required' => true,
                    'minutes' => $minutes,
                    'reauthenticated_until' => $reauthUntil?->toIso8601String(),
                    'is_fresh' => $reauthUntil?->isFuture() ?? false,
                ],
                'qea' => [
                    'enabled' => $qeaEnabled,
                    'label' => $qeaEnabled ? 'QEA: bật (TideCloak realm)' : 'Fallback: quorum mức app',
                    'note' => $qeaEnabled
                        ? 'Thay đổi quyền admin của realm dùng QEA; app vẫn kiểm tra chữ ký khi thực thi.'
                        : 'Chưa xác minh V4/V5: dùng quorum mức app + bắt buộc xác thực lại trước khi duyệt.',
                ],
                'types' => ChangeRequestType::options(),
                'doc_types' => config('trustgate.doc_types', []),
                'playbooks' => Playbook::query()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Playbook $playbook) => [
                        'slug' => $playbook->slug,
                        'name' => $playbook->name,
                        'recipient_label' => $playbook->recipient_label,
                        'required_doc_types' => $playbook->required_doc_types ?? [],
                        'optional_doc_types' => $playbook->optional_doc_types ?? [],
                        'forbidden_doc_types' => $playbook->forbidden_doc_types ?? [],
                        'default_ttl_minutes' => $playbook->default_ttl_minutes,
                        'max_ttl_minutes' => $playbook->max_ttl_minutes,
                        'version' => $playbook->version,
                    ])
                    ->values(),
                'agents' => Agent::query()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Agent $agent) => [
                        'client_id' => $agent->client_id,
                        'name' => $agent->name,
                        'status' => $agent->status,
                        'allowed_playbooks' => $agent->allowed_playbooks ?? [],
                    ])
                    ->values(),
                'users' => User::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'role'])
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ])
                    ->values(),
            ],
        ]);
    }
}
