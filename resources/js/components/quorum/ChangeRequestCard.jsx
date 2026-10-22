import React from 'react';

const STATUS_STYLES = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    executed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const STATUS_LABELS = {
    pending: 'Chờ duyệt',
    executed: 'Đã thực thi',
    rejected: 'Bị từ chối',
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('vi-VN');
}

export default function ChangeRequestCard({
    changeRequest,
    reauthFresh,
    busy,
    onApprove,
    onReject,
}) {
    const progress = Math.min(
        100,
        Math.round(
            (changeRequest.approvals_count / Math.max(1, changeRequest.required_approvals)) * 100,
        ),
    );

    const approveDisabled = busy || !changeRequest.can_vote || !reauthFresh;
    const rejectDisabled = busy || !changeRequest.can_vote;

    return (
        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold text-slate-900">
                            {changeRequest.type_label}
                        </h3>
                        <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ${
                                STATUS_STYLES[changeRequest.status] ??
                                'bg-slate-50 text-slate-600 ring-slate-200'
                            }`}
                        >
                            {STATUS_LABELS[changeRequest.status] ?? changeRequest.status}
                        </span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                        {changeRequest.requester.name} · {changeRequest.requester.email} ·{' '}
                        {formatDate(changeRequest.created_at)}
                    </p>
                </div>
                <span className="rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500">
                    {changeRequest.id.slice(0, 8)}
                </span>
            </div>

            <div className="mt-4 rounded-lg bg-slate-50 p-3">
                <pre className="max-h-40 overflow-auto whitespace-pre-wrap break-all font-mono text-[11px] leading-5 text-slate-700">
                    {JSON.stringify(changeRequest.payload, null, 2)}
                </pre>
            </div>

            <div className="mt-4">
                <div className="flex items-center justify-between text-xs text-slate-600">
                    <span>
                        Phiếu duyệt:{' '}
                        <span className="font-semibold text-slate-900">
                            {changeRequest.approvals_count}/{changeRequest.required_approvals}
                        </span>
                        {changeRequest.remaining_approvals > 0 && (
                            <span className="text-slate-500">
                                {' '}
                                · cần thêm {changeRequest.remaining_approvals}
                            </span>
                        )}
                    </span>
                    {changeRequest.rejections_count > 0 && (
                        <span className="text-rose-600">
                            {changeRequest.rejections_count} phiếu từ chối
                        </span>
                    )}
                </div>
                <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-200">
                    <div
                        className={`h-full rounded-full transition-all ${
                            changeRequest.status === 'rejected' ? 'bg-rose-500' : 'bg-indigo-500'
                        }`}
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>

            <div className="mt-3 space-y-1">
                {changeRequest.approvals.length === 0 && (
                    <p className="text-xs text-slate-500">Chưa có phiếu nào.</p>
                )}
                {changeRequest.approvals.map((approval) => (
                    <div
                        key={`${approval.admin_id}-${approval.at}`}
                        className="flex items-center justify-between text-xs"
                    >
                        <span className="text-slate-600">{approval.admin_name}</span>
                        <span
                            className={
                                approval.decision === 'approve'
                                    ? 'font-medium text-emerald-600'
                                    : 'font-medium text-rose-600'
                            }
                        >
                            {approval.decision === 'approve' ? 'Đồng ý' : 'Từ chối'}
                            {approval.has_signature ? ' · có chữ ký' : ''}
                        </span>
                    </div>
                ))}
            </div>

            {changeRequest.status === 'executed' && (
                <p className="mt-3 text-xs text-emerald-700">
                    Đã thực thi lúc {formatDate(changeRequest.executed_at)} (một transaction).
                </p>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    onClick={() => onApprove(changeRequest)}
                    disabled={approveDisabled}
                    title={!reauthFresh ? 'Cần xác thực lại trước khi duyệt' : undefined}
                    className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {busy ? 'Đang xử lý…' : 'Duyệt'}
                </button>
                <button
                    type="button"
                    onClick={() => onReject(changeRequest)}
                    disabled={rejectDisabled}
                    className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Từ chối
                </button>

                {changeRequest.my_decision && (
                    <span className="text-xs text-slate-500">
                        Bạn đã bỏ phiếu:{' '}
                        {changeRequest.my_decision === 'approve' ? 'đồng ý' : 'từ chối'}
                    </span>
                )}
                {!changeRequest.my_decision && changeRequest.can_vote && !reauthFresh && (
                    <span className="text-xs text-amber-700">Cần xác thực lại</span>
                )}
            </div>
        </article>
    );
}
