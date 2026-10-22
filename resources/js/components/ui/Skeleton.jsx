import { forwardRef } from 'react';

export const Skeleton = forwardRef(function Skeleton({ className = '', ...props }, ref) {
    return (
        <div
            ref={ref}
            className={`animate-pulse rounded-md bg-elevated ${className}`}
            {...props}
        />
    );
});

Skeleton.displayName = 'Skeleton';

export function SkeletonLine({ count = 3, className = '' }) {
    return (
        <div className={`space-y-2 ${className}`}>
            {Array.from({ length: count }).map((_, i) => (
                <div
                    key={i}
                    className="h-4 rounded-md bg-elevated animate-pulse"
                    style={{ width: i === count - 1 ? '60%' : '100%' }}
                />
            ))}
        </div>
    );
}
