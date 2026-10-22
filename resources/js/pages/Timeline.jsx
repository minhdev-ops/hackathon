import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import {
    actionTitle,
    actorLabel,
    buildAuditQuery,
    formatDay,
    formatTime,
    groupLogsByDay,
    nextBeforeId,
    safeMetaSummary,
    subjectLabel,
} from '../lib/auditTimeline';

const PAGE_SIZE = 50;

const ACTION_OPTIONS = [
    { value: '', label: 'Tất cả hành động' },
    { value: 'task.created', label: 'Tạo việc cho agent' },
    { value: 'proposal.generated', label: 'Agent tạo đề xuất' },
    { value: 'grant.approved', label: 'Duyệt grant' },
    { value: 'grant.denied', label: 'Từ chối grant' },
    { value: 'grant.revoked', label: 'Thu hồi grant' },
    { value: 'grant.expired', label: 'Grant hết hạn' },
    { value: 'grant.opened', label: 'Agent mở grant' },
    { value: 'grant.open_denied', label: 'Agent bị chặn mở grant' },
    { value: 'document.uploaded', label: 'Tải tài liệu lên' },
    { value: 'document.viewed', label: 'Xem tài liệu' },
    { value: 'document.deleted', label: 'Xoá tài liệu' },
    { value: 'agent.document_request.out_of_scope', label: 'Agent xin tài liệu ngoài phạm vi' },
    { value: 'change_request.created', label: 'Tạo yêu cầu quorum' },
    { value: 'change_request.approved', label: 'Duyệt yêu cầu quorum' },
    { value: 'change_request.vote_rejected', label: 'Từ chối yêu cầu quorum' },
    { value: 'change_request.executed', label: 'Thực thi yêu cầu quorum' },
    { value: 'change_request.rejected', label: 'Yêu cầu quorum bị từ chối' },
    { value: 'role.granted', label: 'Cấp vai trò' },
    { value: 'role.revoked', label: 'Thu hồi vai trò' },
    { value: 'playbook.updated', label: 'Cập nhật playbook' },
    { value: 'agent.allowlist_updated', label: 'Cập nhật quyền agent' },
    { value: 'quorum.threshold_updated', label: 'Cập nhật ngưỡng quorum' },
];

const SUBJECT_OPTIONS = [
    { value: '', label: 'Tất cả đối tượng' },
    { value: 'document', label: 'Tài liệu' },
    { value: 'agent_task', label: 'Việc agent' },
    { value: 'grant', label: 'Grant' },
    { value: 'change_request', label: 'Yêu cầu quorum' },
    { value: 'user', label: 'Người dùng' },
    { value: 'playbook', label: 'Playbook' },
    { value: 'agent', label: 'Agent' },
    { value: 'setting', label: 'Cấu hình' },
];

function apiErrorMessage(error, fallback) {
    if (error?.response?.status === 401) {
        return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    }

    const apiError = error?.response?.data?.error;

    if (apiError?.code && apiError?.message) {
        return `${apiError.code}: ${apiError.message}`;
    }

    return apiError?.message || fallback;
}

export default function Timeline() {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState(null);
    const [verifying, setVerifying] = useState(false);
    const [verification, setVerification] = useState(null);
    const [action, setAction] = useState('');
    const [subjectType, setSubjectType] = useState('');
    const [hasMore, setHasMore] = useState(false);
    const requestId = useRef(0);

    useEffect(() => {
        let active = true;
        const currentRequest = ++requestId.current;

        setLoading(true);
        setLoadingMore(false);
        setError(null);
        setLogs([]);
        setHasMore(false);

        api.get(`/audit${buildAuditQuery({ limit: PAGE_SIZE, action, subjectType })}`)
            .then((response) => {
                if (!active || currentRequest !== requestId.current) {
                    return;
                }

                const page = response.data.data ?? [];
                setLogs(page);
                setHasMore(page.length === PAGE_SIZE);
            })
            .catch((err) => {
                if (active && currentRequest === requestId.current) {
                    setError(apiErrorMessage(err, 'Không tải được nhật ký.'));
                }
            })
            .finally(() => {
                if (active && currentRequest === requestId.current) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [action, subjectType]);

    const groups = useMemo(() => groupLogsByDay(logs), [logs]);

    const loadMore = async () => {
        const beforeId = nextBeforeId(logs);

        if (beforeId === null || loadingMore) {
            return;
        }

        const currentRequest = requestId.current;

        setLoadingMore(true);
        setError(null);

        try {
            const response = await api.get(
                `/audit${buildAuditQuery({ limit: PAGE_SIZE, beforeId, action, subjectType })}`,
            );
            const page = response.data.data ?? [];

            if (currentRequest !== requestId.current) {
                return;
            }

            setLogs((previous) => {
                const seen = new Set(previous.map((entry) => entry.id));
                const fresh = page.filter((entry) => !seen.has(entry.id));

                return [...previous, ...fresh];
            });
            setHasMore(page.length === PAGE_SIZE);
        } catch (err) {
            if (currentRequest === requestId.current) {
                setError(apiErrorMessage(err, 'Không tải thêm được nhật ký.'));
            }
        } finally {
            if (currentRequest === requestId.current) {
                setLoadingMore(false);
            }
        }
    };

    const verify = async () => {
        setVerifying(true);
        setVerification(null);

        try {
            const response = await api.get('/audit/verify');
            setVerification(response.data.data);
        } catch (err) {
            setVerification({
                valid: false,
                broken_at: null,
                reason: apiErrorMessage(err, 'Không xác minh được chuỗi.'),
            });
        } finally {
            setVerifying(false);
        }
    };

    return (
        <div className="min-h-screen bg-[#FDFDFC] p-4 text-[#1b1b18] lg:p-8">
            <div className="mx-auto max-w-4xl">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Nhật ký hoạt động</h1>
                        <p className="mt-1 text-sm text-[#706f6c]">
                            Chuỗi sự kiện append-only. Owner thấy sự kiện liên quan; admin thấy toàn bộ.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={verify}
                            disabled={verifying}
                            className="rounded border border-[#1b1b18] px-4 py-2 text-sm font-medium hover:bg-[#f3f3f2] disabled:opacity-50"
                        >
                            {verifying ? 'Đang xác minh…' : 'Xác minh chuỗi'}
                        </button>
                        <Link
                            to="/"
                            className="rounded border border-[#d6d6d2] px-4 py-2 text-sm font-medium text-[#706f6c] hover:bg-[#f3f3f2]"
                        >
                            ← Trang chủ
                        </Link>
                    </div>
                </div>

                <div className="mb-6 grid gap-3 sm:grid-cols-2">
                    <label className="text-xs font-medium text-[#706f6c]">
                        Hành động
                        <select
                            value={action}
                            onChange={(event) => setAction(event.target.value)}
                            className="mt-1 w-full rounded border border-[#d6d6d2] bg-white px-3 py-2 text-sm text-[#1b1b18]"
                        >
                            {ACTION_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-xs font-medium text-[#706f6c]">
                        Đối tượng
                        <select
                            value={subjectType}
                            onChange={(event) => setSubjectType(event.target.value)}
                            className="mt-1 w-full rounded border border-[#d6d6d2] bg-white px-3 py-2 text-sm text-[#1b1b18]"
                        >
                            {SUBJECT_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                {verification && (
                    <div
                        role="status"
                        className={`mb-6 rounded-lg border p-4 text-sm ${
                            verification.valid
                                ? 'border-[#86efac] bg-[#f0fdf4] text-[#15803d]'
                                : 'border-[#fecaca] bg-[#fef2f2] text-[#b91c1c]'
                        }`}
                    >
                        {verification.valid ? (
                            <span>Chuỗi hợp lệ ({verification.checked} sự kiện).</span>
                        ) : (
                            <span>
                                Chuỗi bị đứt{verification.broken_at ? ` tại #${verification.broken_at}` : ''}
                                {verification.reason ? ` (${verification.reason})` : ''}.
                            </span>
                        )}
                    </div>
                )}

                {loading && <p className="text-sm text-[#706f6c]">Đang tải nhật ký…</p>}

                {!loading && error && (
                    <div className="rounded-lg border border-[#fecaca] bg-[#fef2f2] p-4 text-sm text-[#b91c1c]">
                        {error}
                    </div>
                )}

                {!loading && !error && logs.length === 0 && (
                    <div className="rounded-lg border border-[#e3e3e0] bg-white p-6 text-sm text-[#706f6c]">
                        Chưa có sự kiện nào phù hợp.
                    </div>
                )}

                <div className="space-y-8">
                    {groups.map(([day, entries]) => (
                        <section key={day}>
                            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-[#706f6c]">
                                {day}
                            </h2>
                            <div className="space-y-2">
                                {entries.map((entry) => {
                                    const summary = entry.action === 'grant.opened'
                                        ? ''
                                        : safeMetaSummary(entry.meta);

                                    return (
                                        <article
                                            key={entry.id}
                                            className="rounded-lg border border-[#e3e3e0] bg-white p-4 shadow-sm"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <h3 className="text-sm font-semibold">
                                                        {actionTitle(entry)}
                                                    </h3>
                                                    <p className="mt-1 text-xs text-[#706f6c]">
                                                        {actorLabel(entry)} · {subjectLabel(entry)}
                                                    </p>
                                                </div>
                                                {entry.action !== 'grant.opened' && (
                                                    <time className="text-xs text-[#706f6c]">
                                                        {formatTime(entry.created_at)}
                                                    </time>
                                                )}
                                            </div>
                                            {summary && (
                                                <p className="mt-2 break-words text-xs text-[#706f6c]">
                                                    {summary}
                                                </p>
                                            )}
                                        </article>
                                    );
                                })}
                            </div>
                        </section>
                    ))}
                </div>

                {!loading && !error && hasMore && (
                    <div className="mt-6 text-center">
                        <button
                            type="button"
                            onClick={loadMore}
                            disabled={loadingMore}
                            className="rounded border border-[#d6d6d2] px-4 py-2 text-sm font-medium text-[#1b1b18] hover:bg-[#f3f3f2] disabled:opacity-50"
                        >
                            {loadingMore ? 'Đang tải thêm…' : 'Tải thêm'}
                        </button>
                    </div>
                )}

                <p className="mt-6 text-xs text-[#706f6c]">
                    {logs.length > 0 && `Đang hiển thị ${logs.length} sự kiện gần nhất. `}
                    Xác minh chuỗi kiểm tra toàn bộ nhật ký, kể cả sự kiện của người dùng khác.
                </p>
            </div>
        </div>
    );
}
