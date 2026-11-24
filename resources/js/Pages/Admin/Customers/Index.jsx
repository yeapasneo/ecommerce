import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, Pill, RowMenu, SearchBox, Tabs } from '../../../Components/Admin/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, date, money } from '../../../lib/format';

export default function Index({ users, filters }) {
    const [f, setF] = useState({ search: '', role: '', ...filters });
    const apply = (next = f) => router.get('/admin/customers', cleanParams({ ...next, per_page: users.per_page === 20 ? undefined : users.per_page, page: undefined }), { preserveState: true, preserveScroll: true });

    return (
        <AdminLayout title="Customers" header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <h1 className="mr-auto flex items-center gap-2 text-xl font-semibold text-slate-900">
                    Customers <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{users.total.toLocaleString()}</span>
                </h1>
                <SearchBox value={f.search} onChange={(v) => setF({ ...f, search: v })} onSubmit={() => apply()} placeholder="Name, email or phone…" />
            </div>
            <div className="panel mb-4 px-4 py-3">
                <Tabs value={f.role} onChange={(v) => { const next = { ...f, role: v }; setF(next); apply(next); }} tabs={[{ value: '', label: 'All users' }, { value: 'customer', label: 'Customers' }, { value: 'admin', label: 'Admins' }]} />
            </div>
            <div className="panel overflow-hidden">
                {users.data.length === 0 ? (
                    <EmptyState icon="users" title="No customers found" text={f.search ? 'Try a different search.' : 'Customers who register will show up here.'} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[820px]">
                            <thead><tr><th className="pl-6">Customer</th><th>Contact</th><th>Joined</th><th>Orders</th><th>Spent</th><th>Status</th><th className="w-px" /></tr></thead>
                            <tbody>
                                {users.data.map((u) => (
                                    <tr key={u.id}>
                                        <td className="pl-6">
                                            <div className="flex items-center gap-3">
                                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">{u.name?.[0]?.toUpperCase()}</span>
                                                <Link href={`/admin/customers/${u.id}`} className="font-medium text-slate-900 hover:text-primary-700">{u.name}</Link>
                                                {u.is_admin && <span className="badge rounded-md bg-primary-50 text-primary-700 ring-1 ring-inset ring-current/20">Admin</span>}
                                            </div>
                                        </td>
                                        <td><p>{u.email}</p><p className="text-xs text-slate-500">{u.phone}</p></td>
                                        <td className="whitespace-nowrap">{date(u.created_at)}</td>
                                        <td><Pill>{u.orders_count}</Pill></td>
                                        <td className="whitespace-nowrap font-medium">{money(u.orders_sum_total)}</td>
                                        <td><span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${u.is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>{u.is_active ? 'Active' : 'Blocked'}</span></td>
                                        <td className="pr-6">
                                            <div className="flex items-center justify-end gap-2">
                                                <RowMenu items={[{ label: 'View profile', icon: 'user', href: `/admin/customers/${u.id}` }, u.email && { label: 'Send email', icon: 'mail', href: `mailto:${u.email}`, external: true }]} />
                                                <Link href={`/admin/customers/${u.id}`} className="btn-outline h-9 px-3 font-medium">View</Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <AdminPagination meta={users} params={cleanParams(f)} />
            </div>
        </AdminLayout>
    );
}
