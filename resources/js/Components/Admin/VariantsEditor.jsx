import { useState } from 'react';
import Icon from '../Icon';
import { Panel } from './UI';

const key = (options) => JSON.stringify(Object.entries(options).sort());

/** Every combination of the option values, e.g. Storage × Colour. */
function combinations(defs) {
    const usable = defs.filter((d) => d.name.trim() && d.values.some((v) => v.trim()));
    return usable.reduce(
        (acc, d) => acc.flatMap((combo) => d.values.map((v) => v.trim()).filter(Boolean).map((v) => ({ ...combo, [d.name.trim()]: v }))),
        usable.length ? [{}] : [],
    );
}

/** Option definitions plus a table of combinations with their own SKU, price and stock. */
export default function VariantsEditor({ variants, onChange, errors, baseSku, basePrice }) {
    const [defs, setDefs] = useState(() => {
        const names = [...new Set(variants.flatMap((v) => Object.keys(v.options ?? {})))];
        return names.length
            ? names.map((n) => ({ name: n, values: [...new Set(variants.map((v) => v.options?.[n]).filter(Boolean))] }))
            : [];
    });
    const [draft, setDraft] = useState({});

    const setDef = (i, patch) => setDefs(defs.map((d, j) => (j === i ? { ...d, ...patch } : d)));
    const addValue = (i) => {
        const v = (draft[i] ?? '').trim();
        if (!v || defs[i].values.includes(v)) return;
        setDef(i, { values: [...defs[i].values, v] });
        setDraft({ ...draft, [i]: '' });
    };
    const generate = () => {
        const existing = Object.fromEntries(variants.map((v) => [key(v.options), v]));
        onChange(combinations(defs).map((options, i) => existing[key(options)] ?? {
            id: null,
            options,
            sku: baseSku ? `${baseSku}-${Object.values(options).join('-').replace(/[^A-Za-z0-9]+/g, '').toUpperCase()}`.slice(0, 100) : '',
            price: basePrice || '',
            regular_price: '',
            stock: 0,
            is_active: true,
            _new: i,
        }));
    };
    const setVariant = (i, patch) => onChange(variants.map((v, j) => (j === i ? { ...v, ...patch } : v)));
    const err = (i, f) => errors[`variants.${i}.${f}`];
    const totalStock = variants.filter((v) => v.is_active).reduce((n, v) => n + (Number(v.stock) || 0), 0);

    return (
        <Panel title="Options & Variants" icon="tag" defaultOpen={variants.length > 0}>
            <p className="-mt-2 mb-4 text-sm text-slate-500">For products sold in different sizes, colours or storage. Each combination gets its own SKU, price and stock. The product shows the lowest price as "from".</p>

            <div className="space-y-3">
                {defs.map((d, i) => (
                    <div key={i} className="rounded-xl border border-slate-200 p-3">
                        <div className="flex gap-2">
                            <input className="input w-44" value={d.name} onChange={(e) => setDef(i, { name: e.target.value })} placeholder="Option, e.g. Storage" aria-label="Option name" />
                            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 px-2 py-1">
                                {d.values.map((v) => (
                                    <span key={v} className="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-sm text-primary-700">
                                        {v}
                                        <button type="button" onClick={() => setDef(i, { values: d.values.filter((x) => x !== v) })} aria-label={`Remove ${v}`}><Icon name="x" className="h-3 w-3" /></button>
                                    </span>
                                ))}
                                <input className="min-w-24 flex-1 py-1 text-sm outline-none" value={draft[i] ?? ''} placeholder={d.values.length ? 'Add value' : 'Values, e.g. 128GB — press Enter'}
                                    onChange={(e) => setDraft({ ...draft, [i]: e.target.value })}
                                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addValue(i); } }}
                                    onBlur={() => addValue(i)} aria-label={`${d.name || 'Option'} values`} />
                            </div>
                            <button type="button" onClick={() => setDefs(defs.filter((_, j) => j !== i))} className="icon-btn h-[38px] w-[38px] shrink-0 hover:text-red-600" aria-label="Remove option"><Icon name="trash" className="h-4 w-4" /></button>
                        </div>
                    </div>
                ))}
                <div className="flex flex-wrap gap-2">
                    {defs.length < 3 && <button type="button" onClick={() => setDefs([...defs, { name: defs.length ? '' : 'Storage', values: [] }])} className="btn-outline"><Icon name="plus" className="h-4 w-4" /> Add option</button>}
                    {defs.length > 0 && <button type="button" onClick={generate} className="btn-primary"><Icon name="refresh" className="h-4 w-4" /> Generate combinations</button>}
                    {variants.length > 0 && <button type="button" onClick={() => { onChange([]); setDefs([]); }} className="btn-outline text-red-600">Remove all variants</button>}
                </div>
            </div>

            {variants.length > 0 && (
                <div className="mt-5 overflow-x-auto">
                    <table className="table-admin min-w-[720px]">
                        <thead><tr><th className="pl-0">Variant</th><th>SKU</th><th className="w-32">Price (৳)</th><th className="w-32">Regular (৳)</th><th className="w-24">Stock</th><th className="w-20">On sale</th><th className="w-px" /></tr></thead>
                        <tbody>
                            {variants.map((v, i) => (
                                <tr key={v.id ?? `n${key(v.options)}`}>
                                    <td className="pl-0 font-medium text-slate-900">{Object.values(v.options).join(' / ')}</td>
                                    <td><input className="input h-9 py-1 font-mono text-xs" value={v.sku ?? ''} onChange={(e) => setVariant(i, { sku: e.target.value })} aria-label="Variant SKU" /></td>
                                    <td>
                                        <input className="input h-9 py-1" type="number" min="0" step="1" value={v.price} onChange={(e) => setVariant(i, { price: e.target.value })} aria-label="Variant price" />
                                        {err(i, 'price') && <p className="mt-1 text-xs text-red-600">{err(i, 'price')}</p>}
                                    </td>
                                    <td><input className="input h-9 py-1" type="number" min="0" step="1" value={v.regular_price ?? ''} onChange={(e) => setVariant(i, { regular_price: e.target.value })} aria-label="Variant regular price" /></td>
                                    <td><input className="input h-9 py-1" type="number" min="0" step="1" value={v.stock} onChange={(e) => setVariant(i, { stock: e.target.value })} aria-label="Variant stock" /></td>
                                    <td><input type="checkbox" className="h-4 w-4 accent-primary-600" checked={!!v.is_active} onChange={(e) => setVariant(i, { is_active: e.target.checked })} aria-label="Variant on sale" /></td>
                                    <td><button type="button" onClick={() => onChange(variants.filter((_, j) => j !== i))} className="icon-btn h-9 w-9 hover:text-red-600" aria-label="Remove variant"><Icon name="x" className="h-4 w-4" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-2 text-xs text-slate-500">{variants.length} variants · {totalStock} in stock in total. The product's price and stock are updated from these when you save.</p>
                </div>
            )}
        </Panel>
    );
}
