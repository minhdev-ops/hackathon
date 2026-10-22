import { forwardRef } from 'react';

const base =
    'inline-flex items-center justify-center gap-2 font-medium transition-colors disabled:opacity-60 disabled:pointer-events-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1';

const variants = {
    primary: 'bg-accent text-[#FDFDFC] hover:bg-accent-hover',
    secondary: 'border border-border text-text hover:bg-elevated',
    danger: 'border border-danger text-danger hover:bg-danger/10',
};

const sizes = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};

export const Button = forwardRef(
    (
        { variant = 'primary', size = 'md', disabled, loading, className, type = 'button', children },
        ref
    ) => (
        <button
            ref={ref}
            type={type}
            disabled={disabled || loading}
            className={`${base} ${variants[variant]} ${sizes[size]} rounded-md ${className ?? ''}`}
        >
            {loading && (
                <svg
                    className="w-4 h-4 mr-1.5 animate-spin"
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
