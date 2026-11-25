import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Panel, Tabs, Toggle } from '../../Components/Admin/UI';
import { Field, TextArea, TextInput } from '../../Components/Field';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';

const TABS = [
    { value: 'general', label: 'General' },
    { value: 'seo', label: 'SEO' },
    { value: 'tracking', label: 'Tracking' },
    { value: 'payments', label: 'Payments' },
    { value: 'couriers', label: 'Couriers' },
    { value: 'notifications', label: 'Notifications' },
    { value: 'fraud', label: 'Fraud check' },
    { value: 'ai', label: 'AI' },
];

const PLACEHOLDERS = ['{name}', '{order}', '{total}', '{status}', '{courier}', '{tracking_url}', '{site}', '{phone}'];

/** SMS length: 160 characters per part (70 when the text has Bangla or other non-GSM characters). */
function smsParts(text) {
    const unicode = /[^\x00-\x7F৳]/.test(text) || text.includes('৳');
    const n = text.length;
    const single = unicode ? 70 : 160;
    const multi = unicode ? 67 : 153;
    return { n, parts: n <= single ? 1 : Math.ceil(n / multi), unicode };
}

function TestSend({ label, url, field, placeholder, type = 'text' }) {
    const [value, setValue] = useState('');
    const [state, setState] = useState(null);
    const send = async () => {
        setState({ loading: true });
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
                body: JSON.stringify({ [field]: value }),
            });
            const body = await res.json().catch(() => ({}));
            const err = body.errors ? Object.values(body.errors)[0]?.[0] : null;
            setState({ ok: res.ok && body.ok, message: err || body.message || 'No answer.' });
        } catch {
            setState({ ok: false, message: 'Could not reach the server.' });
        }
    };
    return (
        <div>
            <span className="label">{label}</span>
            <div className="flex flex-wrap gap-2">
                <input className="input min-w-0 flex-1" type={type} value={value} onChange={(e) => setValue(e.target.value)} placeholder={placeholder} aria-label={label} />
                <button type="button" onClick={send} disabled={!value || state?.loading} className="btn-outline shrink-0">{state?.loading ? 'Sending…' : 'Send test'}</button>
            </div>
            {state && !state.loading && <p className={`mt-1 text-xs ${state.ok ? 'text-green-700' : 'text-red-600'}`} role="status">{state.message}</p>}
            {!state && <p className="mt-1 text-xs text-slate-500">Uses the saved settings. Save first.</p>}
        </div>
    );
}

/** "Test connection" for one AI provider (uses the saved key). */
function TestAi({ provider }) {
    const [state, setState] = useState(null);
    const run = async () => {
        setState({ loading: true });
        try {
            const res = await fetch('/admin/settings/test-ai', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
                body: JSON.stringify({ provider }),
            });
            const body = await res.json().catch(() => ({}));
            setState({ ok: res.ok && body.ok, message: body.message || 'No answer.' });
        } catch {
            setState({ ok: false, message: 'Could not reach the server.' });
        }
    };
    return (
        <div>
            <button type="button" onClick={run} disabled={state?.loading} className="btn-outline btn-sm">{state?.loading ? 'Testing…' : 'Test connection'}</button>
            {state && !state.loading && <p className={`mt-1 text-xs ${state.ok ? 'text-green-700' : 'text-red-600'}`} role="status">{state.message}</p>}
        </div>
    );
}

const GATEWAYS = [
    {
        key: 'bkash',
        name: 'bKash',
        tone: 'bg-pink-50 text-pink-600',
        about: 'Tokenized Checkout. Get credentials from the bKash merchant portal (PGW).',
        fields: [['app_key', 'App Key'], ['app_secret', 'App Secret', true], ['username', 'Username'], ['password', 'Password', true]],
    },
    {
        key: 'nagad',
        name: 'Nagad',
        tone: 'bg-orange-50 text-orange-600',
        about: 'Remote payment gateway. Nagad gives you their public key; you generate the merchant key pair and share your public key with them.',
        fields: [['merchant_id', 'Merchant ID'], ['merchant_number', 'Merchant Number'], ['public_key', 'Nagad Public Key', false, true], ['private_key', 'Merchant Private Key', true, true]],
    },
    {
        key: 'sslcommerz',
        name: 'SSLCommerz',
        tone: 'bg-blue-50 text-blue-600',
        about: 'Cards, mobile banking and internet banking through one hosted checkout.',
        fields: [['store_id', 'Store ID'], ['store_password', 'Store Password', true]],
    },
    {
        key: 'eps',
        name: 'EPS',
        tone: 'bg-emerald-50 text-emerald-600',
        about: 'Easy Payment System hosted checkout.',
        fields: [['merchant_id', 'Merchant ID'], ['store_id', 'Store ID'], ['username', 'Username'], ['password', 'Password', true], ['hash_key', 'Hash Key', true]],
    },
];

const flag = (v) => v === true || v === '1' || v === 1;

function CharCount({ value, max }) {
    const n = (value ?? '').length;
    return <span className={`text-xs ${n > max ? 'text-red-600' : 'text-slate-400'}`}>{n}/{max}</span>;
}

function Copy({ label, value }) {
    const [done, setDone] = useState(false);
    return (
        <div>
            <span className="label">{label}</span>
            <div className="flex gap-2">
                <input readOnly className="input bg-slate-50 font-mono text-xs" value={value} onFocus={(e) => e.target.select()} aria-label={label} />
                <button type="button" className="btn-outline shrink-0 px-3" onClick={() => navigator.clipboard?.writeText(value).then(() => { setDone(true); setTimeout(() => setDone(false), 1500); })}>
                    <Icon name={done ? 'check' : 'file'} className="h-4 w-4" /> {done ? 'Copied' : 'Copy'}
                </button>
            </div>
        </div>
    );
}

function TestConnection({ courier, onStores }) {
    const [state, setState] = useState(null);
    const run = async () => {
        setState({ loading: true });
        try {
            const res = await fetch(`/admin/couriers/${courier}/test`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
            });
            const body = await res.json().catch(() => ({}));
            setState({ ok: res.ok && body.ok, message: body.message ?? 'No answer from the courier.' });
            if (body.stores && onStores) onStores(body.stores);
        } catch {
            setState({ ok: false, message: 'Could not reach the server.' });
        }
    };
    return (
        <div className="flex flex-wrap items-center gap-3">
            <button type="button" onClick={run} disabled={state?.loading} className="btn-outline"><Icon name="refresh" className="h-4 w-4" /> {state?.loading ? 'Testing…' : 'Test connection'}</button>
            {state && !state.loading && <span className={`text-sm ${state.ok ? 'text-green-700' : 'text-red-600'}`} role="status">{state.message}</span>}
            {!state && <span className="text-xs text-slate-500">Uses the saved credentials. Save first.</span>}
        </div>
    );
}

export default function Settings({ values, secretsSet, gateways, couriers, notifications, urls, ogImageUrl, ai }) {
    const [pathaoStores, setPathaoStores] = useState(null);
    const initial = useMemo(() => ({ ...values, seo_og_image: null, seo_og_image_remove: false }), [values]);
    const form = useForm(initial);
    const [tab, setTab] = useState(() => (typeof window !== 'undefined' && TABS.some((t) => `#${t.value}` === window.location.hash) ? window.location.hash.slice(1) : 'general'));
    useEffect(() => { window.history.replaceState(null, '', `#${tab}`); }, [tab]);

    const e = form.errors;
    const set = (k) => (ev) => form.setData(k, ev.target.value);
    const text = (k, label, props = {}) => <TextInput label={label} value={form.data[k] ?? ''} onChange={set(k)} error={e[k]} {...props} />;
    const secret = (k, label) => (
        <TextInput label={label} type="password" autoComplete="off" value={form.data[k] ?? ''} onChange={set(k)} error={e[k]} placeholder={secretsSet[k] ? '•••••••• saved — leave blank to keep' : ''} />
    );
    const courierBadge = (k) => {
        const st = couriers[k];
        return <span className={`badge shrink-0 rounded-md ring-1 ring-inset ring-current/20 ${st.live ? 'bg-green-50 text-green-700' : st.configured ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'}`}>{st.live ? 'On' : st.configured ? 'Off' : 'Not set up'}</span>;
    };
    const toggle = (k, label, description) => <Toggle label={label} description={description} checked={flag(form.data[k])} onChange={(v) => form.setData(k, v ? '1' : '0')} />;

    // Jump to the tab holding the first error.
    useEffect(() => {
        const keys = Object.keys(e);
        if (!keys.length) return;
        const k = keys[0];
        const where = /^ai_/.test(k) ? 'ai' : /^(fraud_|bdcourier)/.test(k) ? 'fraud' : /^(sms_|mail_|notify_|checkout_otp)/.test(k) ? 'notifications' : /^(steadfast|pathao|courier)_/.test(k) ? 'couriers' : /^(bkash|nagad|sslcommerz|eps|cod)_/.test(k) ? 'payments' : /^(seo_|google_|bing_|facebook_domain)/.test(k) ? 'seo' : /(pixel|gtm|ga_)/.test(k) ? 'tracking' : 'general';
        setTab(where);
    }, [e]);

    const submit = (ev) => {
        ev.preventDefault();
        form.transform((d) => ({ ...d, _method: 'put' }));
        form.post('/admin/settings', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData((d) => ({ ...d, seo_og_image: null, seo_og_image_remove: false, ...Object.fromEntries(Object.keys(secretsSet).map((k) => [k, ''])) })),
        });
    };

    const ogPreview = form.data.seo_og_image ? URL.createObjectURL(form.data.seo_og_image) : form.data.seo_og_image_remove ? null : ogImageUrl;
    const homeTitle = form.data.seo_title || `${form.data.site_name} - ${form.data.tagline}`;

    return (
        <AdminLayout
            title="Store Settings"
            subtitle="Store details, SEO, tracking, payments and couriers"
            actions={
                <>
                    <button type="button" onClick={() => form.reset()} disabled={!form.isDirty} className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Discard</button>
                    <button form="settings-form" disabled={form.processing} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : 'Save Settings'}</button>
                </>
            }
        >
            <div className="panel mb-6 px-4 py-3"><Tabs tabs={TABS} value={tab} onChange={setTab} /></div>

            <form id="settings-form" onSubmit={submit}>
                {tab === 'general' && (
                    <div className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                        <Panel title="Store Information" icon="store">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {text('site_name', 'Store Name')}
                                {text('tagline', 'Tagline')}
                                {text('phone', 'Phone / Hotline')}
                                {text('email', 'Support Email', { type: 'email' })}
                                <TextArea label="Address" className="sm:col-span-2" rows={2} value={form.data.address ?? ''} onChange={set('address')} />
                                <TextArea label="Notice" className="sm:col-span-2" rows={2} value={form.data.notice ?? ''} onChange={set('notice')} hint="Shown in the storefront header bar" />
                            </div>
                        </Panel>
                        <div className="space-y-6">
                            <Panel title="Loyalty Points & Referrals" icon="gift">
                                <div className="space-y-4">
                                    {toggle('loyalty_enabled', 'Loyalty points', 'Customers earn points when an order is delivered and use them at checkout.')}
                                    {flag(form.data.loyalty_enabled) && (
                                        <div className="grid grid-cols-2 gap-3">
                                            {text('loyalty_earn_per', '1 point per (৳ spent)', { type: 'number', min: 1 })}
                                            {text('loyalty_point_value', '1 point is worth (৳)', { type: 'number', min: 0.01, step: '0.01' })}
                                            {text('loyalty_min_redeem', 'Use from (points)', { type: 'number', min: 0 })}
                                            {text('loyalty_max_percent', 'Pay at most (% of items)', { type: 'number', min: 1, max: 100 })}
                                        </div>
                                    )}
                                    {toggle('referral_enabled', 'Referral links', 'Each customer gets a link to share. When an invited friend’s first order is delivered, both get points.')}
                                    {flag(form.data.referral_enabled) && (
                                        <div className="grid grid-cols-2 gap-3">
                                            {text('referral_bonus', 'Points for the inviter', { type: 'number', min: 0 })}
                                            {text('referral_welcome', 'Points for the new customer', { type: 'number', min: 0 })}
                                        </div>
                                    )}
                                </div>
                            </Panel>
                            <Panel title="Low Stock" icon="box">
                                <div className="space-y-4">
                                    {text('low_stock_threshold', 'Low stock at or below', { type: 'number', min: 0, hint: 'Products with this many units or fewer show in Inventory → Low stock.' })}
                                    {toggle('low_stock_alerts', 'Email a low-stock list every morning', 'Sent at 8:15 when anything is running low.')}
                                    {flag(form.data.low_stock_alerts) && text('low_stock_email', 'Send it to', { type: 'email', placeholder: form.data.notify_admin_email || 'you@example.com', hint: 'Leave blank to use the new-order alert email.' })}
                                </div>
                            </Panel>
                            <Panel title="Delivery Charges" icon="truck">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {text('delivery_charge_inside', 'Inside Dhaka (৳)', { type: 'number' })}
                                    {text('delivery_charge_outside', 'Outside Dhaka (৳)', { type: 'number' })}
                                    {text('express_delivery_charge', 'Express Delivery (৳)', { type: 'number' })}
                                    {text('free_delivery_over', 'Free delivery over (৳)', { type: 'number', hint: '0 to disable' })}
                                    {text('vat_percent', 'Default VAT on staff orders (%)', { type: 'number', step: '0.5', hint: 'Pre-filled on Create Order; 0 for none' })}
                                    <div>
                                        <label className="label" htmlFor="default-locale">Storefront language</label>
                                        <select id="default-locale" className="input" value={form.data.default_locale ?? 'en'} onChange={set('default_locale')}>
                                            <option value="en">English</option>
                                            <option value="bn">বাংলা (Bangla)</option>
                                        </select>
                                        <p className="mt-1 text-xs text-slate-500">Shoppers can switch in the header. Product names and specs stay as entered.</p>
                                    </div>
                                </div>
                            </Panel>
                            <Panel title="Social Links" icon="megaphone">
                                <div className="grid gap-4">
                                    {text('facebook', 'Facebook URL')}
                                    {text('youtube', 'YouTube URL')}
                                    {text('instagram', 'Instagram URL')}
                                </div>
                            </Panel>
                        </div>
                    </div>
                )}

                {tab === 'seo' && (
                    <div className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                        <div className="space-y-6">
                            <Panel title="Homepage & Default Meta" icon="search">
                                <div className="grid gap-4">
                                    <Field label={<span className="flex justify-between">Homepage title <CharCount value={form.data.seo_title} max={60} /></span>} error={e.seo_title} hint={`Blank uses "${form.data.site_name} - ${form.data.tagline}". Other pages use "Page name | ${form.data.site_name}".`}>
                                        <input className="input" value={form.data.seo_title ?? ''} onChange={set('seo_title')} placeholder={`${form.data.site_name} - ${form.data.tagline}`} aria-label="Homepage title" />
                                    </Field>
                                    <Field label={<span className="flex justify-between">Default meta description <CharCount value={form.data.seo_description} max={160} /></span>} error={e.seo_description} hint="Used on pages that don't have their own description.">
                                        <textarea className="input" rows={3} value={form.data.seo_description ?? ''} onChange={set('seo_description')} aria-label="Default meta description" />
                                    </Field>
                                    {text('seo_keywords', 'Default keywords', { hint: 'Comma separated. Search engines give these little weight; titles and descriptions matter more.' })}
                                </div>
                                <div className="mt-5 rounded-xl border border-slate-200 p-4">
                                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Google preview</p>
                                    <p className="truncate text-xs text-slate-600">{urls.sitemap.replace(/\/sitemap\.xml$/, '')}</p>
                                    <p className="truncate text-lg text-[#1a0dab]">{homeTitle}</p>
                                    <p className="line-clamp-2 text-sm text-slate-600">{form.data.seo_description}</p>
                                </div>
                            </Panel>

                            <Panel title="Social Share Image" icon="image">
                                <p className="-mt-2 mb-4 text-sm text-slate-500">Shown when a page without its own image is shared on Facebook, WhatsApp or X. Use 1200 × 630 px (JPG or PNG).</p>
                                {ogPreview && <img src={ogPreview} alt="" className="mb-4 aspect-[1.91/1] w-full max-w-md rounded-xl border border-slate-200 object-cover" />}
                                <Field label="Upload image" error={e.seo_og_image}>
                                    <input type="file" accept="image/png,image/jpeg,image/webp" className="input file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-700" onChange={(ev) => form.setData('seo_og_image', ev.target.files[0] ?? null)} />
                                </Field>
                                {ogImageUrl && !form.data.seo_og_image && (
                                    <label className="mt-3 inline-flex items-center gap-2 text-sm text-red-600"><input type="checkbox" checked={form.data.seo_og_image_remove} onChange={(ev) => form.setData('seo_og_image_remove', ev.target.checked)} /> Remove current image</label>
                                )}
                            </Panel>
                        </div>

                        <div className="space-y-6">
                            <Panel title="Search Engine Verification" icon="shield">
                                <div className="grid gap-4">
                                    {text('google_site_verification', 'Google Search Console', { hint: 'Only the content value of the meta tag', placeholder: 'e.g. 1a2B3c4D5e…' })}
                                    {text('bing_site_verification', 'Bing Webmaster Tools')}
                                    {text('facebook_domain_verification', 'Facebook domain verification')}
                                </div>
                            </Panel>
                            <Panel title="Sitemap & Robots" icon="file">
                                <div className="grid gap-4">
                                    <Copy label="Sitemap (submit this in Search Console)" value={urls.sitemap} />
                                    <Copy label="robots.txt" value={urls.robots} />
                                    <div className="rounded-xl border border-red-200 bg-red-50/50 p-4">
                                        {toggle('seo_noindex', 'Hide the whole site from search engines', 'For a staging site only. Adds noindex to every page and blocks crawling in robots.txt.')}
                                    </div>
                                </div>
                            </Panel>
                        </div>
                    </div>
                )}

                {tab === 'tracking' && (
                    <div className="grid items-start gap-6 lg:grid-cols-3">
                        <Panel title="Google Tag Manager" icon="tag" collapsible={false}>
                            {text('gtm_container_id', 'Container ID', { placeholder: 'GTM-XXXXXXX' })}
                            <p className="mt-3 text-xs text-slate-500">Pushes <code>page_view</code>, <code>view_item</code>, <code>add_to_cart</code>, <code>begin_checkout</code> and <code>purchase</code> to the dataLayer in GA4 e-commerce format.</p>
                        </Panel>
                        <Panel title="Google Analytics 4" icon="dashboard" collapsible={false}>
                            {text('ga_measurement_id', 'Measurement ID', { placeholder: 'G-XXXXXXXXXX' })}
                            <p className="mt-3 text-xs text-slate-500">Leave blank if GA4 is already set up inside Tag Manager, so page views aren't counted twice.</p>
                        </Panel>
                        <Panel title="Meta (Facebook) Pixel" icon="megaphone" collapsible={false}>
                            {text('facebook_pixel_id', 'Pixel ID', { placeholder: '123456789012345' })}
                            <p className="mt-3 text-xs text-slate-500">Sends PageView, ViewContent, AddToCart, InitiateCheckout and Purchase with value and product IDs.</p>
                        </Panel>
                        <p className="flex items-start gap-2 text-sm text-slate-500 lg:col-span-3"><Icon name="info" className="mt-0.5 h-4 w-4 shrink-0" /> Tracking runs on the storefront only, never in this admin panel. Changes apply on the next page load.</p>
                    </div>
                )}

                {tab === 'payments' && (
                    <div className="space-y-6">
                        <Panel title="EMI Prices" icon="card" collapsible={false}>
                            <div className="grid items-start gap-5 lg:grid-cols-2">
                                <div className="space-y-4">
                                    {toggle('emi_enabled', 'Show EMI prices on product pages', 'Shows "EMI from ৳X/month" with a table of plans. Customers pay by card and their bank converts it to EMI.')}
                                    {text('emi_min_amount', 'Only for products from (৳)', { type: 'number', min: 0 })}
                                </div>
                                <div className="space-y-4">
                                    {text('emi_plans', 'Plans (months:interest %)', { hint: 'Comma separated, e.g. 3:0, 6:0, 9:5, 12:7 — 0 means 0% EMI' })}
                                    <TextArea label="Note under the table" rows={2} value={form.data.emi_note ?? ''} onChange={set('emi_note')} />
                                </div>
                            </div>
                        </Panel>
                        <Panel title="Cash on Delivery" icon="truck" collapsible={false}>
                            {toggle('cod_enabled', 'Accept cash on delivery', 'If every option is off, cash on delivery is still offered so customers can always order.')}
                        </Panel>
                        <div className="grid items-start gap-6 xl:grid-cols-2">
                            {GATEWAYS.map((g) => {
                                const status = gateways[g.key];
                                const on = flag(form.data[`${g.key}_enabled`]);
                                return (
                                    <section key={g.key} className="panel">
                                        <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                                            <span className={`grid h-10 w-10 place-items-center rounded-xl text-sm font-black ${g.tone}`}>{g.name.slice(0, 2)}</span>
                                            <div className="mr-auto">
                                                <h2 className="text-lg font-semibold text-slate-900">{g.name}</h2>
                                                <p className="text-xs text-slate-500">{g.about}</p>
                                            </div>
                                            <span className={`badge shrink-0 rounded-md ring-1 ring-inset ring-current/20 ${status.live ? 'bg-green-50 text-green-700' : status.configured ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'}`}>
                                                {status.live ? (flag(values[`${g.key}_sandbox`]) ? 'Live · Sandbox' : 'Live') : status.configured ? 'Off' : 'Not set up'}
                                            </span>
                                        </div>
                                        <div className="space-y-5 px-6 py-5">
                                            <div className="space-y-4">
                                                {toggle(`${g.key}_enabled`, `Offer ${g.name} at checkout`)}
                                                {e[`${g.key}_enabled`] && <p className="-mt-2 text-xs text-red-600">{e[`${g.key}_enabled`]}</p>}
                                                {toggle(`${g.key}_sandbox`, 'Sandbox (test) mode', 'Use the gateway\'s test server. Turn off only with live credentials.')}
                                            </div>
                                            <div className={`grid gap-4 sm:grid-cols-2 ${on ? '' : 'opacity-90'}`}>
                                                {g.fields.map(([name, label, secret, long]) => {
                                                    const k = `${g.key}_${name}`;
                                                    const saved = secret && secretsSet[k];
                                                    const props = {
                                                        value: form.data[k] ?? '',
                                                        onChange: set(k),
                                                        placeholder: saved ? '•••••••• saved — leave blank to keep' : '',
                                                        autoComplete: 'off',
                                                    };
                                                    return long ? (
                                                        <TextArea key={k} label={label} className="sm:col-span-2" rows={3} error={e[k]} {...props} spellCheck={false} style={{ fontFamily: 'ui-monospace, monospace', fontSize: 12 }} />
                                                    ) : (
                                                        <TextInput key={k} label={label} type={secret ? 'password' : 'text'} error={e[k]} {...props} />
                                                    );
                                                })}
                                            </div>
                                            {g.key === 'sslcommerz' && <Copy label="IPN URL (add this in the SSLCommerz merchant panel)" value={urls.sslcommerzIpn} />}
                                        </div>
                                    </section>
                                );
                            })}
                        </div>
                        <p className="flex items-start gap-2 text-sm text-slate-500"><Icon name="shield" className="mt-0.5 h-4 w-4 shrink-0" /> Secret keys and passwords are stored encrypted and never shown again. Orders are only marked paid after the payment is confirmed directly with the gateway. Test each gateway in sandbox mode before going live.</p>
                    </div>
                )}

                {tab === 'notifications' && (
                    <div className="space-y-6">
                        <div className="grid items-start gap-6 xl:grid-cols-2">
                            <Panel title="SMS Gateway" icon="chat" actions={<span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${notifications.smsReady ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`}>{notifications.smsReady ? 'Ready' : 'Not set up'}</span>}>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <label className="label" htmlFor="sms-provider">Provider</label>
                                        <select id="sms-provider" className="input" value={form.data.sms_provider ?? ''} onChange={set('sms_provider')}>
                                            <option value="">No SMS</option>
                                            {Object.entries(notifications.smsProviders).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                                        </select>
                                    </div>
                                    {secret('sms_api_key', form.data.sms_provider === 'sslwireless' ? 'API token' : 'API key')}
                                    {text('sms_sender_id', form.data.sms_provider === 'sslwireless' ? 'SID' : 'Sender ID', { hint: form.data.sms_provider === 'alphasms' ? 'Optional (approved masking name)' : 'Approved sender ID / masking name' })}
                                    <div className="sm:col-span-2"><TestSend label="Send a test SMS to" url="/admin/settings/test-sms" field="phone" placeholder="01XXXXXXXXX" /></div>
                                </div>
                            </Panel>
                            <Panel title="Email (SMTP)" icon="mail" actions={<span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${notifications.mailReady ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`}>{notifications.mailReady ? 'Ready' : 'Not set up'}</span>}>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {text('mail_host', 'SMTP host', { placeholder: 'smtp.gmail.com' })}
                                    <div className="grid grid-cols-2 gap-3">
                                        {text('mail_port', 'Port', { type: 'number' })}
                                        <div>
                                            <label className="label" htmlFor="mail-enc">Security</label>
                                            <select id="mail-enc" className="input" value={form.data.mail_encryption ?? 'tls'} onChange={set('mail_encryption')}>
                                                <option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option>
                                            </select>
                                        </div>
                                    </div>
                                    {text('mail_username', 'Username', { autoComplete: 'off' })}
                                    {secret('mail_password', 'Password')}
                                    {text('mail_from_address', 'From email', { type: 'email', placeholder: 'orders@yourshop.com' })}
                                    {text('mail_from_name', 'From name', { placeholder: form.data.site_name })}
                                    {text('notify_admin_email', 'Send new-order alerts to', { type: 'email', hint: 'Shop email that gets an alert for every new order' })}
                                    <TestSend label="Send a test email to" url="/admin/settings/test-email" field="email" type="email" placeholder="you@example.com" />
                                </div>
                            </Panel>
                        </div>

                        <Panel title="Checkout Verification" icon="shield">
                            <div className="grid items-start gap-5 lg:grid-cols-2">
                                <div>
                                    {toggle('checkout_otp', 'Verify mobile number with an SMS code', 'Customers must enter a 6-digit code before placing an order. Stops most fake cash-on-delivery orders. Needs a working SMS gateway.')}
                                    {flag(form.data.checkout_otp) && !notifications.smsReady && <p className="mt-2 text-xs text-amber-700">SMS isn't set up yet, so checkout won't ask for a code until it is.</p>}
                                </div>
                                <TextArea label="Code message" rows={2} value={form.data.sms_template_otp ?? ''} onChange={set('sms_template_otp')} error={e.sms_template_otp} hint="Must include {code}." />
                            </div>
                        </Panel>

                        <Panel title="Product Alerts" icon="bell">
                            <div className="grid items-start gap-5 lg:grid-cols-2">
                                <div className="space-y-4">
                                    {toggle('back_in_stock_enabled', '“Notify me” on out-of-stock products', 'Shoppers leave a phone or email and get one message when it is back in stock.')}
                                    <TextArea label="Back-in-stock SMS" rows={2} value={form.data.sms_template_back_in_stock ?? ''} onChange={set('sms_template_back_in_stock')} error={e.sms_template_back_in_stock} hint="Placeholders: {product}, {link}, {site}" />
                                </div>
                                <div className="space-y-4">
                                    {toggle('price_drop_alerts', 'Price-drop alerts for wishlists', 'When a product’s price drops by 2% or more, people who wishlisted it get a message (at most once a week per product).')}
                                    <TextArea label="Price-drop SMS" rows={2} value={form.data.sms_template_price_drop ?? ''} onChange={set('sms_template_price_drop')} error={e.sms_template_price_drop} hint="Placeholders: {name}, {product}, {price}, {old}, {link}, {site}" />
                                </div>
                            </div>
                        </Panel>

                        <Panel title="Returns & Warranty" icon="refresh">
                            <div className="grid items-start gap-5 lg:grid-cols-2">
                                <div className="space-y-4">
                                    {toggle('returns_enabled', 'Customers can request returns and warranty claims', 'From a delivered order in their account. Staff can always open one from the order page.')}
                                    {text('return_days', 'Return window (days after delivery)', { type: 'number', min: 0, max: 365, hint: 'Warranty claims are allowed until each product’s warranty ends.' })}
                                </div>
                                {toggle('notify_returns', 'Send SMS / email at each step', 'Approved, rejected, refunded and completed, with your note to the customer.')}
                            </div>
                        </Panel>

                        <Panel title="Abandoned Cart Reminders" icon="cart">
                            <div className="grid items-start gap-5 lg:grid-cols-2">
                                <div className="space-y-4">
                                    {toggle('abandoned_cart_enabled', 'Remind shoppers who leave checkout', 'Saves the cart once a shopper types a phone or email at checkout, then sends one SMS and/or email with a link that restores the cart.')}
                                    {text('abandoned_cart_delay_hours', 'Send the reminder after (hours)', { type: 'number', min: 1, max: 72 })}
                                </div>
                                <TextArea label="Reminder SMS" rows={3} value={form.data.sms_template_abandoned_cart ?? ''} onChange={set('sms_template_abandoned_cart')} error={e.sms_template_abandoned_cart} hint="Placeholders: {name}, {total}, {link}, {site}" />
                            </div>
                        </Panel>

                        <Panel title="Customer Messages" icon="megaphone" bodyClassName="pb-2">
                            <p className="-mt-2 px-6 pb-4 text-sm text-slate-500">
                                Choose which updates go out by SMS and email. Emails use a standard layout; SMS text is yours to edit. Placeholders: {PLACEHOLDERS.map((p) => <code key={p} className="mx-0.5 rounded bg-slate-100 px-1 text-xs">{p}</code>)}
                            </p>
                            <div className="divide-y divide-slate-100 border-t border-slate-100">
                                {Object.entries(notifications.events).map(([ev, label]) => {
                                    const tpl = form.data[`sms_template_${ev}`] ?? '';
                                    const len = smsParts(tpl);
                                    return (
                                        <div key={ev} className="grid gap-3 px-6 py-4 lg:grid-cols-[220px_1fr]">
                                            <div className="space-y-2">
                                                <p className="font-medium text-slate-900">{label}</p>
                                                <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="h-4 w-4 accent-primary-600" checked={flag(form.data[`notify_sms_${ev}`])} onChange={(x) => form.setData(`notify_sms_${ev}`, x.target.checked ? '1' : '0')} /> SMS</label>
                                                <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="h-4 w-4 accent-primary-600" checked={flag(form.data[`notify_email_${ev}`])} onChange={(x) => form.setData(`notify_email_${ev}`, x.target.checked ? '1' : '0')} /> Email</label>
                                            </div>
                                            <div>
                                                <textarea className="input" rows={2} value={tpl} onChange={set(`sms_template_${ev}`)} aria-label={`${label} SMS text`} />
                                                <p className={`mt-1 text-xs ${len.parts > 2 ? 'text-amber-700' : 'text-slate-500'}`}>{len.n} characters · {len.parts} SMS part{len.parts > 1 ? 's' : ''} before placeholders are filled{len.unicode ? ' · Bangla/Unicode: 70 per part' : ''}</p>
                                                {e[`sms_template_${ev}`] && <p className="mt-1 text-xs text-red-600">{e[`sms_template_${ev}`]}</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </Panel>
                    </div>
                )}

                {tab === 'fraud' && (
                    <div className="grid items-start gap-6 xl:grid-cols-2">
                        <Panel title="Courier History Check" icon="search">
                            <p className="-mt-2 mb-4 text-sm text-slate-500">Every order shows the customer's history in this shop (delivered vs. cancelled). Add a BD Courier API key to also see their record across Steadfast, Pathao, RedX and other couriers.</p>
                            <div className="grid gap-4">
                                <div>
                                    <label className="label" htmlFor="fraud-provider">Courier history source</label>
                                    <select id="fraud-provider" className="input" value={form.data.fraud_provider ?? ''} onChange={set('fraud_provider')}>
                                        <option value="">This shop's orders only</option>
                                        <option value="bdcourier">BD Courier (bdcourier.com)</option>
                                    </select>
                                </div>
                                {form.data.fraud_provider === 'bdcourier' && secret('bdcourier_api_key', 'BD Courier API key')}
                            </div>
                        </Panel>
                        <Panel title="Blocked Numbers" icon="shield">
                            <p className="-mt-2 mb-4 text-sm text-slate-500">Numbers you mark as fraud (from an order page or <Link href="/admin/blocked-phones" className="font-medium text-primary-700 hover:underline">Blocked Numbers</Link>) are handled like this at checkout:</p>
                            <div className="grid gap-2">
                                {[['cod', 'Block cash on delivery only', 'They can still order if they pay online.'], ['all', 'Block all orders', 'They are asked to call the shop instead.']].map(([v, l, d]) => (
                                    <label key={v} className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${form.data.fraud_block_mode === v ? 'border-primary-600 bg-primary-50' : 'border-slate-200'}`}>
                                        <input type="radio" name="fraud_block_mode" className="mt-1 accent-primary-600" checked={form.data.fraud_block_mode === v} onChange={() => form.setData('fraud_block_mode', v)} />
                                        <span><span className="block font-medium text-slate-900">{l}</span><span className="text-xs text-slate-500">{d}</span></span>
                                    </label>
                                ))}
                            </div>
                        </Panel>
                    </div>
                )}

                {tab === 'couriers' && (
                    <div className="space-y-6">
                        <Panel title="Delivery Updates" icon="truck" collapsible={false}>
                            {toggle('courier_sync_status', 'Update orders from the courier', 'When the courier marks a parcel delivered, the order becomes Delivered (cash-on-delivery orders are marked paid). Every courier update is added to the order history either way.')}
                        </Panel>
                        <div className="grid items-start gap-6 xl:grid-cols-2">
                            <section className="panel">
                                <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-sm font-black text-emerald-700">St</span>
                                    <div className="mr-auto">
                                        <h2 className="text-lg font-semibold text-slate-900">Steadfast</h2>
                                        <p className="text-xs text-slate-500">API keys are in the Steadfast merchant panel under API.</p>
                                    </div>
                                    {courierBadge('steadfast')}
                                </div>
                                <div className="space-y-5 px-6 py-5">
                                    {toggle('steadfast_enabled', 'Use Steadfast for deliveries')}
                                    {e.steadfast_enabled && <p className="-mt-3 text-xs text-red-600">{e.steadfast_enabled}</p>}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {secret('steadfast_api_key', 'API Key')}
                                        {secret('steadfast_secret_key', 'Secret Key')}
                                    </div>
                                    <TestConnection courier="steadfast" />
                                    <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                        <p className="text-sm font-medium text-slate-900">Status webhook</p>
                                        <p className="text-xs text-slate-500">In the Steadfast panel, set the callback URL below and the auth token as the Bearer token. Steadfast then pushes every status change here.</p>
                                        <Copy label="Callback URL" value={urls.steadfastWebhook} />
                                        <Copy label="Auth token (Bearer)" value={form.data.steadfast_webhook_token ?? ''} />
                                    </div>
                                </div>
                            </section>

                            <section className="panel">
                                <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-sm font-black text-red-600">Pa</span>
                                    <div className="mr-auto">
                                        <h2 className="text-lg font-semibold text-slate-900">Pathao</h2>
                                        <p className="text-xs text-slate-500">Client ID and secret are in the Pathao merchant panel under Developers API.</p>
                                    </div>
                                    {courierBadge('pathao')}
                                </div>
                                <div className="space-y-5 px-6 py-5">
                                    <div className="space-y-4">
                                        {toggle('pathao_enabled', 'Use Pathao for deliveries')}
                                        {e.pathao_enabled && <p className="-mt-2 text-xs text-red-600">{e.pathao_enabled}</p>}
                                        {toggle('pathao_sandbox', 'Sandbox (test) mode', "Use Pathao's test server. Turn off only with live credentials.")}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {text('pathao_client_id', 'Client ID', { autoComplete: 'off' })}
                                        {secret('pathao_client_secret', 'Client Secret')}
                                        {text('pathao_username', 'Merchant email', { type: 'email', autoComplete: 'off' })}
                                        {secret('pathao_password', 'Merchant password')}
                                    </div>
                                    <TestConnection courier="pathao" onStores={setPathaoStores} />
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <label className="label" htmlFor="pathao-store">Pickup store</label>
                                            {pathaoStores ? (
                                                <select id="pathao-store" className="input" value={form.data.pathao_store_id ?? ''} onChange={set('pathao_store_id')}>
                                                    <option value="">First store</option>
                                                    {pathaoStores.map((st) => <option key={st.id} value={st.id}>{st.name}</option>)}
                                                </select>
                                            ) : (
                                                <input id="pathao-store" className="input" value={form.data.pathao_store_id ?? ''} onChange={set('pathao_store_id')} placeholder="Test connection to list" />
                                            )}
                                            {e.pathao_store_id && <p className="mt-1 text-xs text-red-600">{e.pathao_store_id}</p>}
                                        </div>
                                        <div>
                                            <label className="label" htmlFor="pathao-type">Default delivery</label>
                                            <select id="pathao-type" className="input" value={String(form.data.pathao_delivery_type ?? '48')} onChange={set('pathao_delivery_type')}>
                                                <option value="48">Normal</option>
                                                <option value="12">On-demand</option>
                                            </select>
                                        </div>
                                        {text('pathao_item_weight', 'Default weight (kg)', { type: 'number', step: '0.1', min: '0.5', max: '10' })}
                                    </div>
                                    <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                        <p className="text-sm font-medium text-slate-900">Status webhook</p>
                                        <p className="text-xs text-slate-500">In the Pathao merchant panel, add a webhook with the URL and secret below, then subscribe to the order events.</p>
                                        <Copy label="Webhook URL" value={urls.pathaoWebhook} />
                                        <Copy label="Webhook secret" value={form.data.pathao_webhook_secret ?? ''} />
                                    </div>
                                </div>
                            </section>
                        </div>
                        <p className="flex items-start gap-2 text-sm text-slate-500"><Icon name="info" className="mt-0.5 h-4 w-4 shrink-0" /> Parcels are booked from each order's page (Orders → open an order → Courier). Test with sandbox or a single real parcel before shipping in bulk.</p>
                    </div>
                )}
                {tab === 'ai' && (
                    <div className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                        <div className="space-y-6">
                            <Panel title="AI Provider" icon="sparkle" actions={<span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${ai.ready ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`}>{ai.ready ? 'Ready' : 'No key yet'}</span>}>
                                <p className="-mt-2 mb-4 text-sm text-slate-500">All three have free plans. Get a key from the provider's site, paste it here and save. If the main provider is busy or out of free quota, the backup is used.</p>
                                <div className="mb-5 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="label" htmlFor="ai-provider">Main provider</label>
                                        <select id="ai-provider" className="input" value={form.data.ai_provider ?? 'gemini'} onChange={set('ai_provider')}>
                                            {Object.entries(ai.providers).map(([k, p]) => <option key={k} value={k}>{p.label}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="label" htmlFor="ai-fallback">Backup provider</label>
                                        <select id="ai-fallback" className="input" value={form.data.ai_fallback ?? ''} onChange={set('ai_fallback')}>
                                            <option value="">None</option>
                                            {Object.entries(ai.providers).map(([k, p]) => <option key={k} value={k}>{p.label}</option>)}
                                        </select>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    {Object.entries(ai.providers).map(([k, p]) => (
                                        <section key={k} className="rounded-xl border border-slate-200 p-4">
                                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-medium text-slate-900">{p.label}</p>
                                                <a href={p.key_url} target="_blank" rel="noreferrer" className="text-xs font-medium text-primary-700 hover:underline">Get a free key ↗</a>
                                            </div>
                                            <div className="grid items-start gap-4 sm:grid-cols-2">
                                                {secret(`ai_${k}_key`, 'API key')}
                                                {text(`ai_${k}_model`, 'Models', { hint: `${k === 'gemini' ? 'e.g. gemini-3.5-flash-lite, gemini-3.6-flash' : k === 'groq' ? 'e.g. openai/gpt-oss-120b' : 'e.g. a model ending in :free'}. Separate several with commas: the next is tried when one is busy.` })}
                                            </div>
                                            {secretsSet[`ai_${k}_key`] && <div className="mt-3"><TestAi provider={k} /></div>}
                                        </section>
                                    ))}
                                </div>
                            </Panel>
                        </div>
                        <div className="space-y-6">
                            <Panel title="Shopping Assistant" icon="chat">
                                <div className="space-y-4">
                                    {toggle('ai_chat_enabled', 'Show the chat assistant in the shop', 'A chat button on every store page. It answers in Bangla or English, suggests products using your real prices and stock, and checks order status.')}
                                    {flag(form.data.ai_chat_enabled) && !ai.ready && <p className="text-xs text-amber-700">Add an API key above, or the chat button stays hidden.</p>}
                                    {text('ai_assistant_name', 'Assistant name', { placeholder: `${form.data.site_name} Assistant` })}
                                    <TextArea label="Greeting" rows={3} value={form.data.ai_chat_greeting ?? ''} onChange={set('ai_chat_greeting')} error={e.ai_chat_greeting} />
                                </div>
                            </Panel>
                            <Panel title="AI Writer" icon="edit">
                                <div className="space-y-4">
                                    <p className="-mt-2 text-sm text-slate-500">Writes product descriptions, key features, SEO text and Bangla versions from each product's specs. Staff approve every text in <Link href="/admin/ai-writer" className="font-medium text-primary-700 hover:underline">Products → AI Writer</Link> before it goes live.</p>
                                    {toggle('ai_writer_auto', 'Write drafts automatically every hour', 'Best-selling and most-viewed products with thin descriptions go first.')}
                                    {text('ai_writer_per_hour', 'Products per hour', { type: 'number', min: 1, max: 200 })}
                                </div>
                            </Panel>
                            <Panel title="Usage Limit" icon="shield">
                                <div className="space-y-2">
                                    {text('ai_daily_limit', 'Most AI requests per day', { type: 'number', min: 0, hint: 'Protects your free quota or bill. 0 means no limit. One chat answer can use 1–4 requests.' })}
                                    <p className="text-xs text-slate-500">Used today: <b>{ai.usedToday}</b></p>
                                </div>
                            </Panel>
                            <p className="flex items-start gap-2 text-sm text-slate-500"><Icon name="info" className="mt-0.5 h-4 w-4 shrink-0" /> Product details and shoppers' chat messages are sent to the AI provider. Free plans may use them to improve their models, so the assistant never sends customer names or addresses.</p>
                        </div>
                    </div>
                )}
            </form>
        </AdminLayout>
    );
}
