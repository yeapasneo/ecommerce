import { Link, useForm } from '@inertiajs/react';
import { Select, TextArea } from '../../../Components/Field';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { dateTime, money, statusColor, titleCase } from '../../../lib/format';

export default function Show({ order, statuses, paymentStatuses, paymentMethods, deliveryMethods }) {
    const form = useForm({ status: order.status, payment_status: order.payment_status, comment: '' });
    return (
        <AdminLayout
            title={`Order #${order.order_number}`}
            actions={
                <>
                    <Link href="/admin/orders" className="btn-outline"><Icon name="chevronLeft" className="h-4 w-4" /> Orders</Link>
                    <a href={`/admin/orders/${order.id}/invoice`} target="_blank" rel="noreferrer" className="btn-outline"><Icon name="print" className="h-4 w-4" /> Invoice</a>
                </>
            }
        >
            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <div className="card overflow-x-auto">
                        <table className="table-x">
                            <thead><tr><th>Product</th><th>SKU</th><th className="text-right">Price</th><th className="text-right">Qty</th><th className="text-right">Total</th></tr></thead>
                            <tbody>
                                {order.items.map((i) => (
                                    <tr key={i.id}>
                                        <td>{i.product ? <a href={`/product/${i.product.slug}`} target="_blank" rel="noreferrer" className="hover:text-accent-600">{i.name}</a> : i.name}</td>
                                        <td className="font-mono text-xs">{i.sku}</td>
                                        <td className="text-right">{money(i.price)}</td>
                                        <td className="text-right">{i.quantity}</td>
                                        <td className="text-right">{money(i.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="ml-auto max-w-xs space-y-1 p-4 text-sm">
                            <div className="flex justify-between"><span>Sub-Total</span><span>{money(order.subtotal)}</span></div>
                            <div className="flex justify-between"><span>Delivery</span><span>{money(order.delivery_charge)}</span></div>
                            {order.discount > 0 && <div className="flex justify-between text-green-700"><span>Discount {order.coupon_code && `(${order.coupon_code})`}</span><span>-{money(order.discount)}</span></div>}
                            <div className="flex justify-between text-base font-bold"><span>Total</span><span>{money(order.total)}</span></div>
                        </div>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="card p-5 text-sm">
                            <h2 className="mb-2 font-semibold">Customer</h2>
                            <p className="font-medium">{order.first_name} {order.last_name}</p>
                            <p>{order.phone}</p>
                            {order.email && <p>{order.email}</p>}
                            <p className="mt-2 text-slate-600">{order.address}, {order.city}</p>
                            {order.user ? <Link href={`/admin/customers/${order.user.id}`} className="mt-2 inline-block text-brand-600 hover:underline">View customer account</Link> : <p className="mt-2 text-xs text-slate-500">Guest checkout</p>}
                        </div>
                        <div className="card p-5 text-sm">
                            <h2 className="mb-2 font-semibold">Details</h2>
                            <p>Placed: {dateTime(order.created_at)}</p>
                            <p>Payment: {paymentMethods[order.payment_method]}</p>
                            <p>Delivery: {deliveryMethods[order.delivery_method]}</p>
                            {order.notes && <p className="mt-2 rounded bg-amber-50 p-2 text-amber-900">Note: {order.notes}</p>}
                        </div>
                    </div>
                </div>

                <div className="space-y-6">
                    <form onSubmit={(e) => { e.preventDefault(); form.put(`/admin/orders/${order.id}`, { preserveScroll: true, onSuccess: () => form.setData('comment', '') }); }} className="card grid gap-4 p-5">
                        <div className="flex items-center gap-2">
                            <h2 className="mr-auto font-semibold">Update Order</h2>
                            <span className={`badge ${statusColor(order.status)}`}>{titleCase(order.status)}</span>
                        </div>
                        <Select label="Order Status" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} error={form.errors.status} options={statuses.map((s) => ({ value: s, label: titleCase(s) }))} />
                        <Select label="Payment Status" value={form.data.payment_status} onChange={(e) => form.setData('payment_status', e.target.value)} error={form.errors.payment_status} options={paymentStatuses.map((s) => ({ value: s, label: titleCase(s) }))} />
                        <TextArea label="Comment (optional)" rows={2} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} hint="Shown to the customer in order tracking" />
                        {form.data.status === 'cancelled' && order.status !== 'cancelled' && <p className="rounded bg-red-50 p-2 text-xs text-red-700">Cancelling returns the items to stock.</p>}
                        <button disabled={form.processing} className="btn-primary">Save</button>
                    </form>
                    <div className="card p-5">
                        <h2 className="mb-3 font-semibold">History</h2>
                        <ul className="space-y-3 border-l-2 border-slate-200 pl-4 text-sm">
                            {order.histories.map((h) => (
                                <li key={h.id}>
                                    <span className={`badge ${statusColor(h.status)}`}>{titleCase(h.status)}</span>
                                    <span className="ml-2 text-xs text-slate-400">{dateTime(h.created_at)}</span>
                                    {h.comment && <p className="mt-1 text-slate-600">{h.comment}</p>}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
