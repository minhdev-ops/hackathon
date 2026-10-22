import { createContext, useCallback, useContext, useState } from 'react';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const toast = useCallback(({ title, description, type = 'info', duration = 5000 }) => {
        const id = Math.random().toString(36).slice(2, 9);
        setToasts((prev) => [...prev, { id, title, description, type, duration }]);
        setTimeout(() => {
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, duration);
        return id;
    }, []);

    const dismiss = useCallback((id) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    return (
        <ToastContext.Provider value={{ toasts, toast, dismiss }}>
            {children}
            <div className="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
                {toasts.map((t) => (
                    <div
                        key={t.id}
                        className={`pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border p-4 shadow-lg transition-all ${
                            t.type === 'success'
                                ? 'border-success/20 bg-success/5 text-success'
                                : t.type === 'danger'
                                ? 'border-danger/20 bg-danger/5 text-danger'
                                : t.type === 'warning'
                                ? 'border-warning/20 bg-warning/5 text-warning'
                                : 'border-info/20 bg-info/5 text-info'
                        }`}
                    >
                        <div className="flex-1">
                            {t.title && <h4 className="text-sm font-semibold">{t.title}</h4>}
                            {t.description && <p className="text-xs text-muted mt-0.5">{t.description}</p>}
                        </div>
                        <button
                            onClick={() => dismiss(t.id)}
                            className="rounded-md p-1.5 text-subtle hover:bg-elevated transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                            aria-label="Đóng thông báo"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    return useContext(ToastContext);
}
