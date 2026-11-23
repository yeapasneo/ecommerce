import { Link } from '@inertiajs/react';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/format';

export default function OrderSuccess({ order, paymentMethods, deliveryMethods }) {
    return (
        <div className="container-x max-w-3xl py-8">
            <Seo title="Order Placed" />
            <div className="card p-6 text-center">
                <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-green-100 text-green-600"><Icon name="check" className="h-8 w-8" /></span>
                <h1 className="mt-4 text-2xl font-bold">Thank you! Your order has been placed.</h1>
                <p className="mt-2 text-slate-600">Order number <b className="text-brand-700">#{order.order_number}</b>. We will call you at {order.phone} to confirm.</p>
                <p className="mt-1 text-sm text-slate-500">You can track your order any time with your order number and phone.</p>
            </div>
            <div className="card mt-4 p-5 text-sm">
                <div className="grid gap-2 sm:grid-cols-2">
                    <p><span className="text-slate-500">Payment:</span> {paymentMethods[order.payment_method]}</p>
                    <p><span className="text-slate-500">Delivery:</span> {deliveryMethods[order.delivery_method]}</p>
                    <p className="sm:col-span-2"><span className="text-slate-500">Ship to:</span> {order.first_name} {order.last_name}, {order.address}, {order.city}</p>
                </div>
                <table className="table-x mt-4">
                    <thead><tr><th>Product</th><th className="text-right">Qty</th><th className="text-right">Total</th></tr></thead>
                    <tbody>
                        {order.items.map((i) => (
                            <tr key={i.id}><td>{i.name}</td><td className="text-right">{i.quantity}</td><td className="text-right">{money(i.total)}</td></tr>
                        ))}
                    </tbody>
                </table>
                <div className="ml-auto mt-3 max-w-xs space-y-1">
                    <div className="flex justify-between"><span>Sub-Total</span><span>{money(order.subtotal)}</span></div>
                    <div className="flex justify-between"><span>Delivery</span><span>{money(order.delivery_charge)}</span></div>
                    {order.discount > 0 && <div className="flex justify-between text-green-700"><span>Discount</span><span>-{money(order.discount)}</span></div>}
                    <div className="flex justify-between font-bold"><span>Total</span><span className="text-accent-600">{money(order.total)}</span></div>
                </div>
            </div>
            <div className="mt-6 flex justify-center gap-3">
                <Link href={`/track-order?order_number=${order.order_number}&phone=${encodeURIComponent(order.phone)}`} className="btn-outline">Track Order</Link>
                <Link href="/" className="btn-primary">Continue Shopping</Link>
            </div>
        </div>
    );
}

OrderSuccess.layout = (page) => <ShopLayout>{page}</ShopLayout>;
