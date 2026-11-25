import { Link, router } from '@inertiajs/react';
import OrderTimeline from '../../Components/OrderTimeline';
import AccountLayout from '../../Layouts/AccountLayout';
import { dateTime, money, statusColor, titleCase } from '../../lib/format';
import { t } from '../../lib/i18n';

export default function OrderShow({ order, paymentMethods, deliveryMethods }) {
    const cancel = () => confirm('Cancel this order?') && router.post(`/account/orders/${order.order_number}/cancel`, {}, { preserveScroll: true });
    return (
        <AccountLayout title={`Order #${order.order_number}`}>
            <div className="card p-5">
                <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                    <span className="text-slate-500">Placed {dateTime(order.created_at)}</span>
                    <span className={`badge ${statusColor(order.payment_status)}`}>{titleCase(order.payment_status)}</span>
                    {order.status === 'pending' && <button onClick={cancel} className="btn-outline btn-sm ml-auto text-red-600">{t('Cancel Order')}</button>}
                </div>
                <OrderTimeline order={order} />
            </div>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
                <div className="card p-5 text-sm">
                    <h2 className="mb-2 font-semibold">{t('Delivery Address')}</h2>
                    <p>{order.first_name} {order.last_name}</p>
                    <p>{order.address}, {order.city}</p>
                    <p>{order.phone}</p>
                    {order.email && <p>{order.email}</p>}
                </div>
                <div className="card p-5 text-sm">
                    <h2 className="mb-2 font-semibold">{t('Payment & Delivery')}</h2>
                    <p>Payment: {paymentMethods[order.payment_method]}</p>
                    <p>Delivery: {deliveryMethods[order.delivery_method]}</p>
                    {order.coupon_code && <p>Coupon: {order.coupon_code}</p>}
                    {order.notes && <p className="mt-2 text-slate-500">Note: {order.notes}</p>}
                </div>
            </div>
            <div className="card mt-4 overflow-x-auto">
                <table className="table-x">
                    <thead><tr><th>{t('Product')}</th><th className="text-right">{t('Price')}</th><th className="text-right">{t('Qty')}</th><th className="text-right">{t('Total')}</th></tr></thead>
                    <tbody>
                        {order.items.map((i) => (
                            <tr key={i.id}>
                                <td>{i.product ? <Link href={`/product/${i.product.slug}`} className="hover:text-accent-600">{i.name}</Link> : i.name}{i.variant_name && <span className="text-slate-500"> ({i.variant_name})</span>}</td>
                                <td className="text-right">{money(i.price)}</td>
                                <td className="text-right">{i.quantity}</td>
                                <td className="text-right">{money(i.total)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="ml-auto max-w-xs space-y-1 p-4 text-sm">
                    <div className="flex justify-between"><span>{t('Sub-Total')}</span><span>{money(order.subtotal)}</span></div>
                    <div className="flex justify-between"><span>{t('Delivery')}</span><span>{money(order.delivery_charge)}</span></div>
                    {order.discount > 0 && <div className="flex justify-between text-green-700"><span>{t('Discount')}</span><span>-{money(order.discount)}</span></div>}
                    {order.tax > 0 && <div className="flex justify-between"><span>{t('VAT / Tax')}</span><span>{money(order.tax)}</span></div>}
                    <div className="flex justify-between font-bold"><span>{t('Total')}</span><span className="text-accent-600">{money(order.total)}</span></div>
                    {order.payment_status === 'partial' && <div className="flex justify-between text-sm"><span>{t('Paid')}</span><span>{money(order.paid_amount)}</span></div>}
                    {order.payment_status === 'partial' && <div className="flex justify-between font-semibold text-red-600"><span>{t('Due')}</span><span>{money(order.due_amount)}</span></div>}
                </div>
            </div>
        </AccountLayout>
    );
}
