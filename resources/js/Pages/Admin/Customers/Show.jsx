import { Link, router, usePage } from '@inertiajs/react';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { date, money, statusColor, titleCase } from '../../../lib/format';

export default function Show({ customer, orders }) {
    const { auth } = usePage().props;
    const self = auth.user.id === customer.id;
    const update = (data) => router.put(`/admin/customers/${customer.id}`, { is_active: customer.is_active, is_admin: customer.is_admin, ...data }, { preserveScroll: true });
    const spent = orders.filter((o) => o.status !== 'cancelled').reduce((s, o) => s + o.total, 0);
    return (
        <AdminLayout title={customer.name} actions={<Link href="/admin/customers" className="btn-outline"><Icon name="chevronLeft" className="h-4 w-4" /> Customers</Link>}>
            <div className="grid gap-6 xl:grid-cols-[320px_1fr]">
                <div className="card h-fit space-y-2 p-5 text-sm">
                    <p className="text-lg font-semibold">{customer.name}</p>
                    <p>{customer.email}</p>
                    <p>{customer.phone}</p>
                    {customer.address && <p className="text-slate-600">{customer.address}, {customer.city}</p>}
                    <p className="text-slate-500">Joined {date(customer.created_at)}</p>
                    <p>Total spent: <b>{money(spent)}</b></p>
                    {!self && (
                        <div className="flex flex-wrap gap-2 pt-3">
                            <button onClick={() => update({ is_active: !customer.is_active })} className={customer.is_active ? 'btn-danger btn-sm' : 'btn-primary btn-sm'}>{customer.is_active ? 'Block account' : 'Unblock account'}</button>
                            <button onClick={() => confirm(customer.is_admin ? 'Remove admin access?' : 'Give this user full admin access?') && update({ is_admin: !customer.is_admin })} className="btn-outline btn-sm">{customer.is_admin ? 'Remove admin' : 'Make admin'}</button>
                        </div>
                    )}
                </div>
                <div className="card overflow-x-auto">
                    <h2 className="px-5 py-4 font-semibold">Orders ({orders.length})</h2>
                    <table className="table-x">
                        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th className="text-right">Total</th></tr></thead>
                        <tbody>
                            {orders.map((o) => (
                                <tr key={o.id}>
                                    <td><Link href={`/admin/orders/${o.id}`} className="text-brand-600 hover:underline">#{o.order_number}</Link></td>
                                    <td>{date(o.created_at)}</td>
                                    <td><span className={`badge ${statusColor(o.status)}`}>{titleCase(o.status)}</span></td>
                                    <td className="text-right">{money(o.total)}</td>
                                </tr>
                            ))}
                            {orders.length === 0 && <tr><td colSpan={4} className="py-8 text-center text-slate-500">No orders yet.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
