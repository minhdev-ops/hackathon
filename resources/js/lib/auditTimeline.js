const TIME_ZONE = 'Asia/Ho_Chi_Minh';

const SAFE_META_KEYS = new Set([
    'action',
    'agent_name',
    'allowed_playbooks',
    'approvals_count',
    'change_request_id',
    'client_id',
    'doc_count',
    'doc_ids',
    'doc_type',
    'expires_at',
    'new_role',
    'reason',
    'rejections_count',
    'required_approvals',
    'role',
    'slug',
    'status',
    'ttl_minutes',
    'type',
    'version',
]);

const META_LABELS = {
    approvals_count: 'Phiếu duyệt',
    change_request_id: 'Yêu cầu quorum',
    client_id: 'Client',
    doc_count: 'Số tài liệu',
    doc_type: 'Loại tài liệu',
    expires_at: 'Hết hạn lúc',
    new_role: 'Vai trò mới',
    reason: 'Lý do',
    rejections_count: 'Phiếu từ chối',
    required_approvals: 'Ngưỡng duyệt',
    role: 'Vai trò',
    slug: 'Playbook',
    status: 'Trạng thái',
    ttl_minutes: 'TTL (phút)',
    type: 'Loại thay đổi',
    version: 'Phiên bản',
};

const SUBJECT_LABELS = {
    agent: 'Agent',
    agent_task: 'Việc agent',
    change_request: 'Yêu cầu quorum',
    document: 'Tài liệu',
    grant: 'Grant',
    playbook: 'Playbook',
    setting: 'Cấu hình',
    user: 'Người dùng',
};

export function formatTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('vi-VN', {
        timeZone: TIME_ZONE,
        hour12: false,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export function formatDay(value) {
    if (!value) {
        return 'Không rõ ngày';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Không rõ ngày';
    }

    return date.toLocaleDateString('vi-VN', {
        timeZone: TIME_ZONE,
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function grantOpenedSentence(entry) {
    const count = Number(entry?.meta?.doc_count ?? 0);
    const actor = entry?.actor_name
        || (entry?.actor_id ? `Agent #${entry.actor_id}` : 'Agent');
    const openedAt = formatTime(entry?.created_at);
    const expiresAt = entry?.meta?.expires_at ? formatTime(entry.meta.expires_at) : null;

    return `${actor} đã mở ${count} tài liệu lúc ${openedAt}${expiresAt ? `, hết hạn lúc ${expiresAt}` : ''}.`;
}

export function actionTitle(entry) {
    if (entry?.action === 'grant.opened') {
        return grantOpenedSentence(entry);
    }

    return entry?.action_label || entry?.action || 'Sự kiện không rõ';
}

export function actorLabel(entry) {
    if (entry?.actor_name) {
        return entry.actor_name;
    }

    if (entry?.actor_type === 'system') {
        return 'Hệ thống';
    }

    if (entry?.actor_type === 'agent') {
        return entry.actor_id ? `Agent #${String(entry.actor_id).slice(0, 8)}` : 'Agent';
    }

    return entry?.actor_id ? `Người dùng #${entry.actor_id}` : 'Hệ thống';
}

export function subjectLabel(entry) {
    const type = SUBJECT_LABELS[entry?.subject_type] || entry?.subject_type || 'Đối tượng';

    if (!entry?.subject_id) {
        return type;
    }

    return `${type} #${String(entry.subject_id).slice(0, 8)}`;
}

export function safeMetaSummary(meta) {
    if (!meta || typeof meta !== 'object') {
        return '';
    }

    const parts = [];

    for (const [key, value] of Object.entries(meta)) {
        if (!SAFE_META_KEYS.has(key) || value === null || value === undefined || value === '') {
            continue;
        }

        let rendered;

        if (key === 'expires_at') {
            rendered = formatTime(value);
        } else if (key === 'doc_ids' && Array.isArray(value)) {
            rendered = `${value.length} tài liệu`;
        } else if (Array.isArray(value)) {
            rendered = value.join(', ');
        } else {
            rendered = String(value);
        }

        if (rendered.length > 80) {
            rendered = `${rendered.slice(0, 80)}…`;
        }

        parts.push(`${META_LABELS[key] || key}: ${rendered}`);

        if (parts.length === 4) {
            break;
        }
    }

    return parts.join(' · ');
}

export function groupLogsByDay(logs) {
    const byDay = new Map();

    for (const log of logs) {
        const day = formatDay(log.created_at);

        if (!byDay.has(day)) {
            byDay.set(day, []);
        }

        byDay.get(day).push(log);
    }

    return [...byDay.entries()];
}

export function nextBeforeId(logs) {
    if (!Array.isArray(logs) || logs.length === 0) {
        return null;
    }

    return logs[logs.length - 1]?.id ?? null;
}

export function buildAuditQuery({ limit = 50, beforeId = null, action = '', subjectType = '' } = {}) {
    const params = new URLSearchParams();
    params.set('limit', String(limit));

    if (beforeId !== null && beforeId !== undefined && beforeId !== '') {
        params.set('before_id', String(beforeId));
    }

    if (action) {
        params.set('action', action);
    }

    if (subjectType) {
        params.set('subject_type', subjectType);
    }

    return `?${params.toString()}`;
}
