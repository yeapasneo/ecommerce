import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, Dropdown, EmptyState, Pill, RowMenu, SearchBox, Tabs } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, dateTime, money, statusColor, titleCase } from '../../../lib/format';

export default function Index({ orders, filters, statuses, paymentStatuses, statusCounts }) {
    const [f, setF] = useState({ search: '', status: '', payment_status: '', from: '', to: '', ...filters });
    const apply = (next = f) => router.get('/admin/orders', cleanParams({ ...next, per_page: orders.per_page === 20 ? undefined : orders.per_page, page: undefined }), { preserveState: true, preserveScroll: true });
    const update = (patch) => {
        const next = { ...f, ...patch };
        setF(next);
        apply(next);
    };
    const total = Object.values(statusCounts).reduce((a, b) => a + Number(b), 0);
    const activeFilters = [f.payment_status, f.from, f.to].filter(Boolean).length;
    const filtered = f.search || f.status || activeFilters;

    return (
        <AdminLayout title="Orders" header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <h1 className="mr-auto flex items-center gap-2 text-xl font-semibold text-slate-900">
                    All Orders <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{total.toLocaleString()}</span>
                </h1>
                <SearchBox value={f.search} onChange={(v) => setF({ ...f, search: v })} onSubmit={() => apply()} placeholder="Order #, name, phone, email…" />
            </div>

            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="mr-auto min-w-0 max-w-full">
                    <Tabs
                        value={f.status}
                        onChange={(v) => update({ status: v })}
                        tabs={[{ value: '', label: 'All orders', count: total }, ...statuses.map((s) => ({ value: s, label: titleCase(s), count: statusCounts[s] ?? 0 }))]}
                    />
                </div>
                <Dropdown
                    width="w-72"
                    button={({ toggle }) => (
                        <button type="button" onClick={toggle} className="btn-outline h-10 font-medium">
                            <Icon name="filter" className="h-4 w-4" /> {activeFilters ? `Filters (${activeFilters})` : 'Show All'} <Icon name="chevronDown" className="h-4 w-4" />
                        </button>
                    )}
                >
                    <div className="grid gap-3 p-2">
                        <label className="text-sm">
                            <span className="label">Payment status</span>
                            <select className="input" value={f.payment_status} onChange={(e) => update({ payment_status: e.target.value })}>
                                <option value="">Any payment status</option>
                                {paymentStatuses.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
                            </select>
                        </label>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="text-sm"><span className="label">From</span><input type="date" className="input" value={f.from} onChange={(e) => update({ from: e.target.value })} /></label>
                            <label className="text-sm"><span className="label">To</span><input type="date" className="input" value={f.to} onChange={(e) => update({ to: e.target.value })} /></label>
                        </div>
                        {activeFilters > 0 && <button type="button" data-close onClick={() => update({ payment_status: '', from: '', to: '' })} className="text-left text-sm font-medium text-primary-700 hover:underline">Clear filters</button>}
                    </div>
                </Dropdown>
            </div>

            <div className="panel overflow-hidden">
                {orders.data.length === 0 ? (
                    <EmptyState icon={filtered ? 'search' : 'receipt'} title={filtered ? 'No orders match' : 'No Orders Yet'} text={filtered ? 'Try a different search or clear the filters.' : 'Orders from your storefront will show up here.'} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[920px]">
                            <thead><tr><th className="pl-6">Order</th><th>Customer</th><th>Placed</th><th>Items</th><th>Payment</th><th>Status</th><th>Total</th><th className="w-px" /></tr></thead>
                            <tbody>
                                {orders.data.map((o) => (
                                    <tr key={o.id}>
                                        <td className="pl-6"><Link href={`/admin/orders/${o.id}`} className="font-semibold text-primary-700 hover:underline">#{o.order_number}</Link></td>
                                        <td><p className="font-medium text-slate-900">{o.first_name} {o.last_name}</p><p className="text-xs text-slate-500">{o.phone}</p></td>
                                        <td className="whitespace-nowrap text-xs text-slate-600">{dateTime(o.created_at)}</td>
                                        <td><Pill>{o.items_count}</Pill></td>
                                        <td>
                                            <span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${statusColor(o.payment_status)}`}>{titleCase(o.payment_status)}</span>
                                            <p className="mt-0.5 text-xs uppercase text-slate-500">{o.payment_method}</p>
                                        </td>
                                        <td>
                                            <span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${statusColor(o.status)}`}>{titleCase(o.status)}</span>
                                            {o.courier && <p className="mt-0.5 flex items-center gap-1 text-xs text-slate-500"><Icon name="truck" className="h-3 w-3" /> {o.courier === 'pathao' ? 'Pathao' : 'Steadfast'} · {titleCase(o.courier_status ?? '')}</p>}
                                        </td>
                                        <td className="whitespace-nowrap font-semibold">{money(o.total)}</td>
                                        <td className="pr-6">
                                            <div className="flex items-center justify-end gap-2">
                                                <RowMenu items={[
                                                    { label: 'View order', icon: 'receipt', href: `/admin/orders/${o.id}` },
                                                    { label: 'Print invoice', icon: 'print', href: `/admin/orders/${o.id}/invoice`, external: true },
                                                ]} />
                                                <Link href={`/admin/orders/${o.id}`} className="btn-outline h-9 px-3 font-medium">View</Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <AdminPagination meta={orders} params={cleanParams(f)} />
            </div>
        </AdminLayout>
    );
}
