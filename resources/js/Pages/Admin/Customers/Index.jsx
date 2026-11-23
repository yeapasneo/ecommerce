import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, date, money } from '../../../lib/format';

export default function Index({ users, filters }) {
    const [f, setF] = useState({ search: '', role: '', ...filters });
    const apply = (next = f) => router.get('/admin/customers', cleanParams(next), { preserveState: true, preserveScroll: true });
    return (
        <AdminLayout title="Customers">
            <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="card mb-4 grid gap-3 p-4 sm:grid-cols-[1fr_200px_auto]">
                <input className="input" placeholder="Name, email or phone…" value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} aria-label="Search customers" />
                <select className="input" value={f.role} onChange={(e) => { const next = { ...f, role: e.target.value }; setF(next); apply(next); }} aria-label="Role">
                    <option value="">All users</option>
                    <option value="customer">Customers</option>
                    <option value="admin">Admins</option>
                </select>
                <button className="btn-primary">Search</button>
            </form>
            <div className="card overflow-x-auto">
                <table className="table-x min-w-[760px]">
                    <thead><tr><th>Name</th><th>Contact</th><th>Joined</th><th className="text-right">Orders</th><th className="text-right">Spent</th><th>Status</th></tr></thead>
                    <tbody>
                        {users.data.map((u) => (
                            <tr key={u.id}>
                                <td><Link href={`/admin/customers/${u.id}`} className="font-medium text-brand-600 hover:underline">{u.name}</Link>{u.is_admin && <span className="badge ml-2 bg-purple-100 text-purple-800">Admin</span>}</td>
                                <td>{u.email}<div className="text-xs text-slate-500">{u.phone}</div></td>
                                <td>{date(u.created_at)}</td>
                                <td className="text-right">{u.orders_count}</td>
                                <td className="text-right">{money(u.orders_sum_total)}</td>
                                <td><span className={`badge ${u.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'}`}>{u.is_active ? 'Active' : 'Blocked'}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination meta={users} />
        </AdminLayout>
    );
}
