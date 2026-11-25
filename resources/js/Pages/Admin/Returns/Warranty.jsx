import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState, Panel } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { date, money, statusColor } from '../../../lib/format';

export default function Warranty({ serial, results }) {
    const [q, setQ] = useState(serial ?? '');

    return (
        <AdminLayout title="Warranty Lookup" subtitle="Find who bought a unit and whether it is still under warranty, by serial or IMEI number" back="/admin/returns">
            <form onSubmit={(e) => { e.preventDefault(); router.get('/admin/warranty', { serial: q }); }} className="panel mb-6 flex gap-2 p-4">
                <div className="relative flex-1">
                    <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input className="input h-11 pl-9 font-mono" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Serial or IMEI number" aria-label="Serial or IMEI" autoFocus />
                </div>
                <button className="btn-primary h-11">Look up</button>
            </form>

            {serial && results.length === 0 && <div className="panel"><EmptyState icon="search" title="Not found" text="No sold item has this serial number. Serial numbers are added on each order's page." /></div>}

            <div className="space-y-4">
                {results.map((r) => (
                    <Panel key={r.item.id} title={r.item.name + (r.item.variant_name ? ` (${r.item.variant_name})` : '')} actions={
                        r.warranty.months ? (
                            <span className={`badge rounded-md ${r.warranty.valid ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>{r.warranty.valid ? `Under warranty · ${r.warranty.days_left} days left` : 'Warranty expired'}</span>
                        ) : <span className="badge rounded-md bg-slate-100 text-slate-600">No warranty</span>
                    }>
                        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt className="text-slate-500">Serials on this sale</dt><dd className="font-mono text-xs">{r.item.serials?.join(', ')}</dd></div>
                            <div><dt className="text-slate-500">Order</dt><dd><Link href={`/admin/orders/${r.order.id}`} className="text-primary-700 hover:underline">#{r.order.order_number}</Link> · {money(r.item.price)}</dd></div>
                            <div><dt className="text-slate-500">Customer</dt><dd>{r.order.first_name} {r.order.last_name}<span className="block text-xs text-slate-500">{r.order.phone}</span></dd></div>
                            <div>
                                <dt className="text-slate-500">Warranty</dt>
                                <dd>{r.warranty.months ? `${r.warranty.months} months` : '—'}{r.warranty.delivered && <span className="block text-xs text-slate-500">Delivered {date(r.warranty.delivered)}{r.warranty.ends && ` · ends ${date(r.warranty.ends)}`}</span>}{!r.warranty.delivered && <span className="block text-xs text-amber-600">Not delivered yet</span>}</dd>
                            </div>
                        </dl>
                        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 text-sm">
                            {r.claims.length > 0 ? r.claims.map((c) => <Link key={c.id} href={`/admin/returns/${c.id}`} className={`badge rounded-md ${statusColor(c.status)}`}>{c.number} · {c.type}</Link>) : <span className="text-slate-500">No earlier claims.</span>}
                            <Link href={`/admin/orders/${r.order.id}/returns/create`} className="btn-outline btn-sm ml-auto"><Icon name="plus" className="h-4 w-4" /> Open a claim</Link>
                        </div>
                    </Panel>
                ))}
            </div>
        </AdminLayout>
    );
}
