import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import PageLayout from '../components/layout/PageLayout';
import { Button } from '../components/ui/Button';
import { SkeletonLine } from '../components/ui/Skeleton';
import { useToast } from '../components/ui/Toast';
import { listPlaybooks } from '../api/playbooks';

export default function Playbooks() {
    const navigate = useNavigate();
    const { toast } = useToast();
    const [playbooks, setPlaybooks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const data = await listPlaybooks();
            setPlaybooks(data);
        } catch (err) {
            setError(err?.message || 'Không tải được danh sách playbook.');
            toast({
                title: 'Lỗi',
                description: err?.message || 'Không tải được danh sách playbook.',
                type: 'danger',
            });
        } finally {
            setLoading(false);
        }
    }, [toast]);

    useEffect(() => {
        load();
    }, [load]);

    const handleCreateTask = (slug) => {
        navigate(`/tasks/new?playbook=${encodeURIComponent(slug)}`);
    };

    return (
        <PageLayout
            title="Chọn lĩnh vực"
            subtitle="Mỗi playbook định nghĩa quy tắc phạm vi: tài liệu bắt buộc, tài liệu tùy chọn, thời hạn tối đa và bên nhận hồ sơ."
            showHomeLink={false}
        >
            {error && (
                <div className="rounded-lg border border-danger/20 bg-danger/5 p-4 text-sm text-danger" role="alert">
                    {error}
                </div>
            )}

            {loading && (
                <div className="flex flex-col items-center justify-center py-12">
                    <SkeletonLine count={3} className="w-72" />
                    <p className="mt-4 text-sm text-muted">Đang tải playbook…</p>
                </div>
            )}

            {!loading && playbooks.length === 0 && (
                <div className="rounded-xl border border-dashed border-border bg-surface p-16 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-elevated mb-4">
                        <svg className="h-8 w-8 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </div>
                    <h3 className="text-sm font-semibold text-text">Chưa có playbook nào được seed.</h3>
                    <p className="mt-1 text-xs text-muted">Vui lòng seed dữ liệu playbook trước.</p>
                </div>
            )}

            {!loading && playbooks.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {playbooks.map((pb) => (
                        <div
                            key={pb.id}
                            className="rounded-xl border border-border bg-surface p-5 shadow-sm hover:shadow-lg transition group"
                        >
                            <div className="flex items-start justify-between mb-2">
                                <h3 className="font-semibold text-text">{pb.name}</h3>
                                <span className="rounded-md border border-border bg-elevated px-2 py-0.5 text-[11px] font-mono text-muted">
                                    {pb.slug}
                                </span>
                            </div>
                            <p className="mb-4 text-sm text-muted line-clamp-2">{pb.description}</p>

                            <div className="mb-4">
                                <span className="text-xs font-semibold text-muted uppercase tracking-wider mr-2">Bắt buộc:</span>
                                {pb.required_doc_types.map((t) => (
                                    <span key={t} className="inline-block rounded-md border border-border bg-elevated px-2 py-0.5 text-[11px] font-mono text-muted mr-1.5 mb-1">
                                        {t}
                                    </span>
                                ))}
                            </div>
                            {pb.optional_doc_types.length > 0 && (
                                <div className="mb-4">
                                    <span className="text-xs font-semibold text-muted uppercase tracking-wider mr-2">Tuỳ chọn:</span>
                                    {pb.optional_doc_types.map((t) => (
                                        <span key={t} className="inline-block rounded-md border border-border bg-elevated px-2 py-0.5 text-[11px] font-mono text-muted mr-1.5 mb-1">
                                            {t}
                                        </span>
                                    ))}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-2 mb-4 text-xs text-muted">
                                <div>
                                    <div className="text-muted">Bên nhận</div>
                                    <div className="text-text font-medium">{pb.recipient_label}</div>
                                </div>
                                <div>
                                    <div className="text-muted">TTL tối đa</div>
                                    <div className="text-text font-medium">
                                        {pb.max_ttl_minutes >= 60 ? `${pb.max_ttl_minutes / 60} giờ` : `${pb.max_ttl_minutes} phút`}
                                    </div>
                                </div>
                            </div>

                            <Button
                                variant="primary"
                                onClick={() => handleCreateTask(pb.slug)}
                                className="w-full"
                            >
                                Giao việc cho agent
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </PageLayout>
    );
}
