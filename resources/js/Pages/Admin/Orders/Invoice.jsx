import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import Seo from '../../../Components/Seo';
import { date, money } from '../../../lib/format';

export default function Invoice({ order, paymentMethods, deliveryMethods }) {
    const { settings } = usePage().props;
    useEffect(() => {
        const t = setTimeout(() => window.print(), 400);
        return () => clearTimeout(t);
    }, []);
    return (
        <div className="min-h-screen bg-white p-6 text-sm text-slate-800 sm:p-10">
            <Seo title={`Invoice #${order.order_number}`} />
            <div className="mx-auto max-w-3xl">
                <div className="flex flex-wrap justify-between gap-4 border-b border-slate-200 pb-6">
                    <div>
                        <h1 className="text-2xl font-extrabold text-brand-700">{settings.site_name}</h1>
                        <p>{settings.address}</p>
                        <p>{settings.phone} · {settings.email}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-xl font-bold">INVOICE</p>
                        <p>#{order.order_number}</p>
                        <p>{date(order.created_at)}</p>
                    </div>
                </div>
                <div className="grid gap-4 py-6 sm:grid-cols-2">
                    <div>
                        <p className="font-semibold">Bill To</p>
                        <p>{order.first_name} {order.last_name}</p>
                        <p>{order.address}, {order.city}</p>
                        <p>{order.phone}</p>
                    </div>
                    <div className="sm:text-right">
                        <p>Payment: {paymentMethods[order.payment_method]} ({order.payment_status})</p>
                        <p>Delivery: {deliveryMethods[order.delivery_method]}</p>
                    </div>
                </div>
                <table className="table-x">
                    <thead><tr><th>#</th><th>Item</th><th className="text-right">Price</th><th className="text-right">Qty</th><th className="text-right">Total</th></tr></thead>
                    <tbody>
                        {order.items.map((i, n) => (
                            <tr key={i.id}><td>{n + 1}</td><td>{i.name}{i.variant_name && ` (${i.variant_name})`}<div className="text-xs text-slate-500">{i.sku}</div></td><td className="text-right">{money(i.price)}</td><td className="text-right">{i.quantity}</td><td className="text-right">{money(i.total)}</td></tr>
                        ))}
                    </tbody>
                </table>
                <div className="ml-auto mt-4 max-w-xs space-y-1">
                    <div className="flex justify-between"><span>Sub-Total</span><span>{money(order.subtotal)}</span></div>
                    <div className="flex justify-between"><span>Delivery</span><span>{money(order.delivery_charge)}</span></div>
                    {order.discount > 0 && <div className="flex justify-between"><span>Discount</span><span>-{money(order.discount)}</span></div>}
                    {order.tax > 0 && <div className="flex justify-between"><span>VAT / Tax</span><span>{money(order.tax)}</span></div>}
                    <div className="flex justify-between border-t border-slate-300 pt-1 text-base font-bold"><span>Total</span><span>{money(order.total)}</span></div>
                    {order.payment_status === 'partial' && <div className="flex justify-between text-sm"><span>Paid</span><span>{money(order.paid_amount)}</span></div>}
                    {order.payment_status === 'partial' && <div className="flex justify-between font-semibold text-red-600"><span>Due</span><span>{money(order.due_amount)}</span></div>}
                </div>
                <p className="mt-10 text-center text-xs text-slate-500">Thank you for shopping with {settings.site_name}!</p>
                <div className="no-print mt-6 text-center"><button onClick={() => window.print()} className="btn-primary">Print</button></div>
            </div>
        </div>
    );
}
