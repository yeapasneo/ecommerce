import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import CourierPanel from '../../../Components/Admin/CourierPanel';
import CustomerValidity from '../../../Components/Admin/CustomerValidity';
import { Panel, Pill, SubCard } from '../../../Components/Admin/UI';
import { TextArea } from '../../../Components/Field';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { dateTime, money, statusColor, titleCase } from '../../../lib/format';

const soft = 'badge rounded-md ring-1 ring-inset ring-current/20';

/** Segmented control, e.g. Paid / Unpaid / Refunded. */
function Segmented({ value, onChange, options, label }) {
    return (
        <div role="radiogroup" aria-label={label} className="flex rounded-lg bg-slate-100 p-1">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    role="radio"
                    aria-checked={value === o.value}
                    onClick={() => onChange(o.value)}
                    className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition ${value === o.value ? 'bg-white font-medium text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                >
                    {o.icon && <Icon name={o.icon} className="h-3.5 w-3.5" />} {o.label}
                </button>
            ))}
        </div>
    );
}

function ContactRow({ icon, children }) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <Icon name={icon} className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
            <div className="min-w-0 break-words">{children}</div>
        </div>
    );
}

/** Serial / IMEI numbers of the units on one order line. */
function Serials({ order, item }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ serials: (item.serials ?? []).join('\n') });
    if (!open) {
        return (
            <button type="button" onClick={() => setOpen(true)} className="mt-0.5 text-left text-xs text-primary-700 hover:underline">
                {item.serials?.length ? <>Serial: <span className="font-mono">{item.serials.join(', ')}</span></> : '+ Add serial / IMEI'}
            </button>
        );
    }
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.put(`/admin/orders/${order.id}/items/${item.id}/serials`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="mt-1 space-y-1">
            <textarea className="input min-w-[220px] font-mono text-xs" rows={Math.min(4, item.quantity)} value={form.data.serials} onChange={(e) => form.setData('serials', e.target.value)} placeholder={`One per line (${item.quantity} unit${item.quantity > 1 ? 's' : ''})`} aria-label="Serial numbers" autoFocus />
            {form.errors.serials && <p className="text-xs text-red-600">{form.errors.serials}</p>}
            <div className="flex gap-2"><button disabled={form.processing} className="btn-primary btn-sm">Save</button><button type="button" onClick={() => setOpen(false)} className="btn-outline btn-sm">Cancel</button></div>
        </form>
    );
}

export default function Show({ order, statuses, paymentStatuses, paymentMethods, deliveryMethods, couriers = {}, courierDefaults = {}, validity = null, notificationLog = [], editable = false, returns = [], returnStatuses = {} }) {
    const form = useForm({ status: order.status, payment_status: order.payment_status, paid_amount: order.paid_amount || '', comment: '' });
    const save = (e) => {
        e.preventDefault();
        form.put(`/admin/orders/${order.id}`, { preserveScroll: true, onSuccess: () => form.setData('comment', '') });
    };
    const itemCount = order.items.reduce((n, i) => n + i.quantity, 0);

    return (
        <AdminLayout
            title={`Order #${order.order_number}`}
            subtitle={`Placed ${dateTime(order.created_at)}`}
            back="/admin/orders"
            actions={
                <>
                    <span className={`${soft} px-3 py-1 text-sm ${statusColor(order.status)}`}>{titleCase(order.status)}</span>
                    <a href={`/admin/orders/${order.id}/invoice`} target="_blank" rel="noreferrer" className="btn-outline"><Icon name="print" className="h-4 w-4" /> Invoice</a>
                    {editable && <Link href={`/admin/orders/${order.id}/edit`} className="btn-outline"><Icon name="edit" className="h-4 w-4" /> Edit order</Link>}
                    {order.status === 'delivered' && <Link href={`/admin/orders/${order.id}/returns/create`} className="btn-outline"><Icon name="refresh" className="h-4 w-4" /> Return / claim</Link>}
                    <button form="order-form" disabled={form.processing || !form.isDirty} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : 'Save changes'}</button>
                </>
            }
        >
            <div className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                <div className="space-y-6">
                    <Panel title="Products" actions={<span className="text-sm text-slate-500">{itemCount} item{itemCount === 1 ? '' : 's'}</span>} bodyClassName="pb-2">
                        <div className="overflow-x-auto border-t border-slate-100">
                            <table className="table-admin min-w-[560px]">
                                <thead><tr><th className="pl-6">Product</th><th>Price</th><th>Qty</th><th className="pr-6 text-right">Total</th></tr></thead>
                                <tbody>
                                    {order.items.map((i) => (
                                        <tr key={i.id}>
                                            <td className="pl-6">
                                                <div className="flex items-center gap-3">
                                                    <div className="grid h-12 w-12 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-1">
                                                        {i.product?.image_url ? <img src={i.product.image_url} alt="" className="max-h-full max-w-full object-contain" /> : <Icon name="box" className="h-5 w-5 text-slate-300" />}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="line-clamp-1 font-medium text-slate-900">{i.name}{i.variant_name && <span className="font-normal text-primary-700"> · {i.variant_name}</span>}</span>
                                                            {i.product && <a href={`/product/${i.product.slug}`} target="_blank" rel="noreferrer" className="shrink-0 text-slate-500 hover:text-primary-700" aria-label="Open in store"><Icon name="external" className="h-3.5 w-3.5" /></a>}
                                                        </div>
                                                        <p className="font-mono text-xs text-slate-500">{i.sku}{i.warranty_months > 0 && <span className="font-sans"> · {i.warranty_months}-month warranty</span>}</p>
                                                        <Serials order={order} item={i} />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap">{money(i.price)}</td>
                                            <td><Pill>{i.quantity}</Pill></td>
                                            <td className="whitespace-nowrap pr-6 text-right font-medium">{money(i.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel title="Order Summary">
                        <div className="grid gap-4 md:grid-cols-2">
                            <SubCard title="Discount" icon="percent" tone="orange">
                                {order.discount > 0 ? (
                                    <div className="flex items-center justify-between text-sm">
                                        <span>{order.coupon_code ? <>Coupon <span className="font-mono font-semibold">{order.coupon_code}</span></> : 'Discount'}</span>
                                        <span className="font-semibold text-green-700">-{money(order.discount)}</span>
                                    </div>
                                ) : <p className="text-sm text-slate-500">No discount applied</p>}
                            </SubCard>
                            <SubCard title="Delivery" icon="truck" tone="blue">
                                <div className="flex items-center justify-between text-sm">
                                    <span>{deliveryMethods[order.delivery_method] ?? titleCase(order.delivery_method)}</span>
                                    <span className="font-semibold">{money(order.delivery_charge)}</span>
                                </div>
                            </SubCard>
                            <SubCard title="Payment" icon="card" tone="green">
                                <div className="flex items-center justify-between text-sm">
                                    <span>{paymentMethods[order.payment_method] ?? titleCase(order.payment_method)}</span>
                                    <span className={`${soft} ${statusColor(order.payment_status)}`}>{titleCase(order.payment_status)}</span>
                                </div>
                                {(order.transaction_id || order.paid_at) && (
                                    <dl className="mt-2 space-y-0.5 text-xs text-slate-500">
                                        {order.transaction_id && <div className="flex justify-between gap-2"><dt>Transaction ID</dt><dd className="font-mono text-slate-700">{order.transaction_id}</dd></div>}
                                        {order.paid_at && <div className="flex justify-between gap-2"><dt>Paid at</dt><dd>{dateTime(order.paid_at)}</dd></div>}
                                    </dl>
                                )}
                            </SubCard>
                            <SubCard title="Customer Note" icon="chat">
                                <p className={`text-sm ${order.notes ? 'text-slate-700' : 'text-slate-500'}`}>{order.notes || 'No note from the customer'}</p>
                            </SubCard>
                        </div>
                        <div className="mt-6 flex flex-wrap items-end justify-between gap-4 border-t border-slate-200 pt-6">
                            <dl className="grid grid-cols-[auto_auto] gap-x-8 gap-y-1 text-sm text-slate-600">
                                <dt>Sub-total</dt><dd className="text-right">{money(order.subtotal)}</dd>
                                <dt>Delivery</dt><dd className="text-right">{money(order.delivery_charge)}</dd>
                                {order.discount > 0 && <><dt>Discount</dt><dd className="text-right text-green-700">-{money(order.discount)}</dd></>}
                                {order.points_discount > 0 && <><dt>Points used ({order.points_redeemed})</dt><dd className="text-right text-green-700">-{money(order.points_discount)}</dd></>}
                                {order.points_earned > 0 && <><dt>Points earned</dt><dd className="text-right">+{order.points_earned}</dd></>}
                                {order.tax > 0 && <><dt>VAT / Tax</dt><dd className="text-right">{money(order.tax)}</dd></>}
                                {order.paid_amount > 0 && order.payment_status !== 'paid' && <><dt>Paid</dt><dd className="text-right">{money(order.paid_amount)}</dd></>}
                                {order.due_amount > 0 && order.payment_status !== 'unpaid' && <><dt className="font-semibold text-slate-900">Due</dt><dd className="text-right font-semibold text-red-600">{money(order.due_amount)}</dd></>}
                                {order.refunded_amount > 0 && <><dt>Refunded</dt><dd className="text-right text-green-700">−{money(order.refunded_amount)}</dd></>}
                            </dl>
                            <div className="rounded-xl border-2 border-primary-200 bg-primary-50/60 px-6 py-3 text-center">
                                <p className="text-sm font-medium text-primary-700">Grand Total</p>
                                <p className="text-3xl font-bold text-primary-700">{money(order.total)}</p>
                            </div>
                        </div>
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Order Information">
                        <form id="order-form" onSubmit={save} className="grid gap-4">
                            <label className="block">
                                <span className="label">Order Status</span>
                                <select className="input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                                    {statuses.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
                                </select>
                                {form.errors.status && <p className="mt-1 text-xs text-red-600">{form.errors.status}</p>}
                            </label>
                            <div>
                                <span className="label">Payment Status</span>
                                <Segmented label="Payment status" value={form.data.payment_status} onChange={(v) => form.setData('payment_status', v)} options={paymentStatuses.map((s) => ({ value: s, label: titleCase(s), icon: s === 'paid' ? 'check' : undefined }))} />
                                {form.data.payment_status === 'partial' && (
                                    <div className="mt-3">
                                        <label className="label" htmlFor="paid-amount">Paid so far (৳)</label>
                                        <input id="paid-amount" className="input" type="number" min="0" step="1" value={form.data.paid_amount} onChange={(e) => form.setData('paid_amount', e.target.value)} />
                                        {form.errors.paid_amount && <p className="mt-1 text-xs text-red-600">{form.errors.paid_amount}</p>}
                                        <p className="mt-1 text-xs text-slate-500">Due: {money(Math.max(0, order.total - (Number(form.data.paid_amount) || 0)))}</p>
                                    </div>
                                )}
                            </div>
                            <TextArea label="Comment (optional)" rows={2} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} hint="Shown to the customer in order tracking" />
                            {form.data.status === 'cancelled' && order.status !== 'cancelled' && <p className="rounded-lg bg-red-50 p-3 text-xs text-red-700">Cancelling returns the items to stock.</p>}
                        </form>
                    </Panel>

                    {returns.length > 0 && (
                        <Panel title="Returns & claims" bodyClassName="px-6 pb-5">
                            <ul className="divide-y divide-slate-100 text-sm">
                                {returns.map((r) => (
                                    <li key={r.id} className="flex items-center justify-between gap-2 py-2">
                                        <Link href={`/admin/returns/${r.id}`} className="font-medium text-primary-700 hover:underline">{r.number}</Link>
                                        <span className="text-xs text-slate-500">{r.type === 'warranty' ? 'Warranty' : 'Return'}{r.refund_amount > 0 && ` · ${money(r.refund_amount)} refunded`}</span>
                                        <span className={`badge rounded-md ${statusColor(r.status)}`}>{returnStatuses[r.status]}</span>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}
                    <CourierPanel order={order} couriers={couriers} defaults={courierDefaults} />

                    <Panel title="Customer Information">
                        <div className="mb-5 flex items-center gap-3 border-b border-slate-200 pb-5">
                            <span className="grid h-11 w-11 place-items-center rounded-full bg-primary-100 text-primary-700"><Icon name="user" className="h-5 w-5" /></span>
                            <div className="min-w-0">
                                <p className="truncate font-medium text-slate-900">{order.first_name} {order.last_name}</p>
                                <p className="text-xs text-slate-500">{order.user ? 'Registered customer' : 'Guest checkout'}</p>
                            </div>
                            {order.user && <Link href={`/admin/customers/${order.user.id}`} className="ml-auto text-sm font-medium text-primary-700 hover:underline">View account</Link>}
                        </div>
                        <p className="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">Contact details</p>
                        <div className="space-y-2">
                            <ContactRow icon="phone"><a href={`tel:${order.phone}`} className="hover:text-primary-700">{order.phone}</a></ContactRow>
                            {order.email && <ContactRow icon="mail"><a href={`mailto:${order.email}`} className="hover:text-primary-700">{order.email}</a></ContactRow>}
                            <ContactRow icon="pin">{order.address}, {order.city}</ContactRow>
                        </div>
                        <div className="mt-5"><CustomerValidity phone={order.phone} orderId={order.id} initial={validity} /></div>
                    </Panel>

                    <Panel title="Status History">
                        <ol className="relative space-y-4 border-l-2 border-slate-200 pl-5">
                            {order.histories.map((h) => (
                                <li key={h.id} className="relative">
                                    <span className="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white bg-primary-600 ring-2 ring-primary-100" />
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`${soft} ${statusColor(h.status)}`}>{titleCase(h.status)}</span>
                                        <span className="text-xs text-slate-500">{dateTime(h.created_at)}</span>
                                    </div>
                                    {h.comment && <p className="mt-1 text-sm text-slate-600">{h.comment}</p>}
                                </li>
                            ))}
                        </ol>
                    </Panel>

                    <Panel title="Messages Sent" defaultOpen={notificationLog.length > 0} actions={<span className="text-sm text-slate-500">{notificationLog.length}</span>}>
                        {notificationLog.length === 0 ? (
                            <p className="text-sm text-slate-500">No SMS or email sent for this order yet. Set them up in <Link href="/admin/settings#notifications" className="font-medium text-primary-700 hover:underline">Settings → Notifications</Link>.</p>
                        ) : (
                            <ul className="space-y-3">
                                {notificationLog.map((n) => (
                                    <li key={n.id} className="flex gap-3 text-sm">
                                        <span className={`mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full ${n.status === 'sent' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'}`}><Icon name={n.channel === 'sms' ? 'chat' : 'mail'} className="h-3.5 w-3.5" /></span>
                                        <div className="min-w-0">
                                            <p className="font-medium text-slate-900">{titleCase(n.event)} <span className="font-normal text-slate-500">· {n.channel === 'sms' ? 'SMS' : 'Email'} to {n.recipient}</span></p>
                                            <p className="text-xs text-slate-500">{dateTime(n.created_at)} · {n.status === 'sent' ? 'Sent' : 'Failed'}</p>
                                            {n.status === 'failed' && n.error && <p className="mt-0.5 text-xs text-red-600">{n.error}</p>}
                                            {n.channel === 'sms' && n.message && <p className="mt-1 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">{n.message}</p>}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </AdminLayout>
    );
}
