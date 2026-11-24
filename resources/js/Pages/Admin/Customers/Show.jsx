import { Link, router, usePage } from '@inertiajs/react';
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

export default function Show({ customer, orders }) {
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

                    <Panel title="Account Access">
                        {self ? (
                            <p className="text-sm text-slate-500">This is your own account, so its access can't be changed here.</p>
                        ) : (
                            <div className="space-y-5">
                                <Toggle label="Block account" description="Blocked users are logged out and can't sign in or place orders." checked={!customer.is_active} onChange={(v) => update({ is_active: !v })} />
                                <Toggle label="Admin access" description="Admins can manage the whole store from this panel." checked={customer.is_admin} onChange={(v) => confirm(v ? 'Give this user full admin access?' : 'Remove admin access?') && update({ is_admin: v })} />
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </AdminLayout>
    );
}
