import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Breadcrumbs from '../Components/Breadcrumbs';
import Icon from '../Components/Icon';
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

const normalizePhone = (p) => {
    let d = String(p ?? '').replace(/\D+/g, '');
    if (d.startsWith('880')) d = d.slice(2);
    return d;
};

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(first || data.message || (res.status === 429 ? 'Too many tries. Please wait a few minutes.' : 'Something went wrong.'));
    }
    return data;
}

/** Sends an SMS code to the mobile number and confirms it. */
function PhoneVerify({ phone, verified, onVerified }) {
    const [sentTo, setSentTo] = useState(null);
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState(null);
    const current = normalizePhone(phone);
    const isVerified = verified.includes(current);

    if (isVerified) {
        return <p className="mt-1.5 flex items-center gap-1 text-xs font-semibold text-green-700"><Icon name="check" className="h-3.5 w-3.5" /> Number verified</p>;
    }
    const send = async () => {
        setBusy(true); setMsg(null);
        try { const r = await postJson('/checkout/otp/send', { phone }); setSentTo(current); setCode(''); setMsg({ ok: true, text: r.message }); }
        catch (e) { setMsg({ ok: false, text: e.message }); }
        finally { setBusy(false); }
    };
    const verify = async () => {
        setBusy(true); setMsg(null);
        try { const r = await postJson('/checkout/otp/verify', { phone, code }); onVerified(r.verified); }
        catch (e) { setMsg({ ok: false, text: e.message }); }
        finally { setBusy(false); }
    };
    const codeSent = sentTo === current;
    return (
        <div className="mt-2 rounded-md bg-slate-50 p-3 ring-1 ring-slate-200">
            <p className="mb-2 text-xs text-slate-600">We'll text you a 6-digit code to confirm this number.</p>
            <div className="flex flex-wrap gap-2">
                {codeSent && (
                    <input className="input w-32 tracking-[0.3em]" inputMode="numeric" maxLength={6} placeholder="Code" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} aria-label="Verification code" />
                )}
                {codeSent && <button type="button" onClick={verify} disabled={busy || code.length !== 6} className="btn-primary">Verify</button>}
                <button type="button" onClick={send} disabled={busy || current.length < 11} className="btn-outline">{codeSent ? 'Resend code' : 'Send code'}</button>
            </div>
            {msg && <p className={`mt-2 text-xs ${msg.ok ? 'text-green-700' : 'text-red-600'}`} role="status">{msg.text}</p>}
        </div>
    );
}

export default function Checkout({ cart, paymentMethods, deliveryMethods, defaults, initialDelivery = 0, otp = { required: false, verified: [] } }) {
    const [verifiedPhones, setVerifiedPhones] = useState(otp.verified ?? []);
    const { errors: pageErrors } = usePage().props;
    const form = useForm({ ...defaults, notes: '', delivery_method: 'home_delivery', payment_method: Object.keys(paymentMethods)[0] ?? 'cod', agree: false });
    const [delivery, setDelivery] = useState(initialDelivery);

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
                            <div>
                                <TextInput label="Mobile*" value={form.data.phone} onChange={set('phone')} error={form.errors.phone} />
                                {otp.required && <PhoneVerify phone={form.data.phone} verified={verifiedPhones} onVerified={setVerifiedPhones} />}
                            </div>
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
                            {form.errors.payment_method && <p className="mt-1 text-xs text-red-600">{form.errors.payment_method}</p>}
                            {form.data.payment_method !== 'cod' && (
                                <p className="mt-2 flex items-start gap-1.5 rounded bg-blue-50 p-2 text-xs text-blue-800">
                                    <Icon name="shield" className="mt-px h-3.5 w-3.5 shrink-0" /> After you confirm, you'll be taken to {paymentMethods[form.data.payment_method]} to pay securely, then brought back here.
                                </p>
                            )}
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
