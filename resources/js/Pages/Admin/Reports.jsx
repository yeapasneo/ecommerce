import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { money, titleCase } from '../../lib/format';

const iso = (d) => d.toISOString().slice(0, 10);
const PRESETS = [
    ['Today', () => [new Date(), new Date()]],
    ['7 days', () => [new Date(Date.now() - 6 * 864e5), new Date()]],
    ['30 days', () => [new Date(Date.now() - 29 * 864e5), new Date()]],
    ['This month', () => { const n = new Date(); return [new Date(n.getFullYear(), n.getMonth(), 1), n]; }],
    ['Last month', () => { const n = new Date(); return [new Date(n.getFullYear(), n.getMonth() - 1, 1), new Date(n.getFullYear(), n.getMonth(), 0)]; }],
    ['This year', () => [new Date(new Date().getFullYear(), 0, 1), new Date()]],
];

function Change({ now, before }) {
    if (!before) return <span className="text-xs text-slate-400">no earlier data</span>;
    const pct = Math.round(((now - before) / before) * 100);
    return <span className={`text-xs font-medium ${pct >= 0 ? 'text-green-600' : 'text-red-600'}`}>{pct >= 0 ? '▲' : '▼'} {Math.abs(pct)}% vs previous period</span>;
}

function Chart({ series }) {
    const [hover, setHover] = useState(null);
    const max = Math.max(1, ...series.map((d) => d.revenue));
    const W = 900, H = 220, pad = 34, bw = (W - pad) / series.length;
    const every = Math.ceil(series.length / 8);
    return (
        <div className="relative">
            <svg viewBox={`0 0 ${W} ${H + 24}`} className="w-full" role="img" aria-label="Revenue per day">
                {[0, 0.5, 1].map((t) => (
                    <g key={t}>
                        <line x1={pad} x2={W} y1={H - t * (H - 10)} y2={H - t * (H - 10)} stroke="currentColor" className="text-slate-200" />
                        <text x={0} y={H - t * (H - 10) + 4} fontSize="10" className="fill-slate-400">{t === 0 ? '0' : max * t >= 1000 ? `${Math.round((max * t) / 1000)}k` : Math.round(max * t)}</text>
                    </g>
                ))}
                {series.map((d, i) => {
                    const h = (d.revenue / max) * (H - 10);
                    return (
                        <g key={i} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                            <rect x={pad + i * bw} y={0} width={bw} height={H} fill="transparent" />
                            <rect x={pad + i * bw + Math.min(2, bw * 0.15)} y={H - h} width={Math.max(1, bw - Math.min(4, bw * 0.3))} height={h} rx="2" className={hover === i ? 'fill-primary-300' : 'fill-primary-600'} />
                            {i % every === 0 && <text x={pad + i * bw} y={H + 18} fontSize="10" className="fill-slate-400">{d.date}</text>}
                        </g>
                    );
                })}
            </svg>
            {hover !== null && (
                <div className="pointer-events-none absolute right-2 top-0 rounded-md bg-slate-800 px-3 py-1.5 text-xs text-white">
                    {series[hover].date}: <b>{money(series[hover].revenue)}</b> · {series[hover].orders} orders
                </div>
            )}
        </div>
    );
}

function Breakdown({ title, rows, unit = 'orders', total, by = 'revenue' }) {
    return (
        <Panel title={title} bodyClassName="px-6 pb-5">
            {rows.length === 0 ? <p className="text-sm text-slate-500">No sales in this period.</p> : (
                <ul className="space-y-3">
                    {rows.map((r) => (
                        <li key={r.name ?? 'none'}>
                            <div className="mb-1 flex justify-between gap-3 text-sm">
                                <span className="truncate">{r.name ? titleCase(r.name) : '—'}</span>
                                <span className="shrink-0 font-medium tabular-nums">{money(by === 'profit' ? r.profit : r.revenue)} <span className="font-normal text-slate-500">· {r[unit] ?? r.orders} {unit === 'qty' ? 'sold' : 'orders'}</span></span>
                            </div>
                            <div className="h-1.5 rounded-full bg-slate-100"><div className={`h-1.5 rounded-full ${by === 'profit' ? 'bg-green-500' : 'bg-primary-500'}`} style={{ width: `${total ? (Math.max(0, r[by]) / total) * 100 : 0}%` }} /></div>
                            {r.profit !== undefined && (
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {r.profit === null ? 'Profit: add cost prices to see it' : <>{by === 'profit' ? `Sales ${money(r.revenue)}` : `Profit ${money(r.profit)}`} · <span className={r.margin < 0 ? 'text-red-600' : 'text-green-700'}>{r.margin}% margin</span></>}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

export default function Reports({ range, totals, previous, cancelled, newCustomers, series, topProducts, profitable = [], categories, payments, sources, cities, statuses }) {
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);
    const go = (f, t) => router.get('/admin/reports', { from: f, to: t }, { preserveScroll: true });
    const qs = `from=${range.from}&to=${range.to}`;

    const cards = [
        ['Revenue', money(totals.revenue), 'receipt', <Change key="r" now={totals.revenue} before={previous.revenue} />],
        ['Orders', totals.orders.toLocaleString(), 'cart', <Change key="o" now={totals.orders} before={previous.orders} />],
        ['Gross profit', money(totals.profit), 'percent', totals.costCoverage === 0
            ? <span key="p" className="text-xs text-slate-500">Add cost prices to products to see profit</span>
            : <span key="p" className="text-xs text-slate-500">{totals.margin}% margin{totals.costCoverage < 100 && ` · cost known for ${totals.costCoverage}% of sales`}</span>],
        ['Average order', money(totals.aov), 'receipt', <Change key="a" now={totals.aov} before={previous.aov} />],
        ['Items sold', totals.items.toLocaleString(), 'box', <span key="c" className="text-xs text-slate-500">{totals.customers} customers · {newCustomers} new sign-ups</span>],
    ];

    return (
        <AdminLayout
            title="Sales Reports"
            subtitle={`${range.from} to ${range.to} · cancelled orders are excluded`}
            actions={
                <>
                    <a href={`/admin/reports/export/orders?${qs}`} className="btn-outline"><Icon name="file" className="h-4 w-4" /> Orders CSV</a>
                    <a href={`/admin/reports/export/products?${qs}`} className="btn-outline"><Icon name="file" className="h-4 w-4" /> Products CSV</a>
                </>
            }
        >
            <div className="panel mb-6 flex flex-wrap items-center gap-2 px-4 py-3">
                {PRESETS.map(([label, fn]) => {
                    const [f, t] = fn().map(iso);
                    const on = f === range.from && t === range.to;
                    return <button key={label} type="button" onClick={() => go(f, t)} className={`rounded-lg border px-3 py-1.5 text-sm ${on ? 'border-primary-600 bg-primary-50 font-medium text-primary-700' : 'border-slate-200 hover:border-primary-300'}`}>{label}</button>;
                })}
                <form onSubmit={(e) => { e.preventDefault(); go(from, to); }} className="ml-auto flex flex-wrap items-center gap-2">
                    <input type="date" className="input h-9 w-auto py-1" value={from} onChange={(e) => setFrom(e.target.value)} aria-label="From" />
                    <span className="text-slate-400">to</span>
                    <input type="date" className="input h-9 w-auto py-1" value={to} onChange={(e) => setTo(e.target.value)} aria-label="To" />
                    <button className="btn-primary h-9 py-1">Apply</button>
                </form>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {cards.map(([label, value, icon, sub]) => (
                    <div key={label} className="panel p-5">
                        <div className="flex items-center gap-3">
                            <span className="grid h-10 w-10 place-items-center rounded-xl bg-primary-50 text-primary-700"><Icon name={icon} className="h-5 w-5" /></span>
                            <p className="text-sm text-slate-500">{label}</p>
                        </div>
                        <p className="mt-3 text-2xl font-bold tabular-nums text-slate-900">{value}</p>
                        <div className="mt-1">{sub}</div>
                    </div>
                ))}
            </div>

            <div className="mb-6 grid items-start gap-6 xl:grid-cols-[1fr_340px]">
                <Panel title="Revenue per day"><Chart series={series} /></Panel>
                <Panel title="Money" bodyClassName="px-6 pb-5">
                    <dl className="grid grid-cols-[1fr_auto] gap-y-2 text-sm tabular-nums">
                        <dt className="text-slate-500">Cost of goods sold</dt><dd className="text-right">{money(totals.cost)}</dd>
                        <dt className="text-slate-500">Discounts given</dt><dd className="text-right">{money(totals.discount)}</dd>
                        <dt className="text-slate-500">VAT collected</dt><dd className="text-right">{money(totals.tax)}</dd>
                        <dt className="text-slate-500">Delivery charges</dt><dd className="text-right">{money(totals.delivery)}</dd>
                        <dt className="text-slate-500">Payments received</dt><dd className="text-right text-green-700">{money(totals.collected)}</dd>
                        <dt className="text-slate-500">Still to collect</dt><dd className="text-right text-amber-700">{money(totals.outstanding)}</dd>
                        <dt className="border-t border-slate-100 pt-2 text-slate-500">Cancelled</dt><dd className="border-t border-slate-100 pt-2 text-right text-red-600">{cancelled.orders} · {money(cancelled.value)}</dd>
                    </dl>
                    <div className="mt-4 flex flex-wrap gap-1.5">
                        {Object.entries(statuses).map(([s, n]) => <span key={s} className="badge rounded-md bg-slate-100 text-slate-600">{titleCase(s)} {n}</span>)}
                    </div>
                </Panel>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-2">
                <Breakdown title="Top products" rows={topProducts} unit="qty" total={topProducts[0]?.revenue} />
                <Breakdown title="Most profitable products" rows={profitable} unit="qty" by="profit" total={profitable[0]?.profit} />
                <Breakdown title="Top categories" rows={categories} unit="qty" total={categories[0]?.revenue} />
                <Breakdown title="Payment methods" rows={payments} total={totals.revenue} />
                <Breakdown title="Order sources" rows={sources} total={totals.revenue} />
                <Breakdown title="Top cities" rows={cities} total={totals.revenue} />
            </div>
        </AdminLayout>
    );
}
