import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/format';
import { t } from '../lib/i18n';

const BANNERS = {
    paid: ['bg-green-50 text-green-800 ring-green-200', 'check', 'Payment received. Thank you!'],
    failed: ['bg-red-50 text-red-800 ring-red-200', 'x', 'Your payment did not go through. No money was taken. You can try again below.'],
    cancelled: ['bg-amber-50 text-amber-800 ring-amber-200', 'info', 'You cancelled the payment. Your order is saved. Pay below when you are ready.'],
};

export default function OrderSuccess({ order, paymentMethods, deliveryMethods, paymentResult, payWith }) {
    const [paying, setPaying] = useState(null);
    const pay = (gateway) => router.post(`/payment/${order.order_number}/pay`, { gateway }, { onStart: () => setPaying(gateway), onFinish: () => setPaying(null) });
    const paid = order.payment_status === 'paid';
    const banner = BANNERS[paymentResult];
    const awaiting = Object.keys(payWith).length > 0;

    return (
        <div className="container-x max-w-3xl py-8">
            <Seo title={t('Order Placed')} />
            {banner && (
                <div className={`mb-4 flex items-start gap-2 rounded-lg p-3 text-sm ring-1 ${banner[0]}`} role="status">
                    <Icon name={banner[1]} className="mt-0.5 h-4 w-4 shrink-0" /> {banner[2]}
                </div>
            )}
            {awaiting && (
                <div className="card mb-4 border-l-4 border-accent-500 p-5">
                    <h2 className="font-semibold">Complete your payment of {money(order.total)}</h2>
                    <p className="mt-1 text-sm text-slate-600">{t('Your order is reserved. Pay online now, or we\'ll contact you.')}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {Object.entries(payWith).map(([key, label]) => (
                            <button key={key} onClick={() => pay(key)} disabled={paying !== null} className={key === order.payment_method ? 'btn-accent' : 'btn-outline'}>
                                {paying === key ? 'Redirecting…' : `Pay with ${label}`}
                            </button>
                        ))}
                    </div>
                </div>
            )}
            <div className="card p-6 text-center">
                <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-green-100 text-green-600"><Icon name="check" className="h-8 w-8" /></span>
                <h1 className="mt-4 text-2xl font-bold">{t('Thank you! Your order has been placed.')}</h1>
                <p className="mt-2 text-slate-600">{t('Order number')} <b className="text-brand-700">#{order.order_number}</b>. We will call you at {order.phone} to confirm.</p>
                <p className="mt-1 text-sm text-slate-500">{t('You can track your order any time with your order number and phone.')}</p>
                {paid && <p className="mt-3 inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700"><Icon name="check" className="h-4 w-4" /> Paid{order.transaction_id && ` · TrxID ${order.transaction_id}`}</p>}
            </div>
            <div className="card mt-4 p-5 text-sm">
                <div className="grid gap-2 sm:grid-cols-2">
                    <p><span className="text-slate-500">{t('Payment:')}</span> {paymentMethods[order.payment_method]} · <span className={paid ? 'text-green-700' : 'text-amber-700'}>{paid ? 'Paid' : 'Unpaid'}</span></p>
                    <p><span className="text-slate-500">{t('Delivery:')}</span> {deliveryMethods[order.delivery_method]}</p>
                    <p className="sm:col-span-2"><span className="text-slate-500">{t('Ship to:')}</span> {order.first_name} {order.last_name}, {order.address}, {order.city}</p>
                </div>
                <table className="table-x mt-4">
                    <thead><tr><th>{t('Product')}</th><th className="text-right">{t('Qty')}</th><th className="text-right">{t('Total')}</th></tr></thead>
                    <tbody>
                        {order.items.map((i) => (
                            <tr key={i.id}><td>{i.name}{i.variant_name && <span className="text-slate-500"> ({i.variant_name})</span>}</td><td className="text-right">{i.quantity}</td><td className="text-right">{money(i.total)}</td></tr>
                        ))}
                    </tbody>
                </table>
                <div className="ml-auto mt-3 max-w-xs space-y-1">
                    <div className="flex justify-between"><span>{t('Sub-Total')}</span><span>{money(order.subtotal)}</span></div>
                    <div className="flex justify-between"><span>{t('Delivery')}</span><span>{money(order.delivery_charge)}</span></div>
                    {order.discount > 0 && <div className="flex justify-between text-green-700"><span>{t('Discount')}</span><span>-{money(order.discount)}</span></div>}
                    {order.points_discount > 0 && <div className="flex justify-between text-green-700"><span>{t('Points used')}</span><span>-{money(order.points_discount)}</span></div>}
                    {order.tax > 0 && <div className="flex justify-between"><span>{t('VAT / Tax')}</span><span>{money(order.tax)}</span></div>}
                    <div className="flex justify-between font-bold"><span>{t('Total')}</span><span className="text-accent-600">{money(order.total)}</span></div>
                    {order.payment_status === 'partial' && <div className="flex justify-between text-sm"><span>{t('Paid')}</span><span>{money(order.paid_amount)}</span></div>}
                    {order.payment_status === 'partial' && <div className="flex justify-between font-semibold text-red-600"><span>{t('Due')}</span><span>{money(order.due_amount)}</span></div>}
                </div>
            </div>
            <div className="mt-6 flex justify-center gap-3">
                <Link href={`/track-order?order_number=${order.order_number}&phone=${encodeURIComponent(order.phone)}`} className="btn-outline">{t('Track Order')}</Link>
                <Link href="/" className="btn-primary">{t('Continue Shopping')}</Link>
            </div>
        </div>
    );
}

OrderSuccess.layout = (page) => <ShopLayout>{page}</ShopLayout>;
