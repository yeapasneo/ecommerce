import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState, Panel, Toggle } from '../../../Components/Admin/UI';
import { TextInput } from '../../../Components/Field';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { money } from '../../../lib/format';

const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

function Picker({ onPick }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const box = useRef(null);
    useEffect(() => {
        if (q.trim().length < 2) { setResults([]); return; }
        const t = setTimeout(() => fetch(`/admin/products/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((r) => setResults(r.filter((p, i, all) => all.findIndex((x) => x.id === p.id) === i))), 250);
        return () => clearTimeout(t);
    }, [q]);
    return (
        <div className="relative" ref={box}>
            <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input className="input h-11 pl-9" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search products to add…" aria-label="Search products" />
            {results.length > 0 && (
                <ul className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                    {results.map((p) => (
                        <li key={p.id}>
                            <button type="button" onClick={() => { onPick(p); setQ(''); setResults([]); }} className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-primary-50">
                                <span className="grid h-9 w-9 place-items-center rounded border border-slate-200 bg-white">{p.image_url && <img src={p.image_url} alt="" className="max-h-full max-w-full object-contain" />}</span>
                                <span className="min-w-0 flex-1 truncate text-sm">{p.name}</span>
                                <span className="text-sm font-medium">{money(p.price)}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Form({ sale }) {
    const now = new Date();
    const form = useForm(sale ?? { title: '', starts_at: local(now), ends_at: local(new Date(now.getTime() + 24 * 3600000)), is_active: true, items: [] });
    const d = form.data;
    const e = form.errors;
    const add = (p) => {
        if (d.items.some((i) => i.product_id === p.id)) return;
        form.setData('items', [...d.items, { product_id: p.id, sale_price: Math.round(p.price * 0.9), meta: { name: p.name, sku: p.sku, price: p.price, image_url: p.image_url, has_variants: !!p.variant_id } }]);
    };
    const setItem = (idx, patch) => form.setData('items', d.items.map((i, j) => (j === idx ? { ...i, ...patch } : i)));
    const submit = (ev) => {
        ev.preventDefault();
        form.transform((x) => ({ ...x, items: x.items.map(({ product_id, sale_price }) => ({ product_id, sale_price })) }));
        sale ? form.put(`/admin/flash-sales/${sale.id}`) : form.post('/admin/flash-sales');
    };
    const itemError = Object.entries(e).find(([k]) => k.startsWith('items'))?.[1];

    return (
        <AdminLayout
            title={sale ? 'Edit Flash Sale' : 'New Flash Sale'}
            back="/admin/flash-sales"
            actions={<>
                <Link href="/admin/flash-sales" className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</Link>
                <button form="flash-form" disabled={form.processing} className="btn-primary"><Icon name="check" className="h-4 w-4" /> Save</button>
            </>}
        >
            <form id="flash-form" onSubmit={submit} className="grid items-start gap-6 xl:grid-cols-[1fr_380px]">
                <Panel title="Products & Sale Prices">
                    <Picker onPick={add} />
                    {itemError && <p className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{itemError}</p>}
                    {d.items.length === 0 ? (
                        <EmptyState icon="gift" title="No products yet" text="Search above and add the products that go on sale." />
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="table-admin min-w-[560px]">
                                <thead><tr><th className="pl-0">Product</th><th>Normal price</th><th className="w-40">Sale price</th><th>Off</th><th className="w-px" /></tr></thead>
                                <tbody>
                                    {d.items.map((i, idx) => {
                                        const off = i.meta.price ? Math.round((1 - i.sale_price / i.meta.price) * 100) : 0;
                                        return (
                                            <tr key={i.product_id}>
                                                <td className="pl-0">
                                                    <p className="line-clamp-1 font-medium text-slate-900">{i.meta.name}</p>
                                                    <p className="text-xs text-slate-500">{i.meta.sku}{i.meta.has_variants && ' · all options get the same % off'}</p>
                                                </td>
                                                <td className="whitespace-nowrap">{money(i.meta.price)}</td>
                                                <td><input className="input h-9 py-1" type="number" min="1" step="1" value={i.sale_price} onChange={(ev) => setItem(idx, { sale_price: ev.target.value })} aria-label="Sale price" /></td>
                                                <td className={off > 0 ? 'font-medium text-green-700' : 'text-red-600'}>{off > 0 ? `${off}%` : 'not lower'}</td>
                                                <td><button type="button" onClick={() => form.setData('items', d.items.filter((_, j) => j !== idx))} className="icon-btn h-9 w-9 hover:text-red-600" aria-label="Remove"><Icon name="x" className="h-4 w-4" /></button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
                <Panel title="Sale Details">
                    <div className="grid gap-4">
                        <TextInput label="Title" value={d.title} onChange={(ev) => form.setData('title', ev.target.value)} error={e.title} placeholder="e.g. Weekend Flash Sale" />
                        <TextInput label="Starts" type="datetime-local" value={d.starts_at} onChange={(ev) => form.setData('starts_at', ev.target.value)} error={e.starts_at} />
                        <TextInput label="Ends" type="datetime-local" value={d.ends_at} onChange={(ev) => form.setData('ends_at', ev.target.value)} error={e.ends_at} />
                        <Toggle label="Active" description="Switch off to pause the sale without deleting it." checked={d.is_active} onChange={(v) => form.setData('is_active', v)} />
                        <p className="text-xs text-slate-500">Prices change on the storefront within a minute of the start and end times. Only prices lower than the normal price are used.</p>
                    </div>
                </Panel>
            </form>
        </AdminLayout>
    );
}
