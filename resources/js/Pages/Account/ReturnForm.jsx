import { Link, useForm } from '@inertiajs/react';
import AccountLayout from '../../Layouts/AccountLayout';
import { date, money } from '../../lib/format';
import { t } from '../../lib/i18n';

/** Shared by the customer account and the admin panel (admin = no limits). */
export function ReturnItemsForm({ order, claimable, types, reasons, action, admin = false, cancelHref }) {
    const form = useForm({
        type: Object.values(claimable).some((c) => c.return > 0) || admin ? 'return' : 'warranty',
        reason: '',
        details: '',
        items: Object.fromEntries(order.items.map((i) => [i.id, { quantity: 0, serial: claimable[i.id]?.serials?.[0] ?? '' }])),
    });
    const d = form.data;
    const e = form.errors;
    const max = (i) => (admin ? i.quantity : claimable[i.id]?.[d.type] ?? 0);
    const setItem = (id, patch) => form.setData('items', { ...d.items, [id]: { ...d.items[id], ...patch } });
    const nothing = !admin && order.items.every((i) => max(i) === 0);

    return (
        <form onSubmit={(ev) => { ev.preventDefault(); form.post(action); }} className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2">
                {Object.entries(types).map(([k, label]) => (
                    <label key={k} className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm ${d.type === k ? 'border-brand-500 bg-brand-50' : 'border-slate-200'}`}>
                        <input type="radio" name="type" className="mt-1" checked={d.type === k} onChange={() => form.setData('type', k)} />
                        <span>
                            <b>{t(label)}</b>
                            <span className="block text-xs text-slate-500">{k === 'return' ? t('Send it back for a refund.') : t('Get a faulty item repaired or replaced under warranty.')}</span>
                        </span>
                    </label>
                ))}
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200">
                <table className="table-x">
                    <thead><tr><th>{t('Product')}</th><th className="w-28 text-right">{t('Price')}</th><th className="w-28">{t('Qty')}</th><th>{t('Serial / IMEI')}</th></tr></thead>
                    <tbody>
                        {order.items.map((i) => {
                            const c = claimable[i.id] ?? {};
                            const m = max(i);
                            return (
                                <tr key={i.id} className={m === 0 ? 'opacity-60' : ''}>
                                    <td>
                                        {i.name}{i.variant_name && <span className="text-slate-500"> ({i.variant_name})</span>}
                                        <span className="block text-xs text-slate-500">
                                            {d.type === 'warranty'
                                                ? (c.warranty_ends ? `${t('Warranty until')} ${date(c.warranty_ends)}` : t('No warranty'))
                                                : (m === 0 && !admin ? t('Return period is over') : `${t('Up to')} ${m}`)}
                                        </span>
                                        {e[`items.${i.id}.quantity`] && <span className="block text-xs text-red-600">{e[`items.${i.id}.quantity`]}</span>}
                                    </td>
                                    <td className="text-right">{money(i.price)}</td>
                                    <td>
                                        <select className="input h-9 py-1" value={d.items[i.id].quantity} disabled={m === 0} onChange={(ev) => setItem(i.id, { quantity: Number(ev.target.value) })} aria-label={`${t('Quantity')}: ${i.name}`}>
                                            {Array.from({ length: m + 1 }, (_, n) => <option key={n} value={n}>{n}</option>)}
                                        </select>
                                    </td>
                                    <td>
                                        {c.serials?.length > 1 ? (
                                            <select className="input h-9 py-1" value={d.items[i.id].serial} onChange={(ev) => setItem(i.id, { serial: ev.target.value })} aria-label={t('Serial / IMEI')}>
                                                {c.serials.map((s) => <option key={s} value={s}>{s}</option>)}
                                            </select>
                                        ) : (
                                            <input className="input h-9 py-1" value={d.items[i.id].serial} onChange={(ev) => setItem(i.id, { serial: ev.target.value })} placeholder={t('Optional')} aria-label={t('Serial / IMEI')} />
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {e.items && <p className="text-sm text-red-600">{e.items}</p>}

            <div className="grid gap-4 sm:grid-cols-[260px_1fr]">
                <div>
                    <label className="label" htmlFor="rr-reason">{t('Reason')}</label>
                    <select id="rr-reason" className="input" value={d.reason} onChange={(ev) => form.setData('reason', ev.target.value)}>
                        <option value="">{t('Choose…')}</option>
                        {Object.entries(reasons).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                    </select>
                    {e.reason && <p className="mt-1 text-xs text-red-600">{e.reason}</p>}
                </div>
                <div>
                    <label className="label" htmlFor="rr-details">{t('What happened?')}</label>
                    <textarea id="rr-details" className="input" rows={3} value={d.details} onChange={(ev) => form.setData('details', ev.target.value)} placeholder={t('Describe the problem, e.g. “The laptop does not turn on after charging.”')} />
                    {e.details && <p className="mt-1 text-xs text-red-600">{e.details}</p>}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <button disabled={form.processing || nothing} className="btn-primary">{form.processing ? t('Sending…') : t('Send request')}</button>
                <Link href={cancelHref} className="btn-outline">{t('Cancel')}</Link>
                {nothing && <p className="text-sm text-slate-500">{t('Nothing on this order can be returned or claimed under warranty any more.')}</p>}
            </div>
        </form>
    );
}

export default function ReturnForm({ order, claimable, returnDays, types, reasons }) {
    return (
        <AccountLayout title={`${t('Return or warranty claim')} · #${order.order_number}`}>
            <div className="card p-5">
                <p className="mb-5 text-sm text-slate-600">
                    {t('Items can be returned within')} <b>{returnDays} {t('days')}</b> {t('of delivery')}{order.delivered_at && ` (${t('delivered')} ${date(order.delivered_at)})`}. {t('Warranty claims can be made until each item’s warranty ends.')}
                </p>
                <ReturnItemsForm order={order} claimable={claimable} types={types} reasons={reasons} action={`/account/orders/${order.order_number}/return`} cancelHref={`/account/orders/${order.order_number}`} />
            </div>
        </AccountLayout>
    );
}
