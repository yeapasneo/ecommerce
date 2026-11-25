import { useEffect, useState } from 'react';
import { money } from '../../lib/format';
import Icon from '../Icon';

/** Search box that lists products and their variants (one row each). */
export default function ProductPicker({ onPick, placeholder = 'Search products to add…', showStock = true }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    useEffect(() => {
        if (q.trim().length < 2) { setResults([]); return undefined; }
        const t = setTimeout(() => fetch(`/admin/products/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then(setResults).catch(() => setResults([])), 250);
        return () => clearTimeout(t);
    }, [q]);
    return (
        <div className="relative">
            <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input className="input h-11 pl-9" value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} aria-label="Search products" />
            {results.length > 0 && (
                <ul className="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                    {results.map((p) => (
                        <li key={`${p.id}-${p.variant_id ?? 0}`}>
                            <button type="button" onClick={() => { onPick(p); setQ(''); setResults([]); }} className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-primary-50">
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded border border-slate-200 bg-white">{p.image_url && <img src={p.image_url} alt="" className="max-h-full max-w-full object-contain" />}</span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm">{p.name}{p.variant_name && <span className="text-primary-700"> · {p.variant_name}</span>}</span>
                                    <span className="block text-xs text-slate-500">{p.sku}{showStock && ` · ${p.stock} in stock`}</span>
                                </span>
                                <span className="text-sm font-medium">{money(p.price)}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
