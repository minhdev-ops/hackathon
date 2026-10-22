export function Card({ children, className = '', padding = 'p-6', shadow = 'shadow' }) {
    return (
        <div
            className={`rounded-xl border border-border bg-surface ${shadow} transition-all hover:shadow-lg ${padding} ${className}`}
        >
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '' }) {
    return <div className={`mb-4 ${className}`}>{children}</div>;
}

export function CardTitle({ children, className = '' }) {
    return (
        <h3 className={`text-lg font-semibold text-text tracking-tight ${className}`}>{children}</h3>
    );
}

export function CardDescription({ children, className = '' }) {
    return <p className={`text-sm text-muted ${className}`}>{children}</p>;
}

export function CardContent({ children, className = '' }) {
    return <div className={`mt-4 ${className}`}>{children}</div>;
}

export function CardFooter({ children, className = '' }) {
    return <div className={`mt-6 flex items-center gap-3 ${className}`}>{children}</div>;
}
