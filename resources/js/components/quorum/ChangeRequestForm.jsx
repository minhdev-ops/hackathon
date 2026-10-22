import React, { useEffect, useMemo, useState } from 'react';

const TYPE_ROLE_GRANT = 'role_grant';
const TYPE_ROLE_REVOKE = 'role_revoke';
const TYPE_PLAYBOOK_UPDATE = 'playbook_update';
const TYPE_AGENT_ALLOWLIST_UPDATE = 'agent_allowlist_update';
const TYPE_QUORUM_THRESHOLD_UPDATE = 'quorum_threshold_update';

const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100';
const labelClass = 'mb-1 block text-xs font-medium text-slate-600';

function listToText(list) {
    return (list ?? []).join(', ');
}

function textToList(text) {
    return text
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
}

function playbookFields(playbook) {
    return {
        required: listToText(playbook?.required_doc_types),
        optional: listToText(playbook?.optional_doc_types),
        forbidden: listToText(playbook?.forbidden_doc_types),
        recipient: playbook?.recipient_label ?? '',
        defaultTtl: String(playbook?.default_ttl_minutes ?? ''),
        maxTtl: String(playbook?.max_ttl_minutes ?? ''),
    };
}

export default function ChangeRequestForm({ meta, onCreate }) {
    const [type, setType] = useState(TYPE_ROLE_GRANT);
    const [email, setEmail] = useState(meta.users?.[0]?.email ?? '');
    const [role, setRole] = useState('admin');
    const [playbookSlug, setPlaybookSlug] = useState(meta.playbooks?.[0]?.slug ?? '');
    const [playbookDraft, setPlaybookDraft] = useState(() =>
        playbookFields(meta.playbooks?.[0]),
    );
    const [agentClientId, setAgentClientId] = useState(meta.agents?.[0]?.client_id ?? '');
    const [allowedPlaybooks, setAllowedPlaybooks] = useState(
        () => meta.agents?.[0]?.allowed_playbooks ?? [],
    );
    const [threshold, setThreshold] = useState(String(meta.threshold ?? 2));
    const [busy, setBusy] = useState(false);
    const [localError, setLocalError] = useState(null);

    const selectedPlaybook = useMemo(
        () => meta.playbooks?.find((playbook) => playbook.slug === playbookSlug),
        [meta.playbooks, playbookSlug],
    );

    const selectedAgent = useMemo(
        () => meta.agents?.find((agent) => agent.client_id === agentClientId),
        [meta.agents, agentClientId],
    );

    // Chỉ reset khi đổi lựa chọn, không reset mỗi lần polling 5 giây.
    useEffect(() => {
        setPlaybookDraft(playbookFields(selectedPlaybook));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [playbookSlug]);

    useEffect(() => {
        setAllowedPlaybooks(selectedAgent?.allowed_playbooks ?? []);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [agentClientId]);

    useEffect(() => {
        if (type === TYPE_ROLE_REVOKE && role === 'owner') {
            setRole('admin');
        }
    }, [type, role]);

    const togglePlaybook = (slug) => {
        setAllowedPlaybooks((current) =>
            current.includes(slug)
                ? current.filter((item) => item !== slug)
                : [...current, slug],
        );
    };

    const buildPayload = () => {
        switch (type) {
            case TYPE_ROLE_GRANT:
            case TYPE_ROLE_REVOKE:
                return { email, role };
            case TYPE_PLAYBOOK_UPDATE: {
                const payload = { slug: playbookSlug };
                const base = playbookFields(selectedPlaybook);

                if (playbookDraft.required !== base.required) {
                    payload.required_doc_types = textToList(playbookDraft.required);
                }
                if (playbookDraft.optional !== base.optional) {
                    payload.optional_doc_types = textToList(playbookDraft.optional);
                }
                if (playbookDraft.forbidden !== base.forbidden) {
                    payload.forbidden_doc_types = textToList(playbookDraft.forbidden);
                }
                if (playbookDraft.recipient !== base.recipient) {
                    payload.recipient_label = playbookDraft.recipient;
                }
                if (playbookDraft.defaultTtl !== base.defaultTtl) {
                    payload.default_ttl_minutes = Number(playbookDraft.defaultTtl);
                }
                if (playbookDraft.maxTtl !== base.maxTtl) {
                    payload.max_ttl_minutes = Number(playbookDraft.maxTtl);
                }

                return payload;
            }
            case TYPE_AGENT_ALLOWLIST_UPDATE:
                return { client_id: agentClientId, allowed_playbooks: allowedPlaybooks };
            case TYPE_QUORUM_THRESHOLD_UPDATE:
                return { required_approvals: Number(threshold) };
            default:
                return {};
        }
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        setLocalError(null);
        setBusy(true);

        try {
            await onCreate(type, buildPayload());
        } catch (error) {
            setLocalError(error?.message ?? 'Không tạo được Change Request.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4">
            <h2 className="text-sm font-semibold">Tạo Change Request</h2>
            <p className="mt-1 text-xs text-slate-500">
                CR được lưu ở trạng thái <span className="font-medium">pending</span> và cần{' '}
                {meta.threshold} admin duyệt.
            </p>

            <form onSubmit={handleSubmit} className="mt-4 space-y-3">
                <div>
                    <label className={labelClass} htmlFor="cr-type">
                        Loại thay đổi
                    </label>
                    <select
                        id="cr-type"
                        value={type}
                        onChange={(event) => setType(event.target.value)}
                        className={inputClass}
                    >
                        {meta.types.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                </div>

                {(type === TYPE_ROLE_GRANT || type === TYPE_ROLE_REVOKE) && (
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2">
                            <label className={labelClass} htmlFor="cr-email">
                                Tài khoản
                            </label>
                            <select
                                id="cr-email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                className={inputClass}
                            >
                                {meta.users.map((user) => (
                                    <option key={user.email} value={user.email}>
                                        {user.name} · {user.email} ({user.role})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="col-span-2">
                            <label className={labelClass} htmlFor="cr-role">
                                Vai trò
                            </label>
                            <select
                                id="cr-role"
                                value={role}
                                onChange={(event) => setRole(event.target.value)}
                                className={inputClass}
                            >
                                {(type === TYPE_ROLE_GRANT
                                    ? ['admin', 'agent', 'owner']
                                    : ['admin', 'agent']
                                ).map((roleOption) => (
                                    <option key={roleOption} value={roleOption}>
                                        {roleOption}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}

                {type === TYPE_PLAYBOOK_UPDATE && (
                    <div className="space-y-3">
                        <div>
                            <label className={labelClass} htmlFor="cr-playbook">
                                Playbook
                            </label>
                            <select
                                id="cr-playbook"
                                value={playbookSlug}
                                onChange={(event) => setPlaybookSlug(event.target.value)}
                                className={inputClass}
                            >
                                {meta.playbooks.map((playbook) => (
                                    <option key={playbook.slug} value={playbook.slug}>
                                        {playbook.name} ({playbook.slug}) · v{playbook.version}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass} htmlFor="cr-default-ttl">
                                    TTL mặc định (phút)
                                </label>
                                <input
                                    id="cr-default-ttl"
                                    type="number"
                                    min="1"
                                    value={playbookDraft.defaultTtl}
                                    onChange={(event) =>
                                        setPlaybookDraft((draft) => ({
                                            ...draft,
                                            defaultTtl: event.target.value,
                                        }))
                                    }
                                    className={inputClass}
                                />
                            </div>
                            <div>
                                <label className={labelClass} htmlFor="cr-max-ttl">
                                    TTL tối đa (phút)
                                </label>
                                <input
                                    id="cr-max-ttl"
                                    type="number"
                                    min="1"
                                    value={playbookDraft.maxTtl}
                                    onChange={(event) =>
                                        setPlaybookDraft((draft) => ({
                                            ...draft,
                                            maxTtl: event.target.value,
                                        }))
                                    }
                                    className={inputClass}
                                />
                            </div>
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="cr-recipient">
                                Nhãn bên nhận
                            </label>
                            <input
                                id="cr-recipient"
                                type="text"
                                value={playbookDraft.recipient}
                                onChange={(event) =>
                                    setPlaybookDraft((draft) => ({
                                        ...draft,
                                        recipient: event.target.value,
                                    }))
                                }
                                className={inputClass}
                            />
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="cr-required">
                                Tài liệu bắt buộc (phân cách bằng dấu phẩy)
                            </label>
                            <input
                                id="cr-required"
                                type="text"
                                value={playbookDraft.required}
                                onChange={(event) =>
                                    setPlaybookDraft((draft) => ({
                                        ...draft,
                                        required: event.target.value,
                                    }))
                                }
                                className={inputClass}
                            />
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="cr-optional">
                                Tài liệu tuỳ chọn
                            </label>
                            <input
                                id="cr-optional"
                                type="text"
                                value={playbookDraft.optional}
                                onChange={(event) =>
                                    setPlaybookDraft((draft) => ({
                                        ...draft,
                                        optional: event.target.value,
                                    }))
                                }
                                className={inputClass}
                            />
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="cr-forbidden">
                                Tài liệu bị cấm
                            </label>
                            <input
                                id="cr-forbidden"
                                type="text"
                                value={playbookDraft.forbidden}
                                onChange={(event) =>
                                    setPlaybookDraft((draft) => ({
                                        ...draft,
                                        forbidden: event.target.value,
                                    }))
                                }
                                className={inputClass}
                            />
                        </div>

                        <p className="text-xs text-slate-500">
                            Chỉ các trường bạn thay đổi mới được gửi trong payload.
                        </p>
                    </div>
                )}

                {type === TYPE_AGENT_ALLOWLIST_UPDATE && (
                    <div className="space-y-3">
                        <div>
                            <label className={labelClass} htmlFor="cr-agent">
                                Agent
                            </label>
                            <select
                                id="cr-agent"
                                value={agentClientId}
                                onChange={(event) => setAgentClientId(event.target.value)}
                                className={inputClass}
                            >
                                {meta.agents.map((agent) => (
                                    <option key={agent.client_id} value={agent.client_id}>
                                        {agent.name} ({agent.client_id})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <p className={labelClass}>Playbook được phép</p>
                            <div className="space-y-2 rounded-lg border border-slate-200 p-3">
                                {meta.playbooks.map((playbook) => (
                                    <label
                                        key={playbook.slug}
                                        className="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={allowedPlaybooks.includes(playbook.slug)}
                                            onChange={() => togglePlaybook(playbook.slug)}
                                            className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                        />
                                        {playbook.name} ({playbook.slug})
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {type === TYPE_QUORUM_THRESHOLD_UPDATE && (
                    <div>
                        <label className={labelClass} htmlFor="cr-threshold">
                            Số admin cần duyệt
                        </label>
                        <input
                            id="cr-threshold"
                            type="number"
                            min="1"
                            max="5"
                            value={threshold}
                            onChange={(event) => setThreshold(event.target.value)}
                            className={inputClass}
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            Ngưỡng mới áp dụng cho các CR tạo sau khi CR này được thực thi.
                        </p>
                    </div>
                )}

                {localError && <p className="text-xs text-rose-600">{localError}</p>}

                <button
                    type="submit"
                    disabled={busy}
                    className="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
                >
                    {busy ? 'Đang gửi…' : 'Gửi Change Request'}
                </button>
            </form>
        </section>
    );
}
