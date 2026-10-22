import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';

const STATUS_LABELS = {
    proposing: 'Đang lập đề xuất',
    proposal_ready: 'Đề xuất sẵn sàng',
    awaiting_approval: 'Chờ owner duyệt',
    running: 'Agent đang chạy',
    done: 'Hoàn tất',
    failed_revoked: 'Đã dừng do thu hồi',
    denied: 'Bị từ chối',
};

export default function OwnerHome() {
    const [tasks, setTasks] = useState([]);
    const [me, setMe] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let active = true;

        Promise.all([api.get('/me'), api.get('/tasks')])
            .then(([meResponse, tasksResponse]) => {
                if (active) {
                    setMe(meResponse.data.data);
                    setTasks(tasksResponse.data.data ?? []);
                }
            })
            .catch((err) => {
                if (active) {
                    setError(err.response?.status === 401
                        ? 'Bạn chưa đăng nhập.'
                        : err.response?.data?.error?.message ?? 'Không tải được dữ liệu.');
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    return (
        <div className="min-h-screen bg-[#FDFDFC] p-4 text-[#1b1b18] lg:p-8">
            <div className="mx-auto max-w-3xl">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-[#706f6c]">TrustGate</p>
                        <h1 className="mt-1 text-2xl font-bold">
                            {me ? `Xin chào, ${me.name}` : 'Két dữ liệu và Consent Broker'}
                        </h1>
                        <p className="mt-1 text-sm text-[#706f6c]">
                            Owner duyệt từng lần chia sẻ; khoá két chỉ nằm trong trình duyệt.
                        </p>
                    </div>
                    <nav className="flex flex-wrap items-center gap-2 text-sm">
                        <Link to="/timeline" className="rounded border border-[#d6d6d2] px-3 py-2 hover:bg-[#f3f3f2]">
                            Nhật ký
                        </Link>
                        <a href="/admin/quorum" className="rounded border border-[#d6d6d2] px-3 py-2 hover:bg-[#f3f3f2]">
                            Quorum
                        </a>
                        <Link to="/admin/evil" className="rounded border border-[#d6d6d2] px-3 py-2 hover:bg-[#f3f3f2]">
                            Demo admin xấu
                        </Link>
                        {me ? (
                            <form method="POST" action="/logout">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content ?? ''} />
                                <button type="submit" className="rounded border border-[#d6d6d2] px-3 py-2 hover:bg-[#f3f3f2]">
                                    Đăng xuất
                                </button>
                            </form>
                        ) : (
                            <a href="/login" className="rounded bg-[#1b1b18] px-3 py-2 font-medium text-white">
                                Đăng nhập
                            </a>
                        )}
                    </nav>
                </header>

                {loading && <p className="mt-8 text-sm text-[#706f6c]">Đang tải…</p>}

                {!loading && error && (
                    <div className="mt-8 rounded-lg border border-[#fecaca] bg-[#fef2f2] p-4 text-sm text-[#b91c1c]">
                        {error}
                    </div>
                )}

                {!loading && !error && tasks.length === 0 && (
                    <div className="mt-8 rounded-xl border border-[#e3e3e0] bg-white p-6 text-sm text-[#706f6c]">
                        Chưa có việc nào. Chạy <code className="rounded bg-[#f3f3f2] px-1">php artisan db:seed</code> rồi
                        tạo task qua API hoặc dùng màn quorum/demo.
                    </div>
                )}

                <div className="mt-6 space-y-3">
                    {tasks.map((task) => (
                        <article key={task.id} className="rounded-xl border border-[#e3e3e0] bg-white p-4 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold">{task.instruction}</h2>
                                    <p className="mt-1 text-xs text-[#706f6c]">
                                        {task.agent?.name ?? 'Agent'} · {task.playbook?.name ?? '—'}
                                    </p>
                                </div>
                                <span className="rounded-full bg-[#f3f3f2] px-3 py-1 text-xs">
                                    {STATUS_LABELS[task.status] ?? task.status}
                                </span>
                            </div>

                            {task.proposal?.rationale && (
                                <p className="mt-2 text-xs text-[#706f6c]">{task.proposal.rationale}</p>
                            )}

                            {task.grant && (
                                <div className="mt-3 flex flex-wrap items-center gap-3">
                                    <span className="text-xs text-[#706f6c]">
                                        Grant: {task.grant.status}
                                        {task.grant.expires_at ? ` · hết hạn ${new Date(task.grant.expires_at).toLocaleString('vi-VN')}` : ''}
                                    </span>
                                    <Link
                                        to={`/grants/${task.grant.id}`}
                                        className="rounded border border-[#1b1b18] px-3 py-1.5 text-xs font-medium hover:bg-[#f3f3f2]"
                                    >
                                        Mở màn duyệt
                                    </Link>
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            </div>
        </div>
    );
}
