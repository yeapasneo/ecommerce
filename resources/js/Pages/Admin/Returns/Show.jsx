import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '../../../Components/Admin/UI';
import { TextArea, TextInput } from '../../../Components/Field';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { date, dateTime, money, statusColor } from '../../../lib/format';

/** One workflow step: a note for the customer plus the step's own fields. */
function Step({ request, action, label, tone = 'btn-primary', children, initial = {}, noteLabel = 'Note to the customer (optional)', noteRequired = false }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ action, note: '', ...initial });
    if (!open) return <button type="button" onClick={() => setOpen(true)} className={tone}>{label}</button>;
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.put(`/admin/returns/${request.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="w-full space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
            {children?.(form)}
            <TextArea label={noteLabel + (noteRequired ? '*' : '')} rows={2} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} error={form.errors.note} />
            {form.errors.status && <p className="text-sm text-red-600">{form.errors.status}</p>}
            <div className="flex gap-2">
                <button disabled={form.processing} className={tone}>{form.processing ? 'Saving…' : label}</button>
                <button type="button" onClick={() => setOpen(false)} className="btn-outline">Cancel</button>
            </div>
        </form>
    );
}

export default function Show({ request: r, order, deliveredAt, warranty, refundable, suggestedRefund, statuses, types, reasons, resolutions, refundMethods }) {
    const s = r.status;
    const resolutionSelect = (form) => (
        <div>
            <label className="label" htmlFor="rr-res">Resolution</label>
            <select id="rr-res" className="input" value={form.data.resolution ?? ''} onChange={(e) => form.setData('resolution', e.target.value)}>
                {Object.entries(resolutions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
        </div>
    );

    return (
        <AdminLayout
            title={`${r.number} · ${types[r.type]}`}
            subtitle={`Requested ${dateTime(r.created_at)} · order #${order.order_number}`}
            back="/admin/returns"
            actions={<span className={`badge rounded-md px-3 py-1.5 text-sm ${statusColor(s)}`}>{statuses[s]}</span>}
        >
            <div className="grid items-start gap-6 xl:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <Panel title="Items">
                        <div className="overflow-x-auto">
                            <table className="table-admin min-w-[640px]">
                                <thead><tr><th className="pl-0">Product</th><th className="text-right">Qty</th><th>Serial / IMEI</th><th>Warranty</th><th className="text-right">Value</th></tr></thead>
                                <tbody>
                                    {r.items.map((i) => {
                                        const w = warranty[i.id];
                                        return (
                                            <tr key={i.id}>
                                                <td className="pl-0">
                                                    {i.order_item?.product ? <Link href={`/admin/products/${i.order_item.product.id}/edit`} className="font-medium hover:text-primary-700">{i.order_item.name}</Link> : i.order_item?.name}
                                                    {i.order_item?.variant_name && <span className="text-slate-500"> ({i.order_item.variant_name})</span>}
                                                    {s !== 'requested' && s !== 'approved' && <span className="block text-xs text-slate-500">{i.restock ? 'Put back in stock' : 'Not restocked'}</span>}
                                                </td>
                                                <td className="text-right">{i.quantity}</td>
                                                <td className="font-mono text-xs">{i.serial ?? '—'}</td>
                                                <td className="text-xs">
                                                    {w.months ? (w.valid ? <span className="text-green-700">Valid until {date(w.ends)} ({w.days_left} days)</span> : <span className="text-red-600">Expired {w.ends ? date(w.ends) : ''}</span>) : <span className="text-slate-400">No warranty</span>}
                                                </td>
                                                <td className="text-right tabular-nums">{money(i.quantity * (i.order_item?.price ?? 0))}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-4 rounded-lg bg-slate-50 p-3 text-sm">
                            <p><b>{reasons[r.reason] ?? r.reason}</b></p>
                            {r.details && <p className="mt-1 whitespace-pre-line text-slate-700">{r.details}</p>}
                        </div>
                    </Panel>

                    <Panel title="Next step">
                        <div className="flex flex-wrap items-start gap-2">
                            {s === 'requested' && (
                                <>
                                    <Step request={r} action="approve" label="Approve" initial={{ resolution: r.type === 'return' ? 'refund' : 'repair' }}>{resolutionSelect}</Step>
                                    <Step request={r} action="reject" label="Reject" tone="btn-cancel rounded-lg" noteLabel="Why is it rejected? (sent to the customer)" noteRequired />
                                </>
                            )}
                            {s === 'approved' && (
                                <Step request={r} action="receive" label="Mark item received" initial={{ restock: Object.fromEntries(r.items.map((i) => [i.id, r.type === 'return'])) }}>
                                    {(form) => (
                                        <div className="space-y-1 text-sm">
                                            <p className="font-medium">Put back on sale?</p>
                                            {r.items.map((i) => (
                                                <label key={i.id} className="flex items-center gap-2">
                                                    <input type="checkbox" className="h-4 w-4 accent-primary-600" checked={!!form.data.restock[i.id]} onChange={(e) => form.setData('restock', { ...form.data.restock, [i.id]: e.target.checked })} />
                                                    {i.order_item?.name} × {i.quantity} <span className="text-xs text-slate-500">(untick for faulty items)</span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </Step>
                            )}
                            {['approved', 'received'].includes(s) && refundable > 0 && (
                                <Step request={r} action="refund" label="Refund" initial={{ amount: Math.min(suggestedRefund, refundable), method: order.payment_method === 'bkash' ? 'bkash' : 'cash', reference: '' }}>
                                    {(form) => (
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <TextInput label={`Amount (max ${money(refundable)})`} type="number" step="0.01" min="0.01" max={refundable} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
                                            <div>
                                                <label className="label" htmlFor="rr-method">Paid back by</label>
                                                <select id="rr-method" className="input" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                                                    {Object.entries(refundMethods).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                                </select>
                                            </div>
                                            <TextInput label="Transaction ID" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} error={form.errors.reference} />
                                        </div>
                                    )}
                                </Step>
                            )}
                            {['approved', 'received', 'refunded'].includes(s) && (
                                <Step request={r} action="complete" label={r.type === 'warranty' ? 'Repaired / replaced — close' : 'Close'} tone="btn-outline" initial={{ resolution: r.resolution ?? 'repair' }}>
                                    {r.type === 'warranty' ? resolutionSelect : undefined}
                                </Step>
                            )}
                            {['rejected', 'completed', 'cancelled'].includes(s) && <p className="text-sm text-slate-500">This request is closed.</p>}
                        </div>
                        {r.staff_note && <p className="mt-4 text-sm"><b>Last note to customer:</b> {r.staff_note}</p>}
                    </Panel>

                    <Panel title="History" bodyClassName="px-6 pb-5">
                        <ul className="space-y-2 text-sm">
                            {r.events.map((e) => (
                                <li key={e.id} className="flex gap-3">
                                    <span className={`badge h-fit shrink-0 rounded-md ${statusColor(e.status)}`}>{statuses[e.status]}</span>
                                    <span className="min-w-0 flex-1">{e.comment && <span className="block text-slate-700">{e.comment}</span>}<span className="text-xs text-slate-500">{dateTime(e.created_at)} · {e.user?.name ?? 'Customer'}</span></span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Order" bodyClassName="px-6 pb-5">
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            <dt className="text-slate-500">Order</dt><dd><Link href={`/admin/orders/${order.id}`} className="text-primary-700 hover:underline">#{order.order_number}</Link></dd>
                            <dt className="text-slate-500">Customer</dt><dd>{order.first_name} {order.last_name}<span className="block text-xs text-slate-500">{order.phone}{order.email && ` · ${order.email}`}</span></dd>
                            <dt className="text-slate-500">Delivered</dt><dd>{deliveredAt ? date(deliveredAt) : 'Not yet'}</dd>
                            <dt className="text-slate-500">Total</dt><dd>{money(order.total)}</dd>
                            <dt className="text-slate-500">Paid</dt><dd>{money(order.paid_amount)}</dd>
                            <dt className="text-slate-500">Refunded</dt><dd className={order.refunded_amount > 0 ? 'text-green-700' : ''}>{money(order.refunded_amount)}</dd>
                        </dl>
                    </Panel>
                    {r.refund_amount > 0 && (
                        <Panel title="Refund" bodyClassName="px-6 pb-5">
                            <p className="text-2xl font-bold text-green-700">{money(r.refund_amount)}</p>
                            <p className="text-sm text-slate-500">{refundMethods[r.refund_method]}{r.refund_reference && ` · ${r.refund_reference}`}{r.refunded_at && ` · ${date(r.refunded_at)}`}</p>
                        </Panel>
                    )}
                    <p className="flex items-start gap-2 text-xs text-slate-500"><Icon name="info" className="mt-0.5 h-4 w-4 shrink-0" /> The customer gets an SMS/email at each step (Settings → Notifications).</p>
                </div>
            </div>
        </AdminLayout>
    );
}
