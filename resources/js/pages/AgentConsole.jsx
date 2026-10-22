import { useCallback, useEffect, useRef, useState } from 'react';
import PageLayout from '../components/layout/PageLayout';
import { Button } from '../components/ui/Button';
import { StatusBadge } from '../components/ui/StatusBadge';
import { SkeletonLine } from '../components/ui/Skeleton';
import { useToast } from '../components/ui/Toast';
import { api } from '../api/client';

const POLL_INTERVAL = 3000;

export default function AgentConsole() {
    const { toast } = useToast();
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [notice, setNotice] = useState(null);
    const [outOfScopeResult, setOutOfScopeResult] = useState(null);
    const requestId = useRef(0);

    const load = useCallback(async () => {
        const current = ++requestId.current;
        setLoading(true);
        setError(null);
        try {
            const response = await api.get('/audit?limit=50');
            if (current !== requestId.current) {
                return;
            }
            setLogs(response.data.data ?? []);
        } catch (err) {
            if (current !== requestId.current) {
                return;
            }
            setError(err?.message || 'Không tải được nhật ký agent.');
            toast({
                title: 'Lỗi',
                description: err?.message || 'Không tải được nhật ký agent.',
                type: 'danger',
            });
        } finally {
            if (current === requestId.current) {
                setLoading(false);
            }
        }
    }, [toast]);

    useEffect(() => {
        load();
        const timer = setInterval(load, POLL_INTERVAL);
        return () => clearInterval(timer);
    }, [load]);

    const handleOutOfScope = async () => {
        setNotice(null);
        setOutOfScopeResult(null);
        try {
            await api.post('/agent/documents/request', {
                doc_type: 'medical_summary',
                reason: 'Agent muốn xem toàn bộ hồ sơ khám bệnh.',
            });
            setNotice('Gọi thành công (không mong đợi).');
        } catch (err) {
            const code = err?.response?.data?.error?.code;
            const message = err?.response?.data?.error?.message ?? err?.message;
            setOutOfScopeResult({ code, message });
            if (code === 'OUT_OF_SCOPE') {
                setNotice('Đã chặn đúng quy tắc: 403 OUT_OF_SCOPE.');
                toast({
                    title: 'Đã chặn truy cập ngoài phạm vi',
                    description: 'Agent không thể yêu cầu tài liệu ngoài playbook.',
                    type: 'success',
                });
            } else {
                setNotice(`Kết quả: ${code ?? 'unknown'} — ${message}`);
                toast({
                    title: 'Lỗi không mong đợi',
                    description: message,
                    type: 'danger',
                });
            }
        }
    };

    const agentLogs = logs.filter(
        (entry) =>
            entry.subject_type === 'agent_task' ||
            entry.action?.includes('agent') ||
            entry.action?.includes('grant') ||
            entry.action?.includes('document'),
    );

    return (
        <PageLayout
            title="Agent Console"
            subtitle="Log thời gian thực của agent và kiểm soát quyền truy cập tài liệu."
            showHomeLink={false}
        >
            {error && (
                <div className="rounded-lg border border-danger/20 bg-danger/5 p-4 text-sm text-danger" role="alert">
                    {error}
                </div>
            )}

            {notice && (
                <div
                    className={`rounded-lg border p-3 text-sm ${
                        notice.includes('chặn') || notice.includes('OUT_OF_SCOPE')
                            ? 'border-success/20 bg-success/5 text-success'
                            : 'border-info/20 bg-info/5 text-info'
                    }`}
                    role="status"
                >
                    {notice}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                <section className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Hoạt động agent</h2>
                        <Button variant="secondary" size="sm" onClick={handleOutOfScope}>
                            Xin tài liệu ngoài phạm vi
                        </Button>
                    </div>
                    <p className="text-xs text-muted">
                        Nút trên mô phỏng agent yêu cầu một loại tài liệu không nằm trong phạm vi
                        playbook — server phải trả 403 OUT_OF_SCOPE.
                    </p>

                    {outOfScopeResult && (
                        <div className="mt-3 rounded-lg border border-border bg-elevated p-3 text-xs font-mono">
                            <div className="flex justify-between mb-1">
                                <span className="text-muted">Mã</span>
                                <span className="text-text">{outOfScopeResult.code ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted">Thông báo</span>
                                <span className="text-text">{outOfScopeResult.message ?? '—'}</span>
                            </div>
                        </div>
                    )}

                    {loading && (
                        <div className="flex flex-col items-center justify-center py-12">
                            <SkeletonLine count={3} className="w-72" />
                            <p className="mt-4 text-sm text-muted">Đang tải…</p>
                        </div>
                    )}

                    {!loading && agentLogs.length === 0 && (
                        <div className="rounded-xl border border-dashed border-border bg-surface p-12 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-elevated mb-4">
                                <svg className="h-8 w-8 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 className="text-sm font-semibold text-text">Chưa có sự kiện agent nào.</h3>
                            <p className="mt-1 text-xs text-muted">Hoạt động agent sẽ xuất hiện ở đây.</p>
                        </div>
                    )}

                    {!loading && agentLogs.length > 0 && (
                        <div className="rounded-xl border border-border bg-surface overflow-hidden shadow-sm">
                            <table className="w-full text-left text-xs divide-y divide-border" role="grid" aria-label="Hoạt động agent">
                                <thead>
                                    <tr className="border-b border-border bg-elevated">
                                        <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Thời gian</th>
                                        <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Hành động</th>
                                        <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Đối tượng</th>
                                        <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Chi tiết</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {agentLogs.map((entry) => (
                                        <tr key={entry.id} className="hover:bg-elevated transition" role="row">
                                            <td className="p-3 font-mono text-muted" scope="row">
                                                {new Date(entry.created_at).toLocaleString('vi-VN', {
                                                    hour12: false,
                                                })}
                                            </td>
                                            <td className="p-3 text-text">
                                                <StatusBadge
                                                    variant={
                                                        entry.action?.includes('denied') ||
                                                        entry.action?.includes('rejected') ||
                                                        entry.action?.includes('out_of_scope')
                                                            ? 'denied'
                                                            : 'running'
                                                    }
                                                >
                                                    {entry.action}
                                                </StatusBadge>
                                            </td>
                                            <td className="p-3 font-mono text-muted">
                                                {entry.subject_type ?? '—'}
                                            </td>
                                            <td className="p-3 text-muted truncate max-w-[200px]">
                                                {entry.meta ? JSON.stringify(entry.meta) : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <aside className="space-y-4">
                    <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <h2 className="text-sm font-bold mb-3">Quy tắc phạm vi</h2>
                        <ul className="space-y-2 text-sm text-muted">
                            <li>• Agent chỉ mở tài liệu trong grant đã duyệt.</li>
                            <li>• Loại doc_type ngoài playbook → 422 OUT_OF_SCOPE.</li>
                            <li>• K_g tồn tại trong thời hạn grant, sau đó bị xoá.</li>
                            <li>• Thu hồi grant → agent dừng ngay.</li>
                        </ul>
                    </section>

                    <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <h2 className="text-sm font-bold mb-3">Thao tác nhanh</h2>
                        <div className="space-y-2">
                            <Button variant="secondary" size="sm" className="w-full text-left" onClick={() => navigate('/timeline')}>
                                Xem nhật ký đầy đủ
                            </Button>
                            <Button variant="secondary" size="sm" className="w-full text-left" disabled>
                                Quản lý quorum
                            </Button>
                        </div>
                    </section>
                </aside>
            </div>
        </PageLayout>
    );
}
