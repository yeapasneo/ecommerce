import { Link, router } from '@inertiajs/react';
import { AdminPagination, EmptyState, RowMenu, Tabs } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { dateTime, money } from '../../lib/format';

export default function AbandonedCarts({ carts, status, stats, enabled }) {
    return (
        <AdminLayout title="Abandoned Carts" subtitle="Shoppers who started checkout but didn't order">
            {!enabled && (
                <p className="mb-4 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                    <Icon name="info" className="h-4 w-4 shrink-0" /> Tracking and reminders are off. Turn them on in <Link href="/admin/settings#notifications" className="font-medium underline">Settings → Notifications</Link>.
                </p>
            )}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[['Open carts (30 days)', stats.open, 'cart'], ['Value left behind', money(stats.openValue), 'receipt'], ['Recovered (30 days)', stats.recovered, 'check'], ['Recovered sales', money(stats.recoveredValue), 'percent']].map(([l, v, icon]) => (
                    <div key={l} className="panel flex items-center gap-4 p-5">
                        <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary-50 text-primary-700"><Icon name={icon} /></span>
                        <div><p className="text-sm text-slate-500">{l}</p><p className="text-xl font-bold text-slate-900">{v}</p></div>
                    </div>
                ))}
            </div>
            <div className="panel mb-4 px-4 py-3">
                <Tabs value={status} onChange={(v) => router.get('/admin/abandoned-carts', { status: v }, { preserveState: true })} tabs={[{ value: 'open', label: 'Not ordered' }, { value: 'recovered', label: 'Recovered' }, { value: 'all', label: 'All' }]} />
            </div>
            <div className="panel overflow-hidden">
                {carts.data.length === 0 ? (
                    <EmptyState icon="cart" title="Nothing here" text="Carts appear once a shopper enters a phone or email at checkout." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[860px]">
                            <thead><tr><th className="pl-6">Customer</th><th>Items</th><th>Value</th><th>Last activity</th><th>Reminder</th><th className="w-px" /></tr></thead>
                            <tbody>
                                {carts.data.map((c) => (
                                    <tr key={c.id}>
                                        <td className="pl-6">
                                            <p className="font-medium text-slate-900">{c.name || 'Unnamed'}</p>
                                            <p className="text-xs text-slate-500">{[c.phone, c.email].filter(Boolean).join(' · ')}</p>
                                        </td>
                                        <td className="max-w-xs">
                                            <p className="line-clamp-2 text-sm">{c.items.map((i) => `${i.name} × ${i.quantity}`).join(', ')}</p>
                                        </td>
                                        <td className="whitespace-nowrap font-medium">{money(c.total)}</td>
                                        <td className="whitespace-nowrap text-xs text-slate-600">{dateTime(c.updated_at)}</td>
                                        <td className="text-xs">
                                            {c.recovered_at ? <span className="badge rounded-md bg-green-50 text-green-700">Ordered{c.order && <Link href={`/admin/orders/${c.order.id}`} className="ml-1 underline">#{c.order.order_number}</Link>}</span>
                                                : c.reminded_at ? <span className="text-slate-600">Sent {dateTime(c.reminded_at)}</span> : <span className="text-slate-400">Not yet</span>}
                                        </td>
                                        <td className="pr-6">
                                            <RowMenu items={[
                                                !c.recovered_at && { label: c.reminded_at ? 'Send again' : 'Send reminder now', icon: 'mail', onClick: () => router.post(`/admin/abandoned-carts/${c.id}/remind`, {}, { preserveScroll: true }) },
                                                c.phone && { label: 'Call', icon: 'phone', href: `tel:${c.phone}`, external: true },
                                                { label: 'Remove', icon: 'trash', danger: true, onClick: () => router.delete(`/admin/abandoned-carts/${c.id}`, { preserveScroll: true }) },
                                            ]} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <AdminPagination meta={carts} params={{ status }} />
            </div>
        </AdminLayout>
    );
}
