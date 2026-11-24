import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import CustomerValidity from '../../../Components/Admin/CustomerValidity';
import { EmptyState, Panel, SubCard } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { money } from '../../../lib/format';

const normalize = (p) => {
    let d = String(p ?? '').replace(/\D+/g, '');
    if (d.startsWith('880')) d = d.slice(2);
    return d;
};

function Segmented({ value, onChange, options, label }) {
    return (
        <div role="radiogroup" aria-label={label} className="flex flex-wrap rounded-lg bg-slate-100 p-1">
            {options.map(([v, l, icon]) => (
                <button key={v} type="button" role="radio" aria-checked={value === v} onClick={() => onChange(v)}
                    className={`flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-3 py-1.5 text-sm transition ${value === v ? 'bg-white font-medium text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}>
                    {icon && <Icon name={icon} className="h-3.5 w-3.5" />} {l}
                </button>
            ))}
        </div>
    );
}

/** Search box with a results list; picks a product. */
function ProductSearch({ onPick, autoFocus }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const box = useRef(null);

    useEffect(() => {
        if (q.trim().length < 2) { setResults([]); return; }
        setLoading(true);
        const t = setTimeout(() => {
            fetch(`/admin/products/search?q=${encodeURIComponent(q.trim())}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json()).then((r) => { setResults(r); setActive(0); setOpen(true); })
                .catch(() => setResults([])).finally(() => setLoading(false));
        }, 250);
        return () => clearTimeout(t);
    }, [q]);

    useEffect(() => {
        const close = (e) => box.current && !box.current.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const pick = (p) => { if (!p.purchasable) return; onPick(p); setQ(''); setResults([]); setOpen(false); };

    return (
        <div className="relative" ref={box}>
            <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input autoFocus={autoFocus} className="input h-11 pl-9" placeholder="Search products by name or SKU…" value={q} aria-label="Search products"
                onChange={(e) => setQ(e.target.value)} onFocus={() => results.length && setOpen(true)}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(a + 1, results.length - 1)); }
                    if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
                    if (e.key === 'Enter') { e.preventDefault(); results[active] && pick(results[active]); }
                    if (e.key === 'Escape') setOpen(false);
                }} />
            {loading && <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>}
            {open && q.trim().length >= 2 && !loading && (
                <ul className="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg" role="listbox">
                    {results.length === 0 && <li className="px-3 py-3 text-sm text-slate-500">No products match "{q}".</li>}
                    {results.map((p, i) => (
                        <li key={p.id} role="option" aria-selected={i === active}>
                            <button type="button" onMouseEnter={() => setActive(i)} onClick={() => pick(p)} disabled={!p.purchasable}
                                className={`flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left ${i === active ? 'bg-primary-50' : ''} ${p.purchasable ? '' : 'cursor-not-allowed opacity-50'}`}>
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 bg-white p-0.5">{p.image_url ? <img src={p.image_url} alt="" className="max-h-full max-w-full object-contain" /> : <Icon name="box" className="h-4 w-4 text-slate-300" />}</span>
                                <span className="min-w-0 flex-1">
                                    <span className="line-clamp-1 text-sm font-medium text-slate-900">{p.name}</span>
                                    <span className="text-xs text-slate-500">{p.sku} · {p.stock_status === 'in_stock' ? `${p.stock} in stock` : p.stock_label}</span>
                                </span>
                                <span className="shrink-0 text-sm font-semibold">{money(p.price)}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Create({ paymentMethods, deliveryMethods, sources, statuses, deliveryRates, smsReady }) {
    const form = useForm({
        source: 'phone',
        status: 'processing',
        items: [],
        user_id: null,
        first_name: '', last_name: '', phone: '', email: '', address: '', city: 'Dhaka',
        notes: '',
        delivery_method: 'home_delivery',
        delivery_charge: deliveryRates.inside,
        discount_type: 'amount',
        discount_value: '',
        payment_method: 'cod',
        payment_status: 'unpaid',
        notify: smsReady,
    });
    const d = form.data;
    const e = form.errors;
    const [fullName, setFullName] = useState('');
    const [manualDelivery, setManualDelivery] = useState(false);
    const [lookup, setLookup] = useState(null);
    const [adding, setAdding] = useState(false);
    const inShop = d.source === 'in_shop';

    const subtotal = useMemo(() => d.items.reduce((s, i) => s + (Number(i.price) || 0) * (Number(i.quantity) || 0), 0), [d.items]);
    const discountValue = Number(d.discount_value) || 0;
    const discount = d.discount_type === 'percent' ? Math.round(subtotal * Math.min(discountValue, 100)) / 100 : Math.min(discountValue, subtotal);
    const autoDelivery = d.delivery_method === 'store_pickup' ? 0
        : d.delivery_method === 'express' ? deliveryRates.express
        : deliveryRates.freeOver > 0 && subtotal >= deliveryRates.freeOver ? 0
        : d.city.trim().toLowerCase() === 'dhaka' ? deliveryRates.inside : deliveryRates.outside;
    const delivery = d.delivery_method === 'store_pickup' ? 0 : Number(d.delivery_charge) || 0;
    const total = Math.max(0, subtotal - discount + delivery);

    // Keep the delivery charge in step with method, city and subtotal until someone types their own.
    useEffect(() => { if (!manualDelivery) form.setData('delivery_charge', autoDelivery); }, [autoDelivery]); // eslint-disable-line react-hooks/exhaustive-deps

    const setSource = (v) => {
        form.setData((cur) => ({
            ...cur,
            source: v,
            ...(v === 'in_shop'
                ? { delivery_method: 'store_pickup', payment_method: 'cash', payment_status: 'paid', status: 'delivered', notify: false }
                : cur.source === 'in_shop' ? { delivery_method: 'home_delivery', payment_method: 'cod', payment_status: 'unpaid', status: 'processing', notify: smsReady } : {}),
        }));
    };

    const addProduct = (p) => {
        const existing = d.items.findIndex((i) => i.product_id === p.id);
        if (existing >= 0) {
            form.setData('items', d.items.map((i, j) => (j === existing ? { ...i, quantity: i.quantity + 1 } : i)));
        } else {
            form.setData('items', [...d.items, { product_id: p.id, quantity: 1, price: p.price, meta: p }]);
        }
        setAdding(false);
    };
    const setItem = (idx, patch) => form.setData('items', d.items.map((i, j) => (j === idx ? { ...i, ...patch } : i)));

    // Look the customer up once the number is complete.
    useEffect(() => {
        const phone = normalize(d.phone);
        if (phone.length !== 11) { setLookup(null); return; }
        const t = setTimeout(() => {
            fetch(`/admin/customers/lookup?phone=${phone}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((r) => {
                    setLookup(r);
                    if (!r.found) { form.setData('user_id', null); return; }
                    const src = r.last ?? {};
                    const name = r.user?.name ?? [src.first_name, src.last_name].filter(Boolean).join(' ');
                    form.setData((cur) => ({
                        ...cur,
                        user_id: r.user?.id ?? null,
                        email: cur.email || r.user?.email || src.email || '',
                        address: cur.address || src.address || r.user?.address || '',
                        city: (cur.city && cur.city !== 'Dhaka') ? cur.city : (src.city || r.user?.city || cur.city),
                    }));
                    if (!fullName && name) setName(name);
                })
                .catch(() => setLookup(null));
        }, 300);
        return () => clearTimeout(t);
    }, [d.phone]); // eslint-disable-line react-hooks/exhaustive-deps

    const setName = (v) => {
        setFullName(v);
        const [first, ...rest] = v.trim().split(/\s+/);
        form.setData((cur) => ({ ...cur, first_name: first ?? '', last_name: rest.join(' ') }));
    };

    const submit = (ev) => {
        ev.preventDefault();
        form.transform((data) => ({ ...data, items: data.items.map(({ product_id, quantity, price }) => ({ product_id, quantity, price })) }));
        form.post('/admin/orders', { preserveScroll: true });
    };

    const itemErrors = Object.entries(e).filter(([k]) => k.startsWith('items'));
    const customerLabel = fullName.trim() || (inShop ? 'Walk-in customer' : 'Unnamed customer');

    return (
        <AdminLayout
            title="Create Order"
            back="/admin/orders"
            actions={
                <>
                    <Link href="/admin/orders" className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</Link>
                    <button form="order-create" disabled={form.processing || d.items.length === 0} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Creating…' : 'Create'}</button>
                </>
            }
        >
            <form id="order-create" onSubmit={submit} className="grid items-start gap-6 xl:grid-cols-[1fr_440px]">
                <div className="space-y-6">
                    <Panel title="Products" actions={d.items.length > 0 && <span className="text-sm text-slate-500">{d.items.reduce((n, i) => n + Number(i.quantity || 0), 0)} items</span>}>
                        {d.items.length === 0 && !adding ? (
                            <EmptyState title="No Products Added" text="Start building this order by adding products from your inventory."
                                action={<button type="button" onClick={() => setAdding(true)} className="btn-primary rounded-lg px-6 py-3 text-base"><Icon name="plus" className="h-4 w-4" /> Add Your First Product</button>} />
                        ) : (
                            <div className="space-y-4">
                                <ProductSearch onPick={addProduct} autoFocus={adding} />
                                {d.items.length > 0 && (
                                    <div className="overflow-x-auto">
                                        <table className="table-admin min-w-[620px]">
                                            <thead><tr><th className="min-w-[240px] pl-0">Product</th><th className="w-32">Qty</th><th className="w-36">Unit price</th><th className="text-right">Total</th><th className="w-px" /></tr></thead>
                                            <tbody>
                                                {d.items.map((i, idx) => {
                                                    const m = i.meta ?? {};
                                                    const over = m.stock_status === 'in_stock' && i.quantity > m.stock;
                                                    return (
                                                        <tr key={i.product_id}>
                                                            <td className="pl-0">
                                                                <div className="flex items-center gap-3">
                                                                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-0.5">{m.image_url ? <img src={m.image_url} alt="" className="max-h-full max-w-full object-contain" /> : <Icon name="box" className="h-4 w-4 text-slate-300" />}</span>
                                                                    <div className="min-w-0">
                                                                        <p className="line-clamp-1 font-medium text-slate-900">{m.name}</p>
                                                                        <p className={`text-xs ${over ? 'text-red-600' : 'text-slate-500'}`}>{m.sku} · {m.stock_status === 'in_stock' ? `${m.stock} in stock` : m.stock_label}</p>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div className="flex items-center rounded-lg border border-slate-300">
                                                                    <button type="button" onClick={() => setItem(idx, { quantity: Math.max(1, i.quantity - 1) })} className="grid h-9 w-8 place-items-center text-slate-500 hover:text-slate-900" aria-label="Decrease"><Icon name="minus" className="h-3.5 w-3.5" /></button>
                                                                    <input className="h-9 w-full min-w-0 border-x border-slate-200 text-center text-sm outline-none" inputMode="numeric" value={i.quantity} onChange={(ev) => setItem(idx, { quantity: Math.max(1, parseInt(ev.target.value.replace(/\D/g, ''), 10) || 1) })} aria-label="Quantity" />
                                                                    <button type="button" onClick={() => setItem(idx, { quantity: i.quantity + 1 })} className="grid h-9 w-8 place-items-center text-slate-500 hover:text-slate-900" aria-label="Increase"><Icon name="plus" className="h-3.5 w-3.5" /></button>
                                                                </div>
                                                            </td>
                                                            <td><input className="input h-9 py-1" type="number" min="0" step="1" value={i.price} onChange={(ev) => setItem(idx, { price: ev.target.value })} aria-label="Unit price" /></td>
                                                            <td className="whitespace-nowrap text-right font-medium">{money((Number(i.price) || 0) * i.quantity)}</td>
                                                            <td><button type="button" onClick={() => form.setData('items', d.items.filter((_, j) => j !== idx))} className="icon-btn h-9 w-9 hover:border-red-300 hover:text-red-600" aria-label="Remove"><Icon name="trash" className="h-4 w-4" /></button></td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}
                        {itemErrors.length > 0 && <p className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{itemErrors[0][1]}</p>}
                    </Panel>

                    <Panel title="Order Summary">
                        <div className="grid gap-4 md:grid-cols-2">
                            <SubCard title="Discount" icon="percent" tone="orange">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="label text-xs" htmlFor="disc-pct">Percentage</label>
                                        <div className="relative">
                                            <input id="disc-pct" className="input pr-7" type="number" min="0" max="100" step="0.5"
                                                value={d.discount_type === 'percent' ? d.discount_value : (subtotal ? +((discount / subtotal) * 100).toFixed(1) || '' : '')}
                                                onChange={(ev) => form.setData((c) => ({ ...c, discount_type: 'percent', discount_value: ev.target.value }))} />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label className="label text-xs" htmlFor="disc-amt">Amount</label>
                                        <input id="disc-amt" className="input" type="number" min="0" step="1"
                                            value={d.discount_type === 'amount' ? d.discount_value : (discount || '')}
                                            onChange={(ev) => form.setData((c) => ({ ...c, discount_type: 'amount', discount_value: ev.target.value }))} />
                                    </div>
                                </div>
                            </SubCard>
                            <SubCard title="Delivery" icon="truck" tone="blue">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="label text-xs" htmlFor="del-method">Method</label>
                                        <select id="del-method" className="input" value={d.delivery_method} onChange={(ev) => { setManualDelivery(false); form.setData('delivery_method', ev.target.value); }}>
                                            {Object.entries(deliveryMethods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="label text-xs" htmlFor="del-charge">Charge (৳)</label>
                                        <input id="del-charge" className="input" type="number" min="0" step="1" disabled={d.delivery_method === 'store_pickup'} value={d.delivery_method === 'store_pickup' ? 0 : d.delivery_charge}
                                            onChange={(ev) => { setManualDelivery(true); form.setData('delivery_charge', ev.target.value); }} />
                                    </div>
                                </div>
                                {manualDelivery && <button type="button" onClick={() => { setManualDelivery(false); form.setData('delivery_charge', autoDelivery); }} className="mt-2 text-xs font-medium text-primary-700 hover:underline">Use standard charge ({money(autoDelivery)})</button>}
                            </SubCard>
                            <SubCard title="Payment" icon="card" tone="green">
                                <Segmented label="Payment status" value={d.payment_status} onChange={(v) => form.setData('payment_status', v)} options={[['paid', 'Fully Paid', 'check'], ['unpaid', 'Unpaid']]} />
                                <label className="label mt-3 text-xs" htmlFor="pay-method">Method</label>
                                <select id="pay-method" className="input" value={d.payment_method} onChange={(ev) => form.setData('payment_method', ev.target.value)}>
                                    {Object.entries(paymentMethods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                </select>
                            </SubCard>
                            <SubCard title="Note" icon="chat">
                                <textarea className="input" rows={3} value={d.notes} onChange={(ev) => form.setData('notes', ev.target.value)} placeholder="Gift wrap, call before delivery…" aria-label="Order note" />
                            </SubCard>
                        </div>
                        <div className="mt-6 flex flex-wrap items-end justify-between gap-4 border-t border-slate-200 pt-6">
                            <dl className="grid grid-cols-[auto_auto] gap-x-8 gap-y-1 text-sm text-slate-600">
                                <dt>Sub-total</dt><dd className="text-right">{money(subtotal)}</dd>
                                <dt>Delivery</dt><dd className="text-right">{money(delivery)}</dd>
                                {discount > 0 && <><dt>Discount</dt><dd className="text-right text-green-700">-{money(discount)}</dd></>}
                            </dl>
                            <div className="rounded-xl border-2 border-primary-200 bg-primary-50/60 px-6 py-3 text-center">
                                <p className="text-sm font-medium text-primary-700">Grand Total</p>
                                <p className="text-3xl font-bold text-primary-700">{money(total)}</p>
                            </div>
                        </div>
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Order Information">
                        <div className="grid gap-4">
                            <div>
                                <span className="label">Order Type</span>
                                <Segmented label="Order type" value={d.source} onChange={setSource} options={Object.entries(sources).map(([v, l]) => [v, l.split(' /')[0], { phone: 'phone', facebook: 'chat', in_shop: 'store' }[v]])} />
                            </div>
                            <div>
                                <label className="label" htmlFor="order-status">Order Status</label>
                                <select id="order-status" className="input" value={d.status} onChange={(ev) => form.setData('status', ev.target.value)}>
                                    {statuses.map((s) => <option key={s} value={s}>{s === 'delivered' ? 'Delivered (completed)' : s[0].toUpperCase() + s.slice(1)}</option>)}
                                </select>
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Customer Information">
                        <div className="mb-5 flex items-center gap-3 border-b border-slate-200 pb-5">
                            <span className="grid h-11 w-11 place-items-center rounded-full bg-primary-100 text-primary-700"><Icon name="user" className="h-5 w-5" /></span>
                            <div className="min-w-0">
                                <p className={`truncate ${fullName ? 'font-medium text-slate-900' : 'text-slate-500'}`}>{customerLabel}</p>
                                <p className="text-xs text-slate-500">{d.phone ? d.phone : 'No contact details'}{lookup?.found && ' · Returning customer'}</p>
                            </div>
                        </div>
                        <p className="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">Contact details{inShop && ' (optional)'}</p>
                        <div className="grid gap-4">
                            {[
                                ['phone', 'Customer Phone', 'phone', 'Enter customer phone', d.phone, (v) => form.setData('phone', v)],
                                ['name', 'Customer Name', 'user', 'Enter customer name', fullName, setName],
                                ['email', 'Customer Email', 'mail', 'Enter customer email', d.email, (v) => form.setData('email', v)],
                                ['address', 'Shipping Address', 'pin', 'Enter shipping address', d.address, (v) => form.setData('address', v)],
                                ['city', 'City / District', 'pin', 'e.g. Dhaka', d.city, (v) => form.setData('city', v)],
                            ].map(([key, label, icon, ph, value, onChange]) => (
                                <div key={key}>
                                    <label className="label" htmlFor={`c-${key}`}>{label}</label>
                                    <div className="relative">
                                        <Icon name={icon} className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input id={`c-${key}`} className="input pl-9" value={value} placeholder={ph} onChange={(ev) => onChange(ev.target.value)} type={key === 'email' ? 'email' : 'text'} inputMode={key === 'phone' ? 'tel' : undefined} />
                                    </div>
                                    {e[key === 'name' ? 'first_name' : key] && <p className="mt-1 text-xs text-red-600">{e[key === 'name' ? 'first_name' : key]}</p>}
                                </div>
                            ))}
                            {lookup?.validity && <CustomerValidity phone={normalize(d.phone)} initial={lookup.validity} />}
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-0.5 h-4 w-4 accent-primary-600" checked={d.notify} onChange={(ev) => form.setData('notify', ev.target.checked)} disabled={!d.phone} />
                                <span>Send the order confirmation to the customer<span className="block text-xs text-slate-500">{smsReady ? 'By SMS (and email if given), as set in Settings → Notifications.' : 'By email only. SMS is not set up.'}</span></span>
                            </label>
                        </div>
                    </Panel>
                </div>
            </form>
        </AdminLayout>
    );
}
