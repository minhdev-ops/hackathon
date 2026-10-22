import { forwardRef } from 'react';

export const Input = forwardRef(function Input({ className = '', id, ...props }, ref) {
    return (
        <input
            ref={ref}
            id={id}
            className={`w-full rounded-md border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent transition disabled:opacity-60 disabled:pointer-events-none ${className}`}
            {...props}
        />
    );
});

Input.displayName = 'Input';

export const Label = forwardRef(function Label({ className = '', htmlFor, children }, ref) {
    return (
        <label
            ref={ref}
            htmlFor={htmlFor}
            className={`mb-1.5 block text-xs font-medium text-muted uppercase tracking-wider ${className}`}
        >
            {children}
        </label>
    );
});

Label.displayName = 'Label';

export const Select = forwardRef(function Select({ className = '', id, ...props }, ref) {
    return (
        <select
            ref={ref}
            id={id}
            className={`w-full rounded-md border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent transition disabled:opacity-60 disabled:pointer-events-none ${className}`}
            {...props}
        />
    );
});

Select.displayName = 'Select';

export const Textarea = forwardRef(function Textarea({ className = '', id, ...props }, ref) {
    return (
        <textarea
            ref={ref}
            id={id}
            className={`w-full rounded-md border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent transition disabled:opacity-60 disabled:pointer-events-none ${className}`}
            {...props}
        />
    );
});

Textarea.displayName = 'Textarea';
