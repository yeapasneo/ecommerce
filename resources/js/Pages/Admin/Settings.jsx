import { useForm } from '@inertiajs/react';
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
];

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

export default function Settings({ values, secretsSet, gateways, urls, ogImageUrl }) {
    const initial = useMemo(() => ({ ...values, seo_og_image: null, seo_og_image_remove: false }), [values]);
    const form = useForm(initial);
    const [tab, setTab] = useState(() => (typeof window !== 'undefined' && TABS.some((t) => `#${t.value}` === window.location.hash) ? window.location.hash.slice(1) : 'general'));
    useEffect(() => { window.history.replaceState(null, '', `#${tab}`); }, [tab]);

    const e = form.errors;
    const set = (k) => (ev) => form.setData(k, ev.target.value);
    const text = (k, label, props = {}) => <TextInput label={label} value={form.data[k] ?? ''} onChange={set(k)} error={e[k]} {...props} />;
    const toggle = (k, label, description) => <Toggle label={label} description={description} checked={flag(form.data[k])} onChange={(v) => form.setData(k, v ? '1' : '0')} />;

    // Jump to the tab holding the first error.
    useEffect(() => {
        const keys = Object.keys(e);
        if (!keys.length) return;
        const k = keys[0];
        const where = /^(bkash|nagad|sslcommerz|eps|cod)_/.test(k) ? 'payments' : /^(seo_|google_|bing_|facebook_domain)/.test(k) ? 'seo' : /(pixel|gtm|ga_)/.test(k) ? 'tracking' : 'general';
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
            subtitle="Store details, SEO, tracking and payment gateways"
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
                            <Panel title="Delivery Charges" icon="truck">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {text('delivery_charge_inside', 'Inside Dhaka (৳)', { type: 'number' })}
                                    {text('delivery_charge_outside', 'Outside Dhaka (৳)', { type: 'number' })}
                                    {text('express_delivery_charge', 'Express Delivery (৳)', { type: 'number' })}
                                    {text('free_delivery_over', 'Free delivery over (৳)', { type: 'number', hint: '0 to disable' })}
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
            </form>
        </AdminLayout>
    );
}
