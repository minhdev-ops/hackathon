import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import PageLayout from '../components/layout/PageLayout';
import { Button } from '../components/ui/Button';
import { StatusBadge } from '../components/ui/StatusBadge';
import { CountdownTimer } from '../components/ui/CountdownTimer';
import { SkeletonLine } from '../components/ui/Skeleton';
import { useToast } from '../components/ui/Toast';
import { showTask } from '../api/tasks';
import ConsentReview from './ConsentReview';

const POLL_INTERVAL = 1000;
const POLLABLE_STATUSES = new Set([
    'proposing',
    'proposal_ready',
    'awaiting_approval',
    'running',
]);

export default function TaskDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { toast } = useToast();
    const requestId = useRef(0);
    const [task, setTask] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [polling, setPolling] = useState(false);

    const load = useCallback(async () => {
        if (!id) {
            setError('Thiếu mã task.');
            setLoading(false);
            return;
        }
        const current = ++requestId.current;
        setLoading(true);
        setError(null);
        try {
            const data = await showTask(id);
            if (current !== requestId.current) {
                return;
            }
            setTask(data);
        } catch (err) {
            if (current !== requestId.current) {
                return;
            }
            setError(err?.message || 'Không tải được chi tiết task.');
            toast({
                title: 'Lỗi',
                description: err?.message || 'Không tải được chi tiết task.',
                type: 'danger',
            });
        } finally {
            if (current === requestId.current) {
                setLoading(false);
            }
        }
    }, [id, toast]);

    useEffect(() => {
        load();

        let timer = null;
        const shouldPoll = task && POLLABLE_STATUSES.has(task.status);
        if (shouldPoll && !polling) {
            setPolling(true);
            timer = setInterval(() => {
                void load();
            }, POLL_INTERVAL);
        } else if (!shouldPoll && polling) {
            setPolling(false);
        }

        return () => {
            if (timer) {
                clearInterval(timer);
            }
            setPolling(false);
        };
    }, [load, task, polling]);

    if (loading) {
        return (
            <PageLayout title="Chi tiết task" subtitle="Trạng thái, đề xuất, log và kết quả của agent." showHomeLink={false}>
                <div className="flex flex-col items-center justify-center py-12">
                    <SkeletonLine count={3} className="w-72" />
                    <p className="mt-4 text-sm text-muted">Đang tải chi tiết task…</p>
                </div>
            </PageLayout>
        );
    }

    if (error || !task) {
        return (
            <PageLayout title="Chi tiết task" subtitle="Trạng thái, đề xuất, log và kết quả của agent." showHomeLink={false}>
                <div className="rounded-lg border border-danger/20 bg-danger/5 p-4 text-sm text-danger" role="alert">
                    {error || 'Không tìm thấy task.'}
                </div>
                <div className="mt-6">
                    <Button variant="secondary" onClick={() => navigate('/playbooks')}>
                        Về danh sách playbook
                    </Button>
                </div>
            </PageLayout>
        );
    }

    const grant = task.grant;
    const proposal = task.proposal ?? {};
    const required = (proposal.required ?? []).join(', ') || '—';
    const optional = (proposal.optional ?? []).join(', ') || '—';
    const rationale = proposal.rationale ?? '—';

    return (
        <PageLayout title={`Task #${task.id}`} subtitle="Trạng thái, đề xuất, log và kết quả của agent." showHomeLink={false}>
            <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                <section className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold">{task.instruction}</h2>
                            <p className="mt-1 text-xs text-muted">
                                #{task.id} · {task.playbook?.name ?? '—'} · {task.agent?.name ?? '—'}
                            </p>
                        </div>
                        <StatusBadge variant={task.status}>
                            {task.status.replace(/_/g, ' ')}
                        </StatusBadge>
                    </div>

                    {task.playbook && (
                        <div className="rounded-lg border border-border bg-surface p-4">
                            <h3 className="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Playbook</h3>
                            <p className="text-sm text-text">{task.playbook.name}</p>
                            <p className="mt-1 text-xs text-muted">Slug: {task.playbook.slug}</p>
                        </div>
                    )}

                    <div className="rounded-lg border border-border bg-surface p-4">
                        <h3 className="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Đề xuất của agent</h3>
                        <div className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div className="text-muted text-xs">Bắt buộc</div>
                                <div className="text-text font-medium">{required}</div>
                            </div>
                            <div>
                                <div className="text-muted text-xs">Tuỳ chọn</div>
                                <div className="text-text font-medium">{optional}</div>
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-muted text-xs mb-1">Lý do</div>
                            <p className="text-sm text-text whitespace-pre-wrap">{rationale}</p>
                        </div>
                    </div>

                    {grant && (
                        <div className="rounded-lg border border-border bg-surface p-4">
                            <h3 className="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Grant</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted">Trạng thái</span>
                                    <span className="font-mono">{grant.status}</span>
                                </div>
                                {grant.expires_at && (
                                    <div className="flex justify-between">
                                        <span className="text-muted">Còn lại</span>
                                        <CountdownTimer seconds={grant.remaining_seconds ?? 0} />
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span className="text-muted">Mã grant</span>
                                    <span className="font-mono text-xs text-muted truncate">{grant.id}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="rounded-lg border border-border bg-surface p-4">
                        <h3 className="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Nhật ký agent</h3>
                        <div className="max-h-64 overflow-y-auto space-y-2 text-xs font-mono text-muted">
                            {(task.log ?? []).map((entry, i) => (
                                <div key={i} className="truncate">
                                    {JSON.stringify(entry)}
                                </div>
                            ))}
                            {task.log?.length === 0 && <span className="text-muted">Chưa có sự kiện nào.</span>}
                        </div>
                    </div>

                    {task.result && (
                        <div className="rounded-lg border border-border bg-surface p-4">
                            <h3 className="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Kết quả</h3>
                            <pre className="text-xs font-mono text-muted whitespace-pre-wrap break-words">
                                {JSON.stringify(task.result, null, 2)}
                            </pre>
                        </div>
                    )}

                    {grant && grant.status === 'pending' && (
                        <div className="mt-4">
                            <p className="text-xs text-muted mb-2">
                                Owner đang duyệt grant. Bạn có thể mở màn duyệt bên dưới.
                            </p>
                        </div>
                    )}
                </section>

                <aside className="space-y-4">
                    {grant && (
                        <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                            <h2 className="text-sm font-bold mb-3">Duyệt chia sẻ dữ liệu</h2>
                            <ConsentReview grantId={grant.id} />
                        </section>
                    )}

                    {!grant && task.status === 'proposal_ready' && (
                        <div className="rounded-xl border border-dashed border-border bg-surface p-8 text-center">
                            <p className="text-sm text-muted">Đang chờ tạo grant từ đề xuất.</p>
                        </div>
                    )}
                </aside>
            </div>
        </PageLayout>
    );
}
