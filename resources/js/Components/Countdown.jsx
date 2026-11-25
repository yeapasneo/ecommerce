import { useEffect, useState } from 'react';

/** Live countdown, e.g. to the end of a flash sale. */
export default function Countdown({ to, className = '' }) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(timer);
    }, []);
    const left = Math.max(0, new Date(to).getTime() - now);
    const parts = [Math.floor(left / 86400000), Math.floor(left / 3600000) % 24, Math.floor(left / 60000) % 60, Math.floor(left / 1000) % 60];
    return (
        <span className={`inline-flex gap-1 font-mono tabular-nums ${className}`} aria-label="Time left">
            {parts.map((n, i) => (i === 0 && n === 0 ? null : <span key={i} className="rounded bg-white/20 px-1.5 py-0.5">{String(n).padStart(2, '0')}{'dhms'[i]}</span>))}
        </span>
    );
}
