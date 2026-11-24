import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { date, money, statusColor, titleCase } from '../../lib/format';

function Stat({ icon, label, value, sub, href, tone = 'brand' }) {
    const tones = { brand: 'bg-primary-50 text-primary-700', accent: 'bg-orange-50 text-orange-600', green: 'bg-green-50 text-green-600', red: 'bg-red-50 text-red-600' };
    const body = (
        <div className="panel flex h-full items-center gap-4 p-5 transition hover:border-primary-300">
            <span className={`grid h-12 w-12 shrink-0 place-items-center rounded-xl ${tones[tone]}`}><Icon name={icon} /></span>
            <div className="min-w-0">
                <p className="text-sm text-slate-500">{label}</p>
                <p className="truncate text-2xl font-bold text-slate-900">{typeof value === 'number' ? value.toLocaleString() : value}</p>
                {sub && <p className="text-xs text-slate-500">{sub}</p>}
            </div>
        </div>
    );
    return href ? <Link href={href}>{body}</Link> : body;
}

function RevenueChart({ data }) {
    const [hover, setHover] = useState(null);
    const max = Math.max(1, ...data.map((d) => d.revenue));
    const W = 720, H = 220, pad = 28, bw = (W - pad) / data.length;
    return (
        <div className="relative">
            <svg viewBox={`0 0 ${W} ${H + 24}`} className="w-full" role="img" aria-label="Revenue for the last 30 days">
                {[0, 0.5, 1].map((t) => (
                    <g key={t}>
                        <line x1={pad} x2={W} y1={H - t * H + 4} y2={H - t * H + 4} stroke="#e2e8f0" />
                        <text x={0} y={H - t * H + 8} fontSize="10" fill="#94a3b8">{t === 0 ? '0' : `${Math.round((max * t) / 1000)}k`}</text>
                    </g>
                ))}
                {data.map((d, i) => {
                    const h = (d.revenue / max) * (H - 10);
                    return (
                        <g key={i} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                            <rect x={pad + i * bw} y={0} width={bw} height={H + 4} fill="transparent" />
                            <rect x={pad + i * bw + 2} y={H - h + 4} width={Math.max(2, bw - 4)} height={h} rx="2" fill={hover === i ? '#bba5fc' : '#5a23dc'} />
                            {i % 5 === 0 && <text x={pad + i * bw} y={H + 20} fontSize="10" fill="#94a3b8">{d.date}</text>}
                        </g>
                    );
                })}
            </svg>
            {hover !== null && (
                <div className="pointer-events-none absolute right-2 top-0 rounded-md bg-slate-800 px-3 py-1.5 text-xs text-white">
                    {data[hover].date}: <b>{money(data[hover].revenue)}</b> · {data[hover].orders} orders
                </div>
            )}
        </div>
    );
}

export default function Dashboard({ stats, chart, ordersByStatus, recentOrders, lowStock, topProducts }) {
    return (
        <AdminLayout title="Dashboard" subtitle="Here's what's happening in your store.">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat icon="receipt" label="Total Revenue" value={money(stats.revenue)} sub={`${money(stats.revenueMonth)} this month`} tone="green" />
                <Stat icon="cart" label="Orders" value={stats.orders} sub={`${stats.pendingOrders} pending`} href="/admin/orders?status=pending" tone="accent" />
                <Stat icon="box" label="Products" value={stats.products} sub={`${Number(stats.outOfStock).toLocaleString()} out of stock`} href="/admin/products" />
                <Stat icon="users" label="Customers" value={stats.customers} href="/admin/customers" />
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-3">
                <Stat icon="star" label="Pending reviews" value={stats.pendingReviews} href="/admin/reviews?status=pending" tone="accent" />
                <Stat icon="chat" label="Unanswered questions" value={stats.unansweredQuestions} href="/admin/questions?status=unanswered" tone="accent" />
                <Stat icon="mail" label="Unread messages" value={stats.unreadMessages} href="/admin/messages" tone="red" />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <Panel title="Revenue · last 30 days" className="xl:col-span-2">
                    <RevenueChart data={chart} />
                </Panel>
                <Panel title="Orders by status">
                    <ul className="space-y-3">
                        {['pending', 'processing', 'shipped', 'delivered', 'cancelled'].map((s) => {
                            const n = ordersByStatus[s] ?? 0;
                            const pct = stats.orders ? (n / stats.orders) * 100 : 0;
                            return (
                                <li key={s}>
                                    <div className="mb-1 flex justify-between text-sm"><Link href={`/admin/orders?status=${s}`} className="hover:text-primary-700">{titleCase(s)}</Link><span className="font-semibold">{n}</span></div>
                                    <div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-primary-600" style={{ width: `${pct}%` }} /></div>
                                </li>
                            );
                        })}
                    </ul>
                </Panel>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <Panel title="Recent Orders" className="xl:col-span-2" bodyClassName="overflow-x-auto pb-2" actions={<Link href="/admin/orders" className="text-sm font-medium text-primary-700 hover:underline">View all</Link>}>
                    <table className="table-admin min-w-[520px] border-t border-slate-100">
                        <thead><tr><th className="pl-6">Order</th><th>Customer</th><th>Date</th><th>Status</th><th className="pr-6 text-right">Total</th></tr></thead>
                        <tbody>
                            {recentOrders.map((o) => (
                                <tr key={o.id}>
                                    <td className="pl-6"><Link href={`/admin/orders/${o.id}`} className="font-semibold text-primary-700 hover:underline">#{o.order_number}</Link></td>
                                    <td>{o.first_name} {o.last_name}</td>
                                    <td className="whitespace-nowrap">{date(o.created_at)}</td>
                                    <td><span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${statusColor(o.status)}`}>{titleCase(o.status)}</span></td>
                                    <td className="whitespace-nowrap pr-6 text-right font-medium">{money(o.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Panel>
                <div className="space-y-6">
                    <Panel title="Top Selling">
                        <ul className="space-y-2 text-sm">
                            {topProducts.map((p, i) => (
                                <li key={i} className="flex justify-between gap-3"><span className="line-clamp-1">{p.name}</span><span className="shrink-0 font-semibold">{p.qty} sold</span></li>
                            ))}
                        </ul>
                    </Panel>
                    <Panel title="Low Stock">
                        {lowStock.length === 0 && <p className="text-sm text-slate-500">All products are well stocked.</p>}
                        <ul className="space-y-2 text-sm">
                            {lowStock.map((p) => (
                                <li key={p.id} className="flex justify-between gap-3">
                                    <Link href={`/admin/products/${p.id}/edit`} className="line-clamp-1 hover:text-primary-700">{p.name}</Link>
                                    <span className="badge shrink-0 rounded-md bg-red-50 text-red-700 ring-1 ring-inset ring-current/20">{p.stock} left</span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>
            </div>
        </AdminLayout>
    );
}
