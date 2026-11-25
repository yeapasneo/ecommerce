import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, SearchBox, Tabs } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, date, money, statusColor } from '../../../lib/format';

export default function Index({ returns, filters, counts, statuses, types }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const go = (params) => router.get('/admin/returns', cleanParams({ ...filters, ...params }), { preserveState: true, preserveScroll: true });
    const open = ['requested', 'approved', 'received'].reduce((n, s) => n + (counts[s] ?? 0), 0);

    return (
        <AdminLayout title="Returns & Warranty" count={returns.total} subtitle="Return requests, refunds and warranty claims" actions={<Link href="/admin/warranty" className="btn-outline"><Icon name="search" className="h-4 w-4" /> Warranty lookup</Link>}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="mr-auto min-w-0">
                    <Tabs value={filters.status ?? ''} onChange={(v) => go({ status: v || null })} tabs={[{ value: '', label: 'Open', count: open }, ...Object.entries(statuses).map(([value, label]) => ({ value, label, count: counts[value] ?? 0 }))]} />
                </div>
                <select className="input h-10 w-auto" value={filters.type ?? ''} onChange={(e) => go({ type: e.target.value || null })} aria-label="Type">
                    <option value="">Returns and warranty</option>
                    {Object.entries(types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
                <SearchBox value={search} onChange={setSearch} onSubmit={() => go({ search })} placeholder="RMA, order or phone…" />
            </div>
            <div className="panel overflow-hidden">
                {returns.data.length === 0 ? <EmptyState icon="refresh" title="Nothing here" text="Customers request returns and warranty claims from their account. Staff can also open one from an order." /> : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[760px]">
                            <thead><tr><th className="pl-6">Request</th><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th className="pr-6 text-right">Refunded</th></tr></thead>
                            <tbody>
                                {returns.data.map((r) => (
                                    <tr key={r.id} className="cursor-pointer hover:bg-slate-50" onClick={() => router.get(`/admin/returns/${r.id}`)}>
                                        <td className="pl-6 font-medium text-primary-700">{r.number}<span className="block text-xs font-normal text-slate-500">{date(r.created_at)} · {r.items_count} item(s)</span></td>
                                        <td>#{r.order?.order_number}</td>
                                        <td>{r.order?.first_name} {r.order?.last_name}<span className="block text-xs text-slate-500">{r.order?.phone}</span></td>
                                        <td>{types[r.type]}</td>
                                        <td><span className={`badge rounded-md ${statusColor(r.status)}`}>{statuses[r.status]}</span></td>
                                        <td className="pr-6 text-right tabular-nums">{r.refund_amount > 0 ? money(r.refund_amount) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <AdminPagination meta={returns} params={cleanParams(filters)} />
            </div>
        </AdminLayout>
    );
}
