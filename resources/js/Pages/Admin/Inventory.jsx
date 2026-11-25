import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, SearchBox, Tabs } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { cleanParams, dateTime } from '../../lib/format';

function SetStock({ row }) {
    const [value, setValue] = useState(null);
    if (value === null) return <button type="button" onClick={() => setValue(String(row.stock))} className="btn-outline btn-sm">Set stock</button>;
    const save = () => router.post('/admin/inventory/adjust', { product_id: row.product_id, variant_id: row.variant_id, stock: value, note: 'Stock count' }, { preserveScroll: true, onSuccess: () => setValue(null) });
    return (
        <span className="inline-flex items-center gap-1">
            <input type="number" min="0" className="input h-8 w-20 py-1" value={value} onChange={(e) => setValue(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), save())} aria-label="Counted stock" autoFocus />
            <button type="button" onClick={save} className="btn-primary btn-sm px-2" aria-label="Save"><Icon name="check" className="h-4 w-4" /></button>
            <button type="button" onClick={() => setValue(null)} className="icon-btn h-8 w-8" aria-label="Cancel"><Icon name="x" className="h-4 w-4" /></button>
        </span>
    );
}

export default function Inventory({ tab, threshold, low, movements, waiting = [], reasons, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const go = (params) => router.get('/admin/inventory', cleanParams({ tab, ...filters, ...params }), { preserveState: true, preserveScroll: true });

    return (
        <AdminLayout
            title="Inventory"
            subtitle={`Products at or below ${threshold} in stock, and every stock change. Change the level in Settings → General.`}
            actions={<Link href="/admin/purchase-orders/create" className="btn-primary"><Icon name="plus" className="h-4 w-4" /> New purchase order</Link>}
        >
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="mr-auto"><Tabs value={tab} onChange={(v) => router.get('/admin/inventory', { tab: v })} tabs={[{ value: 'low', label: 'Low stock', count: tab === 'low' ? low.length : undefined }, { value: 'waiting', label: 'Waiting customers' }, { value: 'history', label: 'Stock history' }]} /></div>
                {tab === 'history' && (
                    <>
                        <select className="input h-10 w-auto" value={filters.reason ?? ''} onChange={(e) => go({ reason: e.target.value || null })} aria-label="Reason">
                            <option value="">All changes</option>
                            {Object.entries(reasons).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </select>
                        <SearchBox value={search} onChange={setSearch} onSubmit={() => go({ search })} placeholder="Product name or SKU…" />
                    </>
                )}
            </div>

            {tab === 'low' && (
                <div className="panel overflow-hidden">
                    {low.length === 0 ? <EmptyState icon="box" title="Nothing is running low" text={`Every product on sale has more than ${threshold} in stock.`} /> : (
                        <div className="overflow-x-auto">
                            <table className="table-admin min-w-[760px]">
                                <thead><tr><th className="pl-6">Product</th><th>SKU</th><th className="text-right">In stock</th><th className="text-right">Sold (30 days)</th><th className="pr-6 text-right" /></tr></thead>
                                <tbody>
                                    {low.map((r) => (
                                        <tr key={`${r.product_id}-${r.variant_id ?? 0}`}>
                                            <td className="pl-6">
                                                <div className="flex items-center gap-3">
                                                    <img src={r.image_url} alt="" className="h-9 w-9 rounded border border-slate-100 object-contain" loading="lazy" />
                                                    <Link href={`/admin/products/${r.product_id}/edit`} className="font-medium text-slate-900 hover:text-primary-700">{r.name}</Link>
                                                </div>
                                            </td>
                                            <td className="font-mono text-xs">{r.sku}</td>
                                            <td className={`text-right font-semibold tabular-nums ${r.stock === 0 ? 'text-red-600' : 'text-amber-600'}`}>{r.stock}</td>
                                            <td className="text-right tabular-nums">{r.sold30}</td>
                                            <td className="pr-6">
                                                <div className="flex justify-end gap-2">
                                                    <SetStock row={r} />
                                                    <Link href={`/admin/purchase-orders/create?product=${r.product_id}${r.variant_id ? `&variant=${r.variant_id}` : ''}`} className="btn-outline btn-sm">Reorder</Link>
                                                    <Link href={`/admin/inventory?tab=history&product=${r.product_id}`} className="icon-btn h-8 w-8" aria-label="Stock history"><Icon name="clock" className="h-4 w-4" /></Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {tab === 'waiting' && (
                <div className="panel overflow-hidden">
                    {waiting.length === 0 ? <EmptyState icon="bell" title="Nobody is waiting" text="Shoppers who tap “Notify me” on an out-of-stock product show up here. They're told automatically when it's back." /> : (
                        <div className="overflow-x-auto">
                            <table className="table-admin min-w-[640px]">
                                <thead><tr><th className="pl-6">Product</th><th className="text-right">Waiting</th><th className="text-right">In stock</th><th>Latest request</th><th className="pr-6" /></tr></thead>
                                <tbody>
                                    {waiting.map((w) => (
                                        <tr key={`${w.product_id}-${w.variant_id ?? 0}`}>
                                            <td className="pl-6"><Link href={`/admin/products/${w.product_id}/edit`} className="font-medium hover:text-primary-700">{w.product?.name}{w.variant && <span className="text-primary-700"> · {w.variant.name}</span>}</Link></td>
                                            <td className="text-right font-semibold tabular-nums">{w.people}</td>
                                            <td className="text-right tabular-nums">{w.variant ? w.variant.stock : w.product?.stock}</td>
                                            <td className="text-xs text-slate-500">{dateTime(w.latest)}</td>
                                            <td className="pr-6 text-right"><Link href={`/admin/purchase-orders/create?product=${w.product_id}${w.variant_id ? `&variant=${w.variant_id}` : ''}`} className="btn-outline btn-sm">Reorder</Link></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {tab === 'history' && movements && (
                <div className="panel overflow-hidden">
                    {movements.data.length === 0 ? <EmptyState icon="clock" title="No stock changes yet" text="Sales, cancellations, returns, deliveries and manual changes appear here." /> : (
                        <div className="overflow-x-auto">
                            <table className="table-admin min-w-[820px]">
                                <thead><tr><th className="pl-6">When</th><th>Product</th><th className="text-right">Change</th><th className="text-right">Stock after</th><th>Why</th><th className="pr-6">By</th></tr></thead>
                                <tbody>
                                    {movements.data.map((m) => (
                                        <tr key={m.id}>
                                            <td className="whitespace-nowrap pl-6 text-xs text-slate-500">{dateTime(m.created_at)}</td>
                                            <td><Link href={`/admin/products/${m.product_id}/edit`} className="hover:text-primary-700">{m.product?.name}{m.variant && <span className="text-primary-700"> · {m.variant.name}</span>}</Link></td>
                                            <td className={`text-right font-semibold tabular-nums ${m.change > 0 ? 'text-green-700' : 'text-red-600'}`}>{m.change > 0 ? `+${m.change}` : m.change}</td>
                                            <td className="text-right tabular-nums">{m.stock_after}</td>
                                            <td>
                                                {reasons[m.reason] ?? m.reason}
                                                {m.reference_label && <Link href={m.reference_label.url} className="ml-1 text-primary-700 hover:underline">{m.reference_label.label}</Link>}
                                                {m.note && <span className="block text-xs text-slate-500">{m.note}</span>}
                                            </td>
                                            <td className="pr-6 text-xs text-slate-500">{m.user?.name ?? 'Customer / system'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <AdminPagination meta={movements} params={cleanParams({ tab, ...filters })} />
                </div>
            )}
        </AdminLayout>
    );
}
