import { forwardRef } from 'react';

const base =
    'inline-flex items-center justify-center gap-2 font-medium transition-all disabled:opacity-60 disabled:pointer-events-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-[#FDFDFC]';

const variants = {
    primary:
        'bg-accent text-[#FDFDFC] shadow-sm hover:bg-accent-hover hover:shadow hover:-translate-y-0.5 active:translate-y-0',
    secondary:
        'border border-border text-text bg-surface hover:bg-elevated shadow-sm hover:shadow hover:-translate-y-0.5 active:translate-y-0',
    danger:
        'border border-danger/30 text-danger bg-danger/5 hover:bg-danger/10 shadow-sm hover:shadow hover:-translate-y-0.5 active:translate-y-0',
    outline:
        'border border-accent text-accent bg-transparent hover:bg-accent/5 shadow-sm hover:shadow hover:-translate-y-0.5 active:translate-y-0',
};

const sizes = {
    sm: 'px-3 py-1.5 text-xs rounded-md',
    md: 'px-4 py-2 text-sm rounded-md',
    lg: 'px-6 py-3 text-base rounded-lg',
};

export const Button = forwardRef(
    (
        // `...props` PHẢI được spread xuống <button> — nếu không, mọi
        // onClick/onSubmit… bị nuốt và nút bấm vô hiệu quả (bug "mọi nút
        // không có chức năng").
        { variant = 'primary', size = 'md', disabled, loading, className, type = 'button', children, ...props },
        ref
    ) => (
        <button
            ref={ref}
            type={type}
            disabled={disabled || loading}
            className={`${base} ${variants[variant]} ${sizes[size]} ${className ?? ''}`}
            {...props}
        >
            {loading && (
                <svg
                    className="w-4 h-4 mr-2 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                    />
                    <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                </svg>
            )}
            {children}
        </button>
    )
);

Button.displayName = 'Button';
