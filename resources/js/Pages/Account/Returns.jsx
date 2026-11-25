import { Link, router } from '@inertiajs/react';
import AccountLayout from '../../Layouts/AccountLayout';
import { date, money, statusColor } from '../../lib/format';
import { t } from '../../lib/i18n';

export default function Returns({ returns, statuses, types, reasons }) {
    const cancel = (r) => confirm(t('Cancel this request?')) && router.post(`/account/returns/${r.number}/cancel`, {}, { preserveScroll: true });

    return (
        <AccountLayout title={t('Returns & Warranty')}>
            {returns.length === 0 ? (
                <div className="card p-8 text-center text-sm text-slate-500">
                    {t('No requests yet. To return an item or make a warranty claim, open a delivered order and choose “Return or warranty claim”.')}
                    <div className="mt-4"><Link href="/account/orders" className="btn-primary">{t('My orders')}</Link></div>
                </div>
            ) : (
                <div className="space-y-4">
                    {returns.map((r) => (
                        <article key={r.id} className="card p-5 text-sm">
                            <header className="flex flex-wrap items-center gap-2">
                                <b className="text-base">{r.number}</b>
                                <span className="text-slate-500">· {t(types[r.type])} · {t('Order')} <Link href={`/account/orders/${r.order?.order_number}`} className="text-accent-600 hover:underline">#{r.order?.order_number}</Link></span>
                                <span className={`badge ml-auto ${statusColor(r.status)}`}>{t(statuses[r.status] ?? r.status)}</span>
                            </header>
                            <ul className="mt-2 text-slate-700">
                                {r.items.map((i) => <li key={i.id}>{i.order_item?.name}{i.order_item?.variant_name && ` (${i.order_item.variant_name})`} × {i.quantity}{i.serial && <span className="text-slate-500"> · {i.serial}</span>}</li>)}
                            </ul>
                            <p className="mt-1 text-slate-500">{t(reasons[r.reason] ?? r.reason)}{r.details && ` — ${r.details}`}</p>
                            {r.staff_note && <p className="mt-2 rounded-md bg-slate-50 px-3 py-2"><b>{t('From the shop')}:</b> {r.staff_note}</p>}
                            {r.refund_amount > 0 && <p className="mt-2 font-medium text-green-700">{t('Refunded')}: {money(r.refund_amount)}{r.refunded_at && ` · ${date(r.refunded_at)}`}</p>}
                            <ol className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                {[...r.events].reverse().map((e) => <li key={e.id}>{t(statuses[e.status] ?? e.status)} · {date(e.created_at)}</li>)}
                            </ol>
                            {r.status === 'requested' && <button type="button" onClick={() => cancel(r)} className="btn-outline btn-sm mt-3 text-red-600">{t('Cancel request')}</button>}
                        </article>
                    ))}
                </div>
            )}
        </AccountLayout>
    );
}
