import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Icon from './Icon';

export default function Flash() {
    const { flash, errors } = usePage().props;
    const [items, setItems] = useState([]);

    useEffect(() => {
        const next = [];
        if (flash?.success) next.push({ type: 'success', text: flash.success });
        if (flash?.error) next.push({ type: 'error', text: flash.error });
        if (errors?.cart) next.push({ type: 'error', text: errors.cart });
        if (!next.length) return;
        const stamped = next.map((n) => ({ ...n, id: Math.random() }));
        setItems((cur) => [...cur, ...stamped]);
        const t = setTimeout(() => setItems((cur) => cur.filter((c) => !stamped.includes(c))), 4500);
        return () => clearTimeout(t);
    }, [flash, errors]);

    if (!items.length) return null;
    return (
        <div className="no-print fixed bottom-4 right-4 z-[60] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2">
            {items.map((i) => (
                <div key={i.id} role="status" className={`flex items-start gap-3 rounded-lg px-4 py-3 text-sm text-white shadow-lg ${i.type === 'success' ? 'bg-green-600' : 'bg-red-600'}`}>
                    <Icon name={i.type === 'success' ? 'check' : 'x'} className="mt-0.5 h-4 w-4 shrink-0" />
                    <span className="flex-1">{i.text}</span>
                    <button onClick={() => setItems((cur) => cur.filter((c) => c !== i))} aria-label="Dismiss"><Icon name="x" className="h-4 w-4" /></button>
                </div>
            ))}
        </div>
    );
}
