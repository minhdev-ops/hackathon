import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { formatApiError, logout, quorumApi } from '../api/quorum';
import ChangeRequestForm from '../components/quorum/ChangeRequestForm';
import ChangeRequestCard from '../components/quorum/ChangeRequestCard';

const FILTERS = [
    { value: 'all', label: 'Tất cả' },
    { value: 'pending', label: 'Chờ duyệt' },
    { value: 'executed', label: 'Đã thực thi' },
    { value: 'rejected', label: 'Bị từ chối' },
];

function formatRemaining(ms) {
    if (ms <= 0) {
        return '0:00';
    }

    const total = Math.floor(ms / 1000);
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

export default function AdminQuorum({ userName }) {
    const [meta, setMeta] = useState(null);
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [notice, setNotice] = useState(null);
    const [password, setPassword] = useState('');
    const [reauthBusy, setReauthBusy] = useState(false);
    const [busyId, setBusyId] = useState(null);
    const [filter, setFilter] = useState('all');
    const [now, setNow] = useState(Date.now());

    const load = useCallback(async () => {
        try {
            const [metaResponse, listResponse] = await Promise.all([
                quorumApi.meta(),
                quorumApi.list(),
            ]);

            setMeta(metaResponse.data);
            setRequests(listResponse.data ?? []);
        } catch (requestError) {
            setError(formatApiError(requestError));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
        const poll = window.setInterval(load, 5000);
        const tick = window.setInterval(() => setNow(Date.now()), 1000);

        return () => {
            window.clearInterval(poll);
            window.clearInterval(tick);
        };
    }, [load]);

    const reauthUntil = useMemo(() => {
        if (!meta?.reauth?.reauthenticated_until) {
            return 0;
        }

        return new Date(meta.reauth.reauthenticated_until).getTime();
    }, [meta]);

    const reauthFresh = reauthUntil > now;

    const pendingCount = requests.filter((item) => item.status === 'pending').length;
    const executedCount = requests.filter((item) => item.status === 'executed').length;

    const visibleRequests = useMemo(
        () => (filter === 'all' ? requests : requests.filter((item) => item.status === filter)),
        [requests, filter],
    );

    const handleReauth = async (event) => {
        event.preventDefault();
        setError(null);
        setReauthBusy(true);

        try {
            await quorumApi.reauth(password);
            setPassword('');
            setNotice(`Đã xác thực lại. Bạn có ${meta?.reauth?.minutes ?? 5} phút để duyệt.`);
            await load();
        } catch (reauthError) {
            setError(formatApiError(reauthError));
        } finally {
            setReauthBusy(false);
        }
    };

    const handleCreate = async (type, payload) => {
        setError(null);
        setNotice(null);

        try {
            await quorumApi.create(type, payload);
            setNotice('Đã tạo Change Request ở trạng thái chờ duyệt.');
            await load();
        } catch (createError) {
            setError(formatApiError(createError));
            throw createError;
        }
    };

    const handleApprove = async (changeRequest) => {
        setError(null);
        setNotice(null);
        setBusyId(changeRequest.id);

        try {
            const response = await quorumApi.approve(changeRequest.id);
            setNotice(
                response.data.status === 'executed'
                    ? 'Quorum đủ ngưỡng — payload đã được thực thi.'
                    : 'Đã ghi nhận phiếu duyệt, đang chờ admin khác.',
            );
            await load();
        } catch (approveError) {
            setError(formatApiError(approveError));
        } finally {
            setBusyId(null);
        }
    };

    const handleReject = async (changeRequest) => {
        setError(null);
        setNotice(null);
        setBusyId(changeRequest.id);

        try {
            await quorumApi.reject(changeRequest.id);
            setNotice('Đã ghi nhận phiếu từ chối.');
            await load();
        } catch (rejectError) {
            setError(formatApiError(rejectError));
        } finally {
            setBusyId(null);
        }
    };

    const handleDirectTry = async () => {
        setError(null);
        setNotice(null);

        try {
            await quorumApi.directThreshold();
            setNotice('Không ngờ: đổi trực tiếp lại thành công.');
        } catch (directError) {
            if (directError?.code === 'QUORUM_REQUIRED') {
                setNotice('409 QUORUM_REQUIRED — đổi trực tiếp bị chặn, phải tạo Change Request.');
            } else {
                setError(formatApiError(directError));
            }
        }
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
                                TG
                            </span>
                            <h1 className="text-lg font-semibold">TrustGate · Quorum quản trị</h1>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            Thay đổi quyền, playbook, allowlist và ngưỡng duyệt đều cần quorum admin.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <span
                            className={`rounded-full px-3 py-1 text-xs font-medium ${
                                meta?.qea?.enabled
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                    : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                            }`}
                            title={meta?.qea?.note}
                        >
                            {meta?.qea?.label ?? 'Đang tải QEA…'}
                        </span>
                        <span className="text-slate-500">{userName}</span>
                        <a
                            href="/timeline"
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                        >
                            Nhật ký
                        </a>
                        <button
                            type="button"
                            onClick={logout}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                        >
                            Đăng xuất
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-6 py-8">
                {(error || notice) && (
                    <div
                        className={`mb-6 rounded-xl border px-4 py-3 text-sm ${
                            error
                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        }`}
                    >
                        {error ?? notice}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[390px_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <section className="grid grid-cols-3 gap-3">
                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Ngưỡng</p>
                                <p className="mt-1 text-2xl font-semibold">{meta?.threshold ?? '—'}</p>
                                <p className="text-xs text-slate-500">admin / CR</p>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Chờ duyệt</p>
                                <p className="mt-1 text-2xl font-semibold">{pendingCount}</p>
                                <p className="text-xs text-slate-500">Change Request</p>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Đã chạy</p>
                                <p className="mt-1 text-2xl font-semibold">{executedCount}</p>
                                <p className="text-xs text-slate-500">payload</p>
                            </div>
                        </section>

                        <section
                            className={`rounded-xl border p-4 ${
                                reauthFresh
                                    ? 'border-emerald-200 bg-emerald-50'
                                    : 'border-amber-200 bg-amber-50'
                            }`}
                        >
                            {reauthFresh ? (
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium text-emerald-800">
                                        Đã xác thực lại
                                    </p>
                                    <span className="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        còn {formatRemaining(reauthUntil - now)}
                                    </span>
                                </div>
                            ) : (
                                <form onSubmit={handleReauth} className="space-y-3">
                                    <div>
                                        <p className="text-sm font-medium text-amber-900">
                                            Cần xác thực lại trước khi duyệt
                                        </p>
                                        <p className="mt-1 text-xs text-amber-800">
                                            FR-QRM-04 fallback: nhập lại mật khẩu, hiệu lực{' '}
                                            {meta?.reauth?.minutes ?? 5} phút.
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <input
                                            type="password"
                                            value={password}
                                            onChange={(event) => setPassword(event.target.value)}
                                            placeholder="Mật khẩu admin"
                                            required
                                            className="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm outline-none focus:border-amber-500"
                                        />
                                        <button
                                            type="submit"
                                            disabled={reauthBusy}
                                            className="shrink-0 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-amber-700 disabled:opacity-60"
                                        >
                                            {reauthBusy ? 'Đang…' : 'Xác thực'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </section>

                        {meta && <ChangeRequestForm meta={meta} onCreate={handleCreate} />}

                        <section className="rounded-xl border border-slate-200 bg-white p-4">
                            <h2 className="text-sm font-semibold">Demo FR-QRM-01</h2>
                            <p className="mt-1 text-xs text-slate-500">
                                Gọi thẳng API đổi ngưỡng quorum phải bị chặn bằng 409 QUORUM_REQUIRED.
                            </p>
                            <button
                                type="button"
                                onClick={handleDirectTry}
                                className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                            >
                                Thử đổi ngưỡng trực tiếp
                            </button>
                        </section>
                    </div>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-base font-semibold">Change Request</h2>
                            <div className="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
                                {FILTERS.map((item) => (
                                    <button
                                        key={item.value}
                                        type="button"
                                        onClick={() => setFilter(item.value)}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition ${
                                            filter === item.value
                                                ? 'bg-white text-slate-900 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-800'
                                        }`}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {loading && <p className="text-sm text-slate-500">Đang tải…</p>}

                        {!loading && visibleRequests.length === 0 && (
                            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                                <p className="text-sm font-medium text-slate-700">
                                    Chưa có Change Request nào
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Tạo CR mới ở cột bên trái để thấy luồng quorum 1/2 → executed.
                                </p>
                            </div>
                        )}

                        <div className="space-y-4">
                            {visibleRequests.map((changeRequest) => (
                                <ChangeRequestCard
                                    key={changeRequest.id}
                                    changeRequest={changeRequest}
                                    reauthFresh={reauthFresh}
                                    busy={busyId === changeRequest.id}
                                    onApprove={handleApprove}
                                    onReject={handleReject}
                                />
                            ))}
                        </div>
                    </section>
                </div>
            </main>
        </div>
    );
}
