import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, dateTime, money, statusColor, titleCase } from '../../../lib/format';

export default function Index({ orders, filters, statuses, paymentStatuses }) {
    const [f, setF] = useState({ search: '', status: '', payment_status: '', from: '', to: '', ...filters });
    const apply = (next = f) => router.get('/admin/orders', cleanParams(next), { preserveState: true, preserveScroll: true });
    const set = (k) => (e) => {
        const next = { ...f, [k]: e.target.value };
        setF(next);
        if (k !== 'search') apply(next);
    };

    return (
        <AdminLayout title="Orders">
            <div className="mb-4 flex flex-wrap gap-2">
                {['', ...statuses].map((s) => (
                    <button key={s} onClick={() => { const next = { ...f, status: s }; setF(next); apply(next); }} className={`rounded-full px-3 py-1 text-sm ${f.status === s ? 'bg-brand-700 text-white' : 'bg-white ring-1 ring-slate-200 hover:ring-brand-500'}`}>
                        {s ? titleCase(s) : 'All'}
                    </button>
                ))}
            </div>
            <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <input className="input" placeholder="Order #, name, phone, email…" value={f.search} onChange={set('search')} aria-label="Search orders" />
                <select className="input" value={f.payment_status} onChange={set('payment_status')} aria-label="Payment status">
                    <option value="">Any payment status</option>
                    {paymentStatuses.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
                </select>
                <input type="date" className="input" value={f.from} onChange={set('from')} aria-label="From date" />
                <input type="date" className="input" value={f.to} onChange={set('to')} aria-label="To date" />
            </form>

            <div className="card overflow-x-auto">
                <table className="table-x min-w-[860px]">
                    <thead><tr><th>Order</th><th>Customer</th><th>Placed</th><th>Items</th><th>Payment</th><th>Status</th><th className="text-right">Total</th></tr></thead>
                    <tbody>
                        {orders.data.map((o) => (
                            <tr key={o.id} className="cursor-pointer hover:bg-slate-50" onClick={() => router.visit(`/admin/orders/${o.id}`)}>
                                <td><Link href={`/admin/orders/${o.id}`} className="font-medium text-brand-600 hover:underline">#{o.order_number}</Link></td>
                                <td>{o.first_name} {o.last_name}<div className="text-xs text-slate-500">{o.phone}</div></td>
                                <td className="text-xs">{dateTime(o.created_at)}</td>
                                <td>{o.items_count}</td>
                                <td><span className={`badge ${statusColor(o.payment_status)}`}>{titleCase(o.payment_status)}</span><div className="text-xs text-slate-500">{o.payment_method.toUpperCase()}</div></td>
                                <td><span className={`badge ${statusColor(o.status)}`}>{titleCase(o.status)}</span></td>
                                <td className="text-right font-semibold">{money(o.total)}</td>
                            </tr>
                        ))}
                        {orders.data.length === 0 && <tr><td colSpan={7} className="py-10 text-center text-slate-500">No orders found.</td></tr>}
                    </tbody>
                </table>
            </div>
            <Pagination meta={orders} />
        </AdminLayout>
    );
}
