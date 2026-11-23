import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Breadcrumbs from '../Components/Breadcrumbs';
import { TextArea, TextInput } from '../Components/Field';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/format';
import { CouponBox } from './Cart';

function Step({ n, title, children }) {
    return (
        <div className="card p-5">
            <h2 className="mb-4 flex items-center gap-3 text-lg font-semibold">
                <span className="grid h-8 w-8 place-items-center rounded-full bg-brand-700 text-sm text-white">{n}</span>
                {title}
            </h2>
            {children}
        </div>
    );
}

export default function Checkout({ cart, paymentMethods, deliveryMethods, defaults }) {
    const { errors: pageErrors } = usePage().props;
    const form = useForm({ ...defaults, notes: '', delivery_method: 'home_delivery', payment_method: 'cod', agree: false });
    const [delivery, setDelivery] = useState(0);

    useEffect(() => {
        const t = setTimeout(() => {
            fetch('/checkout/quote', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
                body: JSON.stringify({ delivery_method: form.data.delivery_method, city: form.data.city }),
            })
                .then((r) => r.json())
                .then((d) => setDelivery(d.delivery_charge ?? 0))
                .catch(() => {});
        }, 300);
        return () => clearTimeout(t);
    }, [form.data.delivery_method, form.data.city]);

    const submit = (e) => {
        e.preventDefault();
        form.post('/checkout', { preserveScroll: true });
    };
    const total = cart.subtotal - cart.discount + delivery;
    const set = (k) => (e) => form.setData(k, e.target.value);

    return (
        <div className="container-x py-4">
            <Seo title="Checkout" />
            <Breadcrumbs items={[{ name: 'Shopping Cart', url: '/cart' }, { name: 'Checkout' }]} />
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1fr_380px]">
                <div className="space-y-6">
                    <Step n={1} title="Customer Information">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextInput label="First Name*" value={form.data.first_name} onChange={set('first_name')} error={form.errors.first_name} />
                            <TextInput label="Last Name" value={form.data.last_name} onChange={set('last_name')} error={form.errors.last_name} />
                            <TextInput label="Mobile*" value={form.data.phone} onChange={set('phone')} error={form.errors.phone} />
                            <TextInput label="Email" type="email" value={form.data.email} onChange={set('email')} error={form.errors.email} />
                            <TextArea label="Address*" rows={2} className="sm:col-span-2" value={form.data.address} onChange={set('address')} error={form.errors.address} />
                            <TextInput label="City / District*" value={form.data.city} onChange={set('city')} error={form.errors.city} hint="Delivery inside Dhaka is cheaper" />
                            <TextArea label="Comment" rows={2} value={form.data.notes} onChange={set('notes')} error={form.errors.notes} />
                        </div>
                    </Step>
                    <div className="grid gap-6 md:grid-cols-2">
                        <Step n={2} title="Payment Method">
                            {Object.entries(paymentMethods).map(([value, label]) => (
                                <label key={value} className="mb-2 flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="radio" name="payment" className="accent-brand-700" checked={form.data.payment_method === value} onChange={() => form.setData('payment_method', value)} />
                                    {label}
                                </label>
                            ))}
                            {form.data.payment_method !== 'cod' && <p className="mt-2 rounded bg-amber-50 p-2 text-xs text-amber-800">Our team will contact you with payment instructions after you place the order.</p>}
                        </Step>
                        <Step n={3} title="Delivery Method">
                            {Object.entries(deliveryMethods).map(([value, label]) => (
                                <label key={value} className="mb-2 flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="radio" name="delivery" className="accent-brand-700" checked={form.data.delivery_method === value} onChange={() => form.setData('delivery_method', value)} />
                                    {label}
                                </label>
                            ))}
                        </Step>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="card p-4"><CouponBox coupon={cart.coupon} /></div>
                    <div className="card p-4">
                        <h2 className="mb-3 font-semibold">Order Overview</h2>
                        <ul className="divide-y divide-slate-100 text-sm">
                            {cart.items.map((i) => (
                                <li key={i.id} className="flex justify-between gap-3 py-2">
                                    <span>{i.product.name} <span className="text-slate-500">× {i.quantity}</span></span>
                                    <span className="shrink-0">{money(i.total)}</span>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-3 space-y-1 border-t border-slate-200 pt-3 text-sm">
                            <div className="flex justify-between"><span>Sub-Total</span><span>{money(cart.subtotal)}</span></div>
                            <div className="flex justify-between"><span>Delivery Charge</span><span>{delivery ? money(delivery) : 'Free'}</span></div>
                            {cart.discount > 0 && <div className="flex justify-between text-green-700"><span>Discount</span><span>-{money(cart.discount)}</span></div>}
                            <div className="flex justify-between pt-2 text-base font-bold"><span>Total</span><span className="text-accent-600">{money(total)}</span></div>
                        </div>
                        <label className="mt-4 flex items-start gap-2 text-sm">
                            <input type="checkbox" className="mt-1 accent-brand-700" checked={form.data.agree} onChange={(e) => form.setData('agree', e.target.checked)} />
                            <span>I have read and agree to the <Link href="/page/terms-and-conditions" className="text-accent-600 hover:underline">Terms and Conditions</Link>, <Link href="/page/privacy-policy" className="text-accent-600 hover:underline">Privacy Policy</Link> and <Link href="/page/refund-policy" className="text-accent-600 hover:underline">Refund and Return Policy</Link></span>
                        </label>
                        {form.errors.agree && <p className="mt-1 text-xs text-red-600">{form.errors.agree}</p>}
                        {(form.errors.cart || pageErrors.cart) && <p className="mt-2 rounded bg-red-50 p-2 text-xs text-red-700">{form.errors.cart || pageErrors.cart}</p>}
                        <button disabled={form.processing} className="btn-accent mt-4 w-full py-3">{form.processing ? 'Placing order…' : 'Confirm Order'}</button>
                    </div>
                </div>
            </form>
        </div>
    );
}

Checkout.layout = (page) => <ShopLayout>{page}</ShopLayout>;
