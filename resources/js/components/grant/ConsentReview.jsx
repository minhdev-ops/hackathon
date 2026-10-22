import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../../api/client';
import { bytesToBase64 } from '../../crypto/base64.js';
import { createGrantBundle } from '../../crypto/bundle.js';
import {
    decryptDocument,
    decryptTitle,
    isVaultUnlocked,
    lockVault,
    sha256Hex,
    unlockVault,
} from '../../crypto/vault.js';

const DOC_LABELS = {
    id_card: 'Căn cước / CMND',
    income_proof: 'Chứng minh thu nhập',
    rental_history: 'Lịch sử thuê nhà',
    reference_letter: 'Thư giới thiệu',
    insurance_card: 'Thẻ bảo hiểm',
    medical_summary: 'Tóm tắt bệnh án',
    lab_result: 'Kết quả xét nghiệm',
};

const STATUS_LABELS = {
    pending: 'Chờ duyệt',
    active: 'Đang hiệu lực',
    revoked: 'Đã thu hồi',
    expired: 'Đã hết hạn',
    denied: 'Đã từ chối',
};

function formatError(error) {
    const payload = error?.response?.data?.error;

    if (!payload) {
        return error?.message ?? 'Có lỗi xảy ra.';
    }

    const missing = payload.details?.missing_doc_types;

    return missing?.length
        ? `${payload.message} Thiếu: ${missing.map((type) => DOC_LABELS[type] ?? type).join(', ')}.`
        : payload.message ?? 'Có lỗi xảy ra.';
}

function formatCountdown(seconds) {
    const safe = Math.max(0, seconds);
    const hours = Math.floor(safe / 3600);
    const minutes = Math.floor((safe % 3600) / 60);
    const secs = safe % 60;

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
        : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

export default function ConsentReview({ grantId: providedGrantId = null }) {
    const { grantId: routeGrantId } = useParams();
    const grantId = providedGrantId ?? routeGrantId;
    const [me, setMe] = useState(null);
    const [grant, setGrant] = useState(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const [unlocked, setUnlocked] = useState(isVaultUnlocked());
    const [passphrase, setPassphrase] = useState('');
    const [unlocking, setUnlocking] = useState(false);
    const [unlockError, setUnlockError] = useState(null);
    const [titles, setTitles] = useState({});
    const [selected, setSelected] = useState({});
    const [ttl, setTtl] = useState('');
    const [busy, setBusy] = useState(null);
    const [actionError, setActionError] = useState(null);
    const [notice, setNotice] = useState(null);
    const [clock, setClock] = useState(Date.now());
    const [serverOffset, setServerOffset] = useState(0);

    useEffect(() => {
        if (!grantId) {
            setLoadError('Thiếu mã grant để duyệt.');
            setLoading(false);
            return;
        }

        let active = true;

        Promise.all([api.get('/me'), api.get(`/grants/${grantId}`)])
            .then(([meResponse, grantResponse]) => {
                if (!active) {
                    return;
                }

                const current = grantResponse.data.data;
                setMe(meResponse.data.data);
                setGrant(current);
                setServerOffset(
                    current.server_time ? Date.parse(current.server_time) - Date.now() : 0,
                );

                const documents = current.review?.documents ?? [];
                setSelected(Object.fromEntries(
                    documents.map((document) => [
                        document.id,
                        document.required || document.selected,
                    ]),
                ));
                setTtl(String(
                    current.review?.default_ttl_minutes
                    ?? current.review?.ttl_options?.[0]
                    ?? '',
                ));
            })
            .catch((error) => {
                if (active) {
                    setLoadError(formatError(error));
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
    }, [grantId]);

    useEffect(() => {
        const timer = window.setInterval(() => setClock(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!grant || !unlocked) {
            return undefined;
        }

        let active = true;
        const documents = grant.review?.documents ?? [];

        Promise.all(documents.map(async (document) => {
            try {
                return [document.id, await decryptTitle(document.title_ct)];
            } catch {
                return [document.id, null];
            }
        })).then((entries) => {
            if (active) {
                setTitles(Object.fromEntries(entries));
            }
        });

        return () => {
            active = false;
        };
    }, [grant, unlocked]);

    const review = grant?.review ?? {};
    const documents = review.documents ?? [];
    const requiredTypes = review.required_doc_types ?? [];
    const selectedDocuments = useMemo(
        () => documents.filter((document) => selected[document.id]),
        [documents, selected],
    );
    const missingTypes = requiredTypes.filter(
        (type) => !selectedDocuments.some((document) => document.doc_type === type),
    );
    const remaining = grant?.expires_at
        ? Math.max(0, Math.floor((Date.parse(grant.expires_at) - (clock + serverOffset)) / 1000))
        : 0;
    const isActive = grant?.status === 'active' && remaining > 0;
    const isPending = grant?.status === 'pending';

    const handleUnlock = async (event) => {
        event.preventDefault();
        setUnlockError(null);
        setUnlocking(true);

        try {
            await unlockVault(passphrase, me?.vault_kdf_salt);

            const first = documents[0];
            if (first) {
                await decryptTitle(first.title_ct);
            }

            setUnlocked(true);
            setPassphrase('');
        } catch {
            lockVault();
            setUnlockError('Không mở được két. Kiểm tra mật khẩu két (demo: password).');
        } finally {
            setUnlocking(false);
        }
    };

    const toggleDocument = (document) => {
        if (document.required) {
            return;
        }

        setSelected((previous) => ({
            ...previous,
            [document.id]: !previous[document.id],
        }));
    };

    const handleApprove = async () => {
        setActionError(null);
        setNotice(null);

        if (!unlocked) {
            setActionError('Hãy mở két bằng mật khẩu demo trước khi duyệt.');
            return;
        }

        if (missingTypes.length > 0) {
            setActionError(`Không thể bỏ tài liệu bắt buộc: ${missingTypes.join(', ')}.`);
            return;
        }

        setBusy('approve');

        try {
            const bundleDocuments = [];

            for (const document of selectedDocuments) {
                const response = await api.get(`/documents/${document.id}/ciphertext`, {
                    responseType: 'arraybuffer',
                });
                const plaintext = await decryptDocument(new Uint8Array(response.data), document.iv);
                const digest = await sha256Hex(plaintext);

                if (digest.toLowerCase() !== String(document.sha256).toLowerCase()) {
                    throw new Error(`Tài liệu ${DOC_LABELS[document.doc_type] ?? document.doc_type} không khớp sha256.`);
                }

                bundleDocuments.push({
                    doc_id: document.id,
                    doc_type: document.doc_type,
                    name: titles[document.id] ?? DOC_LABELS[document.doc_type] ?? document.doc_type,
                    content_b64: bytesToBase64(new Uint8Array(plaintext)),
                });
            }

            const bundle = await createGrantBundle(bundleDocuments);
            const form = new FormData();

            for (const document of selectedDocuments) {
                form.append('doc_ids[]', document.id);
            }

            form.append('ttl_minutes', String(ttl));
            form.append('grant_key_b64', bundle.keyB64);
            form.append('bundle_iv', bundle.iv);
            form.append('bundle', new Blob([bundle.bundle], { type: 'application/octet-stream' }), 'bundle.bin');

            const response = await api.post(`/grants/${grantId}/approve`, form);
            const updated = response.data.data;

            setGrant(updated);
            setServerOffset(
                updated.server_time ? Date.parse(updated.server_time) - Date.now() : 0,
            );
            setNotice('Đã duyệt. Agent đang chạy trong thời hạn grant.');
        } catch (error) {
            setActionError(formatError(error));
        } finally {
            setBusy(null);
        }
    };

    const handleDeny = async () => {
        if (!window.confirm('Từ chối grant này? Agent sẽ không thể mở tài liệu.')) {
            return;
        }

        setBusy('deny');
        setActionError(null);

        try {
            const response = await api.post(`/grants/${grantId}/deny`, {});
            setGrant(response.data.data);
            setNotice('Đã từ chối grant.');
        } catch (error) {
            setActionError(formatError(error));
        } finally {
            setBusy(null);
        }
    };

    const handleRevoke = async () => {
        if (!window.confirm('Thu hồi grant ngay? K_g sẽ bị xoá và agent dừng lại.')) {
            return;
        }

        setBusy('revoke');
        setActionError(null);

        try {
            const response = await api.post(`/grants/${grantId}/revoke`, {});
            setGrant(response.data.data);
            setNotice('Đã thu hồi grant và xoá khoá.');
        } catch (error) {
            setActionError(formatError(error));
        } finally {
            setBusy(null);
        }
    };

    if (loading) {
        return <div className="p-8 text-sm text-[#706f6c]">Đang tải grant…</div>;
    }

    if (loadError) {
        return (
            <div className="mx-auto max-w-2xl p-8">
                <div className="rounded-lg border border-[#fecaca] bg-[#fef2f2] p-4 text-sm text-[#b91c1c]">
                    {loadError}
                </div>
                <Link to="/" className="mt-4 inline-block text-sm underline">← Trang chủ</Link>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-[#FDFDFC] p-4 text-[#1b1b18] lg:p-8">
            <div className="mx-auto max-w-3xl">
                <div className="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Duyệt chia sẻ dữ liệu</h1>
                        <p className="mt-1 text-sm text-[#706f6c]">
                            {grant.agent?.name} · {grant.purpose}
                        </p>
                    </div>
                    <Link to="/" className="text-sm underline">← Trang chủ</Link>
                </div>

                {notice && (
                    <div className="mb-4 rounded-lg border border-[#86efac] bg-[#f0fdf4] p-3 text-sm text-[#15803d]">
                        {notice}
                    </div>
                )}

                {actionError && (
                    <div className="mb-4 rounded-lg border border-[#fecaca] bg-[#fef2f2] p-3 text-sm text-[#b91c1c]">
                        {actionError}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-[1.6fr_1fr]">
                    <section className="rounded-xl border border-[#e3e3e0] bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">Tài liệu được yêu cầu</h2>
                            <span className="rounded-full bg-[#f3f3f2] px-3 py-1 text-xs">
                                {STATUS_LABELS[grant.status] ?? grant.status}
                            </span>
                        </div>

                        <p className="mt-2 text-xs text-[#706f6c]">
                            Gửi cho: {review.recipient_label ?? '—'} · {review.playbook?.name ?? '—'}
                        </p>

                        {review.rationale && (
                            <p className="mt-2 rounded bg-[#f8f8f7] p-2 text-xs text-[#706f6c]">
                                Lý do: {review.rationale}
                            </p>
                        )}

                        <div className="mt-4 space-y-2">
                            {documents.map((document) => (
                                <label
                                    key={document.id}
                                    className="flex cursor-pointer items-start gap-3 rounded-lg border border-[#e3e3e0] p-3"
                                >
                                    <input
                                        type="checkbox"
                                        checked={Boolean(selected[document.id])}
                                        disabled={document.required || !isPending}
                                        onChange={() => toggleDocument(document)}
                                        className="mt-1 h-4 w-4"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-medium">
                                                {titles[document.id]
                                                    ?? (unlocked ? DOC_LABELS[document.doc_type] : 'Đang khoá…')}
                                            </span>
                                            {document.required && (
                                                <span className="rounded bg-[#fef2f2] px-2 py-0.5 text-[10px] font-semibold text-[#b91c1c]">
                                                    bắt buộc
                                                </span>
                                            )}
                                        </span>
                                        <span className="mt-1 block text-xs text-[#706f6c]">
                                            {DOC_LABELS[document.doc_type] ?? document.doc_type}
                                            {document.size_bytes ? ` · ${(document.size_bytes / 1024).toFixed(1)} KB` : ''}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>

                        {missingTypes.length > 0 && isPending && (
                            <p className="mt-3 text-xs text-[#b91c1c]">
                                Còn thiếu: {missingTypes.map((type) => DOC_LABELS[type] ?? type).join(', ')}
                            </p>
                        )}

                        {isPending && (
                            <div className="mt-5 flex flex-wrap items-end gap-3">
                                <label className="text-xs font-medium text-[#706f6c]">
                                    Thời hạn
                                    <select
                                        value={ttl}
                                        onChange={(event) => setTtl(event.target.value)}
                                        className="mt-1 block rounded border border-[#d6d6d2] bg-white px-3 py-2 text-sm text-[#1b1b18]"
                                    >
                                        {(review.ttl_options ?? []).map((minutes) => (
                                            <option key={minutes} value={minutes}>
                                                {minutes >= 60 ? `${minutes / 60} giờ` : `${minutes} phút`}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={handleApprove}
                                        disabled={busy !== null || missingTypes.length > 0}
                                        className="rounded bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                    >
                                        {busy === 'approve' ? 'Đang mã hoá…' : 'Duyệt'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleDeny}
                                        disabled={busy !== null}
                                        className="rounded border border-[#b91c1c] px-4 py-2 text-sm font-medium text-[#b91c1c] disabled:opacity-50"
                                    >
                                        {busy === 'deny' ? 'Đang xử lý…' : 'Từ chối'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {isActive && (
                            <div className="mt-5 rounded-lg border border-[#86efac] bg-[#f0fdf4] p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-[#15803d]">Grant đang hiệu lực</p>
                                        <p className="mt-1 text-xs text-[#15803d]">
                                            Còn lại {formatCountdown(remaining)}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRevoke}
                                        disabled={busy !== null}
                                        className="rounded border border-[#b91c1c] bg-white px-3 py-2 text-sm font-medium text-[#b91c1c] disabled:opacity-50"
                                    >
                                        {busy === 'revoke' ? 'Đang thu hồi…' : 'Thu hồi'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {grant.status === 'active' && remaining === 0 && (
                            <p className="mt-4 text-sm text-[#b91c1c]">Grant đã hết hạn.</p>
                        )}
                    </section>

                    <aside className="space-y-4">
                        <section className="rounded-xl border border-[#e3e3e0] bg-white p-5 shadow-sm">
                            <h2 className="font-semibold">Mở két</h2>
                            <p className="mt-1 text-xs text-[#706f6c]">
                                Khoá chỉ nằm trong bộ nhớ trình duyệt. Demo dùng mật khẩu <code>password</code>.
                            </p>
                            {unlocked ? (
                                <p className="mt-3 text-sm font-medium text-[#15803d]">Két đã mở.</p>
                            ) : (
                                <form onSubmit={handleUnlock} className="mt-3 space-y-2">
                                    <input
                                        type="password"
                                        value={passphrase}
                                        onChange={(event) => setPassphrase(event.target.value)}
                                        placeholder="Mật khẩu két"
                                        required
                                        className="w-full rounded border border-[#d6d6d2] px-3 py-2 text-sm"
                                    />
                                    <button
                                        type="submit"
                                        disabled={unlocking}
                                        className="w-full rounded border border-[#1b1b18] px-3 py-2 text-sm font-medium disabled:opacity-50"
                                    >
                                        {unlocking ? 'Đang mở…' : 'Mở két'}
                                    </button>
                                    {unlockError && (
                                        <p className="text-xs text-[#b91c1c]">{unlockError}</p>
                                    )}
                                </form>
                            )}
                        </section>

                        <section className="rounded-xl border border-[#e3e3e0] bg-white p-5 text-xs text-[#706f6c] shadow-sm">
                            <p className="font-semibold text-[#1b1b18]">Điều sẽ xảy ra</p>
                            <p className="mt-2">Trình duyệt giải mã tài liệu, tạo K_g mới, mã hoá bundle và chỉ gửi bản mã.</p>
                            <p className="mt-2">Server không nhận plaintext hay K_g trong log.</p>
                            <p className="mt-2">Thu hồi sẽ xoá K_g; agent đang chạy dừng ngay.</p>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    );
}
