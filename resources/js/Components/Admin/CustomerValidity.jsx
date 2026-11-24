import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Icon from '../Icon';
import { Toggle } from './UI';

const RISK = {
    low: ['Trusted', 'bg-green-50 text-green-700', 'Mostly accepts deliveries.'],
    medium: ['Some returns', 'bg-amber-50 text-amber-700', 'Has refused or cancelled some orders. Consider calling first.'],
    high: ['High risk', 'bg-red-50 text-red-700', 'Usually cancels or refuses parcels. Ask for advance payment.'],
    new: ['New customer', 'bg-slate-100 text-slate-600', 'No completed orders yet.'],
    blocked: ['Marked as fraud', 'bg-red-600 text-white', 'This number is on your blocked list.'],
};

function Bar({ delivered, cancelled }) {
    const total = delivered + cancelled;
    const pct = total ? Math.round((delivered / total) * 100) : 0;
    return (
        <div className="flex items-center gap-2">
            <div className="h-2 flex-1 overflow-hidden rounded-full bg-red-100" aria-hidden="true">
                <div className="h-2 rounded-full bg-green-500" style={{ width: `${pct}%` }} />
            </div>
            <span className="w-10 text-right text-xs font-semibold tabular-nums">{total ? `${pct}%` : '—'}</span>
        </div>
    );
}

export async function fetchValidity(phone, orderId, external = false) {
    const q = new URLSearchParams({ phone, ...(orderId ? { order_id: orderId } : {}), ...(external ? { external: 1 } : {}) });
    const res = await fetch(`/admin/fraud-check?${q}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('Could not check this number.');
    return res.json();
}

/** Delivery record for a phone number, with a courier lookup and a "mark as fraud" switch. */
export default function CustomerValidity({ phone, orderId, initial, compact = false }) {
    const [report, setReport] = useState(initial);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [reason, setReason] = useState('');
    const [asking, setAsking] = useState(false);

    useEffect(() => setReport(initial), [initial]);

    if (!report) return null;
    const [label, tone, hint] = RISK[report.risk] ?? RISK.new;
    const i = report.internal;
    const x = report.external;

    const checkCouriers = async () => {
        setLoading(true); setError(null);
        try { setReport(await fetchValidity(phone, orderId, true)); }
        catch (e) { setError(e.message); }
        finally { setLoading(false); }
    };
    const block = () => router.post('/admin/blocked-phones', { phone, reason }, { preserveScroll: true, onSuccess: () => { setAsking(false); setReason(''); } });
    const unblock = () => router.post('/admin/blocked-phones/unblock', { phone }, { preserveScroll: true });

    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <div className="mb-3 flex items-center gap-2.5">
                <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700"><Icon name="shield" className="h-4 w-4" /></span>
                <p className="mr-auto font-semibold text-slate-900">Customer Validity</p>
                <span className={`badge rounded-md ${tone}`}>{label}</span>
            </div>
            <p className="mb-3 text-xs text-slate-500">{report.blocked?.reason ? `Blocked: ${report.blocked.reason}` : hint}</p>

            <div className="space-y-1 text-sm">
                <div className="flex justify-between text-xs text-slate-500"><span>In this shop{orderId ? ' (other orders)' : ''}</span><span>{i.total} order{i.total === 1 ? '' : 's'}</span></div>
                <Bar delivered={i.delivered} cancelled={i.cancelled} />
                <p className="text-xs text-slate-500">{i.delivered} delivered · {i.cancelled} cancelled{i.ongoing ? ` · ${i.ongoing} in progress` : ''}</p>
            </div>

            {x && (
                <div className="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                    <div className="flex justify-between text-xs text-slate-500"><span>All couriers</span><span>{x.total} parcel{x.total === 1 ? '' : 's'}</span></div>
                    <Bar delivered={x.delivered} cancelled={x.cancelled} />
                    {x.couriers.length > 0 && (
                        <table className="mt-2 w-full text-xs">
                            <tbody>
                                {x.couriers.map((c) => (
                                    <tr key={c.name} className="border-t border-slate-100">
                                        <td className="py-1">{c.name}</td>
                                        <td className="py-1 text-right tabular-nums text-green-700">{c.delivered} ✓</td>
                                        <td className="py-1 text-right tabular-nums text-red-600">{c.cancelled} ✕</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}

            {!x && (
                <div className="mt-4">
                    {report.externalAvailable ? (
                        <button type="button" onClick={checkCouriers} disabled={loading} className="btn-primary w-full">
                            <Icon name="shield" className="h-4 w-4" /> {loading ? 'Checking couriers…' : 'Check courier history'}
                        </button>
                    ) : (
                        !compact && <p className="text-xs text-slate-500">To see this number's record across Steadfast, Pathao, RedX and more, add a BD Courier key in <Link href="/admin/settings#fraud" className="font-medium text-primary-700 hover:underline">Settings → Fraud check</Link>.</p>
                    )}
                    {(error || report.externalError) && <p className="mt-2 text-xs text-red-600">{error || report.externalError}</p>}
                </div>
            )}

            {!compact && (
                <div className="mt-4 border-t border-slate-200 pt-4">
                    <Toggle label="Mark as fraud?" description="Flag this number so checkout blocks it as set in Settings → Fraud check." checked={!!report.blocked || asking} onChange={(v) => (v ? setAsking(true) : report.blocked ? unblock() : setAsking(false))} />
                    {asking && !report.blocked && (
                        <div className="mt-3 flex gap-2">
                            <input className="input min-w-0 flex-1" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Reason (optional), e.g. refused 2 parcels" aria-label="Reason" />
                            <button type="button" onClick={block} className="btn-danger shrink-0">Block</button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
