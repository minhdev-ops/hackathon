import { useEffect, useState } from 'react';

function pad(n) {
    return String(n).padStart(2, '0');
}

export function CountdownTimer({ seconds }) {
    const [remaining, setRemaining] = useState(Math.max(0, seconds));

    useEffect(() => {
        if (remaining <= 0) {
            return;
        }
        const timer = setInterval(() => {
            setRemaining((prev) => (prev <= 1 ? 0 : prev - 1));
        }, 1000);
        return () => clearInterval(timer);
    }, [remaining]);

    const total = Math.max(0, remaining);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    if (hours > 0) {
        return <span className="font-mono text-sm">{hours}:${pad(minutes)}:${pad(secs)}</span>;
    }
    return <span className="font-mono text-sm">{pad(minutes)}:${pad(secs)}</span>;
}
