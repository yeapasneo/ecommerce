import { Link, router, useForm, usePage } from '@inertiajs/react';
import { EmptyState, Panel, Toggle } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { date, money, statusColor, titleCase } from '../../../lib/format';

function ContactRow({ icon, children }) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <Icon name={icon} className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
            <div className="min-w-0 break-words">{children}</div>
        </div>
    );
}

function PointsPanel({ customer, points, pointTypes, referredBy }) {
    const form = useForm({ points: '', note: '' });
    return (
        <Panel title="Loyalty Points" actions={<span className="rounded-full bg-primary-50 px-2.5 py-0.5 text-sm font-semibold text-primary-700">{customer.points}</span>}>
            {referredBy && <p className="mb-3 text-sm text-slate-500">Invited by <Link href={`/admin/customers/${referredBy.id}`} className="text-primary-700 hover:underline">{referredBy.name}</Link></p>}
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/admin/customers/${customer.id}/points`, { preserveScroll: true, onSuccess: () => form.reset() }); }} className="mb-4 grid grid-cols-[110px_1fr_auto] gap-2">
                <input className="input" type="number" value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} placeholder="+50 / −20" aria-label="Points" />
                <input className="input" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Reason" aria-label="Reason" />
                <button disabled={form.processing || !form.data.points} className="btn-primary">Save</button>
                {(form.errors.points || form.errors.note) && <p className="col-span-3 text-xs text-red-600">{form.errors.points || form.errors.note}</p>}
            </form>
            <ul className="divide-y divide-slate-100 text-sm">
                {points.length === 0 && <li className="py-2 text-slate-500">No points yet.</li>}
                {points.map((p) => (
                    <li key={p.id} className="flex items-center justify-between gap-2 py-1.5">
                        <span className="min-w-0 truncate">{pointTypes[p.type] ?? p.type}{p.order ? ` · #${p.order.order_number}` : p.note ? ` · ${p.note}` : ''}<span className="block text-xs text-slate-500">{date(p.created_at)}</span></span>
                        <span className={`shrink-0 font-semibold ${p.points > 0 ? 'text-green-700' : 'text-red-600'}`}>{p.points > 0 ? `+${p.points}` : p.points}</span>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

export default function Show({ customer, orders, points = null, pointTypes = {}, referredBy = null }) {
    const { auth } = usePage().props;
    const self = auth.user.id === customer.id;
    const update = (data) => router.put(`/admin/customers/${customer.id}`, { is_active: customer.is_active, is_admin: customer.is_admin, ...data }, { preserveScroll: true });
    const counted = orders.filter((o) => o.status !== 'cancelled');
    const spent = counted.reduce((s, o) => s + o.total, 0);

    return (
        <AdminLayout title={customer.name} subtitle={`Customer since ${date(customer.created_at)}`} back="/admin/customers">
            <div className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {[['receipt', 'Orders', orders.length], ['card', 'Total spent', money(spent)], ['percent', 'Average order', money(counted.length ? spent / counted.length : 0)]].map(([icon, label, value]) => (
                            <div key={label} className="panel flex items-center gap-4 p-5">
                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary-50 text-primary-700"><Icon name={icon} /></span>
                                <div><p className="text-sm text-slate-500">{label}</p><p className="text-xl font-bold text-slate-900">{value}</p></div>
                            </div>
                        ))}
                    </div>
                    <Panel title="Orders" bodyClassName="overflow-x-auto pb-2">
                        {orders.length === 0 ? (
                            <EmptyState icon="receipt" title="No orders yet" text="This customer hasn't placed an order." />
                        ) : (
                            <table className="table-admin min-w-[480px] border-t border-slate-100">
                                <thead><tr><th className="pl-6">Order</th><th>Date</th><th>Status</th><th className="pr-6 text-right">Total</th></tr></thead>
                                <tbody>
                                    {orders.map((o) => (
                                        <tr key={o.id}>
                                            <td className="pl-6"><Link href={`/admin/orders/${o.id}`} className="font-semibold text-primary-700 hover:underline">#{o.order_number}</Link></td>
                                            <td>{date(o.created_at)}</td>
                                            <td><span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${statusColor(o.status)}`}>{titleCase(o.status)}</span></td>
                                            <td className="pr-6 text-right font-medium">{money(o.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Customer Information">
                        <div className="mb-5 flex items-center gap-3 border-b border-slate-200 pb-5">
                            <span className="grid h-11 w-11 place-items-center rounded-full bg-primary-100 font-semibold text-primary-700">{customer.name?.[0]?.toUpperCase()}</span>
                            <div className="min-w-0">
                                <p className="truncate font-medium text-slate-900">{customer.name}</p>
                                <p className="text-xs text-slate-500">{customer.is_admin ? 'Administrator' : 'Customer'} · {customer.is_active ? 'Active' : 'Blocked'}</p>
                            </div>
                        </div>
                        <p className="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">Contact details</p>
                        <div className="space-y-2">
                            <ContactRow icon="mail"><a href={`mailto:${customer.email}`} className="hover:text-primary-700">{customer.email}</a></ContactRow>
                            {customer.phone && <ContactRow icon="phone">{customer.phone}</ContactRow>}
                            {customer.address && <ContactRow icon="pin">{customer.address}{customer.city && `, ${customer.city}`}</ContactRow>}
                        </div>
                    </Panel>

                    {points && <PointsPanel customer={customer} points={points} pointTypes={pointTypes} referredBy={referredBy} />}

                    <Panel title="Account Access">
                        {self ? (
                            <p className="text-sm text-slate-500">This is your own account, so its access can't be changed here.</p>
                        ) : (
                            <div className="space-y-5">
                                <Toggle label="Block account" description="Blocked users are logged out and can't sign in or place orders." checked={!customer.is_active} onChange={(v) => update({ is_active: !v })} />
                                {auth.user.permissions?.includes('staff') && (
                                    <Toggle label="Admin access" description="Gives Manager access to this panel. Change roles on the Staff page." checked={customer.is_admin} onChange={(v) => confirm(v ? 'Give this user Manager access to the admin panel?' : 'Remove admin access?') && update({ is_admin: v })} />
                                )}
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </AdminLayout>
    );
}
