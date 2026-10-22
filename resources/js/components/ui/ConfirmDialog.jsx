import { useCallback, useState } from 'react';

export function ConfirmDialog({ isOpen, title, description, confirmLabel, cancelLabel, onConfirm, onCancel }) {
    const [confirming, setConfirming] = useState(false);

    const handleConfirm = useCallback(async () => {
        setConfirming(true);
        try {
            await onConfirm();
        } finally {
            setConfirming(false);
        }
    }, [onConfirm]);

    if (!isOpen) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-dialog-title"
        >
            <div className="fixed inset-0 bg-black/40 backdrop-blur-sm" onClick={onCancel} />
            <div
                className="relative bg-surface rounded-xl border border-border p-6 max-w-md w-full shadow-lg transition-all"
                onClick={(e) => e.stopPropagation()}
            >
                <h3 id="confirm-dialog-title" className="text-base font-bold mb-1 text-text">
                    {title}
                </h3>
                {description && (
                    <p className="text-sm text-muted mb-6">{description}</p>
                )}
                <div className="flex gap-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="flex-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-text hover:bg-elevated transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    >
                        {cancelLabel ?? 'Hủy'}
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={confirming}
                        className="flex-1 rounded-lg bg-accent text-[#FDFDFC] px-3 py-2 text-sm font-medium shadow-sm hover:bg-accent-hover hover:shadow disabled:opacity-60 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    >
                        {confirming ? 'Đang xử lý…' : confirmLabel ?? 'Xác nhận'}
                    </button>
                </div>
            </div>
        </div>
    );
}
