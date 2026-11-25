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
                        <li key={`${p.id}-${p.variant_id ?? ''}`} role="option" aria-selected={i === active}>
                            <button type="button" onMouseEnter={() => setActive(i)} onClick={() => pick(p)} disabled={!p.purchasable}
                                className={`flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left ${i === active ? 'bg-primary-50' : ''} ${p.purchasable ? '' : 'cursor-not-allowed opacity-50'}`}>
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 bg-white p-0.5">{p.image_url ? <img src={p.image_url} alt="" className="max-h-full max-w-full object-contain" /> : <Icon name="box" className="h-4 w-4 text-slate-300" />}</span>
                                <span className="min-w-0 flex-1">
                                    <span className="line-clamp-1 text-sm font-medium text-slate-900">{p.name}{p.variant_name && <span className="text-primary-700"> · {p.variant_name}</span>}</span>
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

export default function Create({ order = null, paymentMethods, deliveryMethods, sources, statuses, deliveryRates, defaultVat = 0, smsReady }) {
    const editing = !!order;
    const form = useForm(editing ? {
        source: order.source === 'website' ? 'other' : order.source,
        status: order.status,
        items: order.items,
        user_id: order.user_id,
        first_name: order.first_name ?? '', last_name: order.last_name ?? '', phone: order.phone ?? '', email: order.email ?? '', address: order.address ?? '', city: order.city ?? 'Dhaka',
        notes: order.notes ?? '',
        delivery_method: order.delivery_method,
        delivery_charge: order.delivery_charge,
        discount_type: 'amount',
        discount_value: order.discount || '',
        tax_type: 'amount',
        tax_value: order.tax || '',
        payment_method: order.payment_method,
        payment_status: order.payment_status === 'refunded' ? 'paid' : order.payment_status,
        paid_amount: order.paid_amount || '',
        notify: false,
    } : {
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
        tax_type: 'percent',
        tax_value: defaultVat || '',
        payment_method: 'cod',
        payment_status: 'unpaid',
        paid_amount: '',
        notify: smsReady,
    });
    const d = form.data;
    const e = form.errors;
    const [fullName, setFullName] = useState(editing ? [order.first_name, order.last_name].filter(Boolean).join(' ') : '');
    const [manualDelivery, setManualDelivery] = useState(editing);
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
    const taxValue = Number(d.tax_value) || 0;
    const tax = d.tax_type === 'percent' ? Math.round((subtotal - discount) * Math.min(taxValue, 100)) / 100 : taxValue;
    const total = Math.max(0, Math.round((subtotal - discount + tax + delivery) * 100) / 100);
    const paidNow = d.payment_status === 'paid' ? total : d.payment_status === 'partial' ? Math.min(Number(d.paid_amount) || 0, total) : 0;
    const due = Math.max(0, total - paidNow);

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
        const existing = d.items.findIndex((i) => i.product_id === p.id && (i.variant_id ?? null) === (p.variant_id ?? null));
        if (existing >= 0) {
            form.setData('items', d.items.map((i, j) => (j === existing ? { ...i, quantity: i.quantity + 1 } : i)));
        } else {
            form.setData('items', [...d.items, { product_id: p.id, variant_id: p.variant_id ?? null, quantity: 1, price: p.price, meta: p }]);
        }
        setAdding(false);
    };
    const setItem = (idx, patch) => form.setData('items', d.items.map((i, j) => (j === idx ? { ...i, ...patch } : i)));

    // Look the customer up once the number is complete.
    const firstLookup = useRef(editing);
    useEffect(() => {
        const phone = normalize(d.phone);
        if (phone.length !== 11) { setLookup(null); return; }
        const t = setTimeout(() => {
            fetch(`/admin/customers/lookup?phone=${phone}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((r) => {
                    setLookup(r);
                    if (firstLookup.current) { firstLookup.current = false; return; } // editing: keep the saved details
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
        form.transform((data) => ({ ...data, items: data.items.map(({ product_id, variant_id, quantity, price }) => ({ product_id, variant_id: variant_id ?? null, quantity, price })) }));
        if (editing) form.put(`/admin/orders/${order.id}/details`, { preserveScroll: true });
        else form.post('/admin/orders', { preserveScroll: true });
    };

    const itemErrors = Object.entries(e).filter(([k]) => k.startsWith('items'));
    const customerLabel = fullName.trim() || (inShop ? 'Walk-in customer' : 'Unnamed customer');

    return (
        <AdminLayout
            title={editing ? `Edit Order #${order.order_number}` : 'Create Order'}
            subtitle={editing ? 'Removed items go back to stock; added items are taken from stock.' : undefined}
            back={editing ? `/admin/orders/${order.id}` : '/admin/orders'}
            actions={
                <>
                    <Link href={editing ? `/admin/orders/${order.id}` : '/admin/orders'} className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</Link>
                    <button form="order-create" disabled={form.processing || d.items.length === 0} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : editing ? 'Save changes' : 'Create'}</button>
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
                                                        <tr key={`${i.product_id}-${i.variant_id ?? ''}`}>
                                                            <td className="pl-0">
                                                                <div className="flex items-center gap-3">
                                                                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-0.5">{m.image_url ? <img src={m.image_url} alt="" className="max-h-full max-w-full object-contain" /> : <Icon name="box" className="h-4 w-4 text-slate-300" />}</span>
                                                                    <div className="min-w-0">
                                                                        <p className="line-clamp-1 font-medium text-slate-900">{m.name}</p>
                                                                        {m.variant_name && <p className="text-xs font-medium text-primary-700">{m.variant_name}</p>}
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
                            <SubCard title="VAT / Tax" icon="receipt" tone="primary">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="label text-xs" htmlFor="tax-pct">Percentage</label>
                                        <div className="relative">
                                            <input id="tax-pct" className="input pr-7" type="number" min="0" max="100" step="0.5"
                                                value={d.tax_type === 'percent' ? d.tax_value : (subtotal - discount > 0 && tax ? +((tax / (subtotal - discount)) * 100).toFixed(1) : '')}
                                                onChange={(ev) => form.setData((c) => ({ ...c, tax_type: 'percent', tax_value: ev.target.value }))} />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label className="label text-xs" htmlFor="tax-amt">Amount</label>
                                        <input id="tax-amt" className="input" type="number" min="0" step="1"
                                            value={d.tax_type === 'amount' ? d.tax_value : (tax || '')}
                                            onChange={(ev) => form.setData((c) => ({ ...c, tax_type: 'amount', tax_value: ev.target.value }))} />
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
                                <Segmented label="Payment status" value={d.payment_status} onChange={(v) => form.setData('payment_status', v)} options={[['paid', 'Fully Paid', 'check'], ['partial', 'Partial', 'refresh'], ['unpaid', 'Unpaid']]} />
                                {d.payment_status === 'partial' && (
                                    <div className="mt-3">
                                        <label className="label text-xs" htmlFor="paid-amt">Paid amount (৳)</label>
                                        <input id="paid-amt" className="input" type="number" min="0" step="1" value={d.paid_amount} onChange={(ev) => form.setData('paid_amount', ev.target.value)} />
                                        {e.paid_amount && <p className="mt-1 text-xs text-red-600">{e.paid_amount}</p>}
                                    </div>
                                )}
                                <label className="label mt-3 text-xs" htmlFor="pay-method">Method</label>
                                <select id="pay-method" className="input" value={d.payment_method} onChange={(ev) => form.setData('payment_method', ev.target.value)}>
                                    {Object.entries(paymentMethods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                </select>
                            </SubCard>
                        </div>
                        <label className="label mt-4" htmlFor="order-note">Note</label>
                        <textarea id="order-note" className="input" rows={2} value={d.notes} onChange={(ev) => form.setData('notes', ev.target.value)} placeholder="Gift wrap, call before delivery…" />
                        <div className="mt-6 flex flex-wrap items-end justify-between gap-4 border-t border-slate-200 pt-6">
                            <dl className="grid grid-cols-[auto_auto] gap-x-8 gap-y-1 text-sm text-slate-600">
                                <dt>Sub-total</dt><dd className="text-right">{money(subtotal)}</dd>
                                <dt>Delivery</dt><dd className="text-right">{money(delivery)}</dd>
                                {discount > 0 && <><dt>Discount</dt><dd className="text-right text-green-700">-{money(discount)}</dd></>}
                                {tax > 0 && <><dt>VAT / Tax</dt><dd className="text-right">{money(tax)}</dd></>}
                                {d.payment_status === 'partial' && <><dt>Paid</dt><dd className="text-right">{money(paidNow)}</dd><dt className="font-semibold text-slate-900">Due</dt><dd className="text-right font-semibold text-red-600">{money(due)}</dd></>}
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
