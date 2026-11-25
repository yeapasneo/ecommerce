import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, SearchBox, Tabs } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, date, money } from '../../../lib/format';
import { PO_TONE } from './Show';

export default function Index({ orders, filters, statuses, counts }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const go = (params) => router.get('/admin/purchase-orders', cleanParams({ ...filters, ...params }), { preserveState: true, preserveScroll: true });

    return (
        <AdminLayout
            title="Purchase Orders"
            count={orders.total}
            subtitle="Stock bought from suppliers. Receiving a delivery adds it to stock."
            actions={
                <>
                    <Link href="/admin/suppliers" className="btn-outline"><Icon name="users" className="h-4 w-4" /> Suppliers</Link>
                    <Link href="/admin/purchase-orders/create" className="btn-primary"><Icon name="plus" className="h-4 w-4" /> New purchase order</Link>
                </>
            }
        >
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="mr-auto min-w-0"><Tabs value={filters.status ?? ''} onChange={(v) => go({ status: v || null })} tabs={[{ value: '', label: 'All' }, ...Object.entries(statuses).map(([value, label]) => ({ value, label, count: counts[value] ?? 0 }))]} /></div>
                <SearchBox value={search} onChange={setSearch} onSubmit={() => go({ search })} placeholder="PO number or supplier…" />
            </div>
            <div className="panel overflow-hidden">
                {orders.data.length === 0 ? (
                    <EmptyState icon="box" title="No purchase orders" text="Create one when you order stock from a supplier." action={<Link href="/admin/purchase-orders/create" className="btn-primary"><Icon name="plus" className="h-4 w-4" /> New purchase order</Link>} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[720px]">
                            <thead><tr><th className="pl-6">Number</th><th>Supplier</th><th>Items</th><th>Expected</th><th>Status</th><th className="pr-6 text-right">Total</th></tr></thead>
                            <tbody>
                                {orders.data.map((o) => (
                                    <tr key={o.id} className="cursor-pointer hover:bg-slate-50" onClick={() => router.get(`/admin/purchase-orders/${o.id}`)}>
                                        <td className="pl-6 font-medium text-primary-700">{o.number}<span className="block text-xs font-normal text-slate-500">{date(o.created_at)}</span></td>
                                        <td>{o.supplier?.name ?? '—'}</td>
                                        <td>{o.items_count}</td>
                                        <td>{o.expected_at ? date(o.expected_at) : '—'}</td>
                                        <td><span className={`badge rounded-md ${PO_TONE[o.status]}`}>{statuses[o.status]}</span></td>
                                        <td className="pr-6 text-right tabular-nums">{money(o.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <AdminPagination meta={orders} params={cleanParams(filters)} />
            </div>
        </AdminLayout>
    );
}
