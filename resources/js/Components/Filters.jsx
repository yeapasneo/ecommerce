import { router } from '@inertiajs/react';
import { useState } from 'react';
import { cleanParams } from '../lib/format';

function Panel({ title, children }) {
    const [open, setOpen] = useState(true);
    return (
        <div className="card mb-3">
            <button onClick={() => setOpen(!open)} className="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 text-sm font-semibold">
                {title}
                <span className="text-slate-400">{open ? '−' : '+'}</span>
            </button>
            {open && <div className="p-4">{children}</div>}
        </div>
    );
}

/** Navigate to the current listing with merged filter params. */
export function applyFilters(query, changes) {
    const next = { ...query, ...changes };
    const spec = Object.fromEntries(Object.entries(next.spec ?? {}).filter(([, v]) => Array.isArray(v) && v.length));
    const q = new URLSearchParams(window.location.search).get('q');
    const params = cleanParams({
        q: q ?? undefined,
        min_price: next.min_price,
        max_price: next.max_price,
        status: next.status,
        brand: next.brand,
        spec: Object.keys(spec).length ? spec : undefined,
        sort: next.sort === 'default' ? undefined : next.sort,
        limit: Number(next.limit) === 20 ? undefined : next.limit,
    });
    router.get(window.location.pathname, params, { preserveScroll: true, preserveState: true });
}

export default function Filters({ filters, query, onDone }) {
    const [min, setMin] = useState(query.min_price ?? '');
    const [max, setMax] = useState(query.max_price ?? '');

    const apply = (changes) => {
        applyFilters(query, changes);
        onDone?.();
    };

    const toggleIn = (list, value) => (list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);
    const spec = query.spec ?? {};

    return (
        <div>
            <Panel title="Price Range">
                <p className="mb-3 text-xs text-slate-500">৳{filters.priceRange.min.toLocaleString()} – ৳{filters.priceRange.max.toLocaleString()}</p>
                <div className="flex items-center gap-2">
                    <input type="number" className="input" placeholder="Min" value={min} onChange={(e) => setMin(e.target.value)} aria-label="Minimum price" />
                    <span>-</span>
                    <input type="number" className="input" placeholder="Max" value={max} onChange={(e) => setMax(e.target.value)} aria-label="Maximum price" />
                </div>
                <button onClick={() => apply({ min_price: min, max_price: max })} className="btn-primary btn-sm mt-3 w-full">Apply</button>
            </Panel>

            <Panel title="Availability">
                {Object.entries(filters.statuses).map(([value, label]) => (
                    <label key={value} className="mb-2 flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" className="accent-brand-700" checked={query.status.includes(value)} onChange={() => apply({ status: toggleIn(query.status, value) })} />
                        {label}
                    </label>
                ))}
            </Panel>

            {filters.brands.length > 0 && (
                <Panel title="Brand">
                    <div className="max-h-56 overflow-y-auto">
                        {filters.brands.map((b) => (
                            <label key={b.id} className="mb-2 flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" className="accent-brand-700" checked={query.brand.includes(b.slug)} onChange={() => apply({ brand: toggleIn(query.brand, b.slug) })} />
                                {b.name}
                            </label>
                        ))}
                    </div>
                </Panel>
            )}

            {filters.facets.map((f) => (
                <Panel key={f.name} title={f.name}>
                    <div className="max-h-56 overflow-y-auto">
                        {f.values.map((v) => {
                            const current = spec[f.name] ?? [];
                            return (
                                <label key={v} className="mb-2 flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" className="accent-brand-700" checked={current.includes(v)} onChange={() => apply({ spec: { ...spec, [f.name]: toggleIn(current, v) } })} />
                                    {v}
                                </label>
                            );
                        })}
                    </div>
                </Panel>
            ))}
        </div>
    );
}
