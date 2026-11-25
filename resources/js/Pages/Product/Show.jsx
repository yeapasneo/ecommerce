import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import Breadcrumbs from '../../Components/Breadcrumbs';
import Countdown from '../../Components/Countdown';
import Icon from '../../Components/Icon';
import ProductCard from '../../Components/ProductCard';
import Seo from '../../Components/Seo';
import Stars from '../../Components/Stars';
import ShopLayout from '../../Layouts/ShopLayout';
import { date, money, statusColor } from '../../lib/format';
import { t } from '../../lib/i18n';

function Gallery({ product }) {
    const images = [product.image_url, ...product.images.map((i) => i.url)].filter(Boolean);
    const [active, setActive] = useState(0);
    return (
        <div>
            <div className="card flex aspect-square items-center justify-center overflow-hidden p-4">
                {images.length ? <img src={images[active]} alt={product.name} className="max-h-full rounded object-contain" /> : <Icon name="image" className="h-24 w-24 text-slate-300" />}
            </div>
            {images.length > 1 && (
                <div className="mt-3 flex gap-2 overflow-x-auto">
                    {images.map((src, i) => (
                        <button key={i} onClick={() => setActive(i)} className={`h-16 w-16 shrink-0 overflow-hidden rounded border-2 ${i === active ? 'border-accent-500' : 'border-transparent'}`}>
                            <img src={src} alt="" className="h-full w-full object-cover" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function ReviewForm({ product }) {
    const form = useForm({ rating: 5, comment: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(`/product/${product.id}/review`, { preserveScroll: true, onSuccess: () => form.reset() }); }} className="mt-4 space-y-3 rounded-md bg-slate-50 p-4">
            <div className="flex items-center gap-2 text-sm">{t('Your rating:')} <Stars value={form.data.rating} size="h-6 w-6" onChange={(v) => form.setData('rating', v)} /></div>
            <textarea className="input" rows={3} placeholder={t('Share your experience with this product')} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} />
            {form.errors.comment && <p className="text-xs text-red-600">{form.errors.comment}</p>}
            <button disabled={form.processing} className="btn-primary">{t('Submit Review')}</button>
        </form>
    );
}

function QuestionForm({ product }) {
    const form = useForm({ question: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(`/product/${product.id}/question`, { preserveScroll: true, onSuccess: () => form.reset() }); }} className="mt-4 flex flex-col gap-2 sm:flex-row">
            <input className="input" placeholder={t('Ask a question about this product')} value={form.data.question} onChange={(e) => form.setData('question', e.target.value)} />
            <button disabled={form.processing} className="btn-primary shrink-0">{t('Ask Question')}</button>
            {form.errors.question && <p className="text-xs text-red-600">{form.errors.question}</p>}
        </form>
    );
}

function EmiBox({ emi }) {
    const [open, setOpen] = useState(false);
    if (!emi?.plans?.length) return null;
    const best = emi.plans[emi.plans.length - 1];
    return (
        <div className="mt-3 text-sm">
            <button type="button" onClick={() => setOpen(!open)} className="flex items-center gap-1.5 font-medium text-brand-700 hover:underline" aria-expanded={open}>
                <Icon name="card" className="h-4 w-4" /> {t('EMI from')} {money(best.monthly)}/{t('month')} <Icon name={open ? 'chevronUp' : 'chevronDown'} className="h-3.5 w-3.5" />
            </button>
            {open && (
                <div className="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500"><tr><th className="px-3 py-2">{t('Tenure')}</th><th className="px-3 py-2">{t('Monthly')}</th><th className="px-3 py-2">{t('Total')}</th></tr></thead>
                        <tbody>
                            {emi.plans.map((p) => (
                                <tr key={p.months} className="border-t border-slate-100">
                                    <td className="px-3 py-2">{p.months} {t('months')}{p.rate === 0 && <span className="ml-1 rounded bg-green-50 px-1 text-[10px] font-semibold text-green-700">0%</span>}</td>
                                    <td className="px-3 py-2 font-semibold">{money(p.monthly)}</td>
                                    <td className="px-3 py-2 text-slate-500">{money(p.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {emi.note && <p className="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-500">{emi.note}</p>}
                </div>
            )}
        </div>
    );
}

export default function Show({ product, breadcrumbs, specGroups, reviews, rating, questions, related, inWishlist, inCompare, hasReviewed, variants = [], flash = null, emi = null }) {
    const { auth } = usePage().props;
    const [qty, setQty] = useState(1);
    const [tab, setTab] = useState('specification');

    // Variants: option names in order, the values each can take, and the chosen combination.
    const optionNames = useMemo(() => [...new Set(variants.flatMap((v) => Object.keys(v.options)))], [variants]);
    const optionValues = useMemo(() => Object.fromEntries(optionNames.map((n) => [n, [...new Set(variants.map((v) => v.options[n]).filter(Boolean))]])), [variants, optionNames]);
    const [picked, setPicked] = useState(() => (variants.find((v) => v.available) ?? variants[0])?.options ?? {});
    const variant = variants.length ? variants.find((v) => optionNames.every((n) => v.options[n] === picked[n])) : null;
    const isAvailable = (name, value) => variants.some((v) => v.available && v.options[name] === value && optionNames.every((n) => n === name || v.options[n] === picked[n]));
    const choose = (name, value) => {
        const next = { ...picked, [name]: value };
        // If that exact combination doesn't exist, jump to the closest one that has this value.
        const exact = variants.find((v) => optionNames.every((n) => v.options[n] === next[n]));
        setPicked(exact ? next : (variants.find((v) => v.options[name] === value && v.available) ?? variants.find((v) => v.options[name] === value))?.options ?? next);
    };

    const price = variant ? variant.price : product.price;
    const regular = variant ? variant.regular_price : product.regular_price;
    const saving = regular > price ? regular - price : 0;
    const canBuy = variants.length ? !!variant?.available : ['in_stock', 'pre_order'].includes(product.stock_status);
    const stockLabel = variants.length ? (variant?.available ? t('In Stock') : t('Out of Stock')) : product.stock_label;

    const add = (buyNow) => router.post('/cart', { product_id: product.id, variant_id: variant?.id ?? null, quantity: qty, buy_now: buyNow }, { preserveScroll: true });
    const requireLogin = (fn) => (auth.user ? fn() : router.visit('/login'));

    const tabs = [
        ['specification', 'Specification'],
        ['description', 'Description'],
        ['questions', `Questions (${questions.length})`],
        ['reviews', `Reviews (${rating.count})`],
    ];

    return (
        <div className="container-x py-4">
            <Seo title={product.meta_title || product.name} description={product.meta_description} />
            <Breadcrumbs items={[...breadcrumbs, { name: product.name }]} />

            <div className="card grid gap-6 p-4 md:grid-cols-2 md:p-6">
                <Gallery product={product} />
                <div>
                    <h1 className="text-xl font-semibold text-brand-700 md:text-2xl">{product.name}</h1>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs">
                        <span className="rounded-full bg-slate-100 px-3 py-1">{t('Price')}: <b className="text-slate-800">{money(price)}</b></span>
                        {regular > price && <span className="rounded-full bg-slate-100 px-3 py-1">{t('Regular Price')}: <span className="line-through">{money(regular)}</span></span>}
                        <span className={`rounded-full px-3 py-1 ${statusColor(canBuy || !variants.length ? product.stock_status : 'out_of_stock')}`}>{t('Status')}: {stockLabel}</span>
                        <span className="rounded-full bg-slate-100 px-3 py-1">{t('Product Code')}: {variant?.sku ?? product.sku}</span>
                        {product.brand && <Link href={`/brand/${product.brand.slug}`} className="rounded-full bg-slate-100 px-3 py-1 hover:text-accent-600">{t('Brand')}: {product.brand.name}</Link>}
                    </div>
                    {rating.count > 0 && (
                        <div className="mt-3 flex items-center gap-2 text-sm text-slate-600"><Stars value={rating.average} /> {rating.average} ({rating.count} reviews)</div>
                    )}

                    {product.key_features?.length > 0 && (
                        <div className="mt-5">
                            <h2 className="mb-2 font-semibold">{t('Key Features')}</h2>
                            <ul className="space-y-1 text-sm text-slate-600">
                                {product.key_features.map((f, i) => <li key={i}>{f}</li>)}
                            </ul>
                            <button onClick={() => { setTab('specification'); document.getElementById('details')?.scrollIntoView({ behavior: 'smooth' }); }} className="mt-2 text-sm font-medium text-accent-600 hover:underline">{t('View More Info')}</button>
                        </div>
                    )}

                    {optionNames.length > 0 && (
                        <div className="mt-5 space-y-3">
                            {optionNames.map((name) => (
                                <div key={name}>
                                    <p className="mb-1.5 text-sm font-semibold">{name}: <span className="font-normal text-slate-600">{picked[name]}</span></p>
                                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={name}>
                                        {optionValues[name].map((value) => {
                                            const on = picked[name] === value;
                                            const ok = isAvailable(name, value);
                                            return (
                                                <button key={value} type="button" role="radio" aria-checked={on} onClick={() => choose(name, value)}
                                                    className={`rounded-md border px-3 py-1.5 text-sm transition ${on ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 bg-white hover:border-brand-500'} ${ok ? '' : 'opacity-50 line-through decoration-slate-400'}`}>
                                                    {value}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {flash && (
                        <div className="mt-5 flex flex-wrap items-center gap-3 rounded-md bg-gradient-to-r from-red-600 to-accent-500 px-4 py-2.5 text-sm text-white">
                            <Icon name="gift" className="h-5 w-5" />
                            <span className="font-semibold">{flash.title}</span>
                            <span className="ml-auto flex items-center gap-2">{t('Ends in')} <Countdown to={flash.ends_at} /></span>
                        </div>
                    )}

                    <div className={`${flash ? 'mt-2' : 'mt-5'} rounded-md border-2 border-dashed border-brand-100 bg-brand-50 p-4`}>
                        <div className="text-2xl font-bold text-accent-600">{money(price)}</div>
                        {saving > 0 && <div className="text-sm text-green-700">{t('You save')} {money(saving)}</div>}
                        {product.warranty_months ? <div className="mt-1 text-xs text-slate-500">{product.warranty_months / 12} {t('year(s) official warranty')}</div> : null}
                        <EmiBox emi={emi} />
                    </div>

                    <div className="mt-5 flex flex-wrap items-center gap-3">
                        <div className="flex items-center rounded-md border border-slate-300">
                            <button onClick={() => setQty(Math.max(1, qty - 1))} className="px-3 py-2" aria-label={t('Decrease quantity')}><Icon name="minus" className="h-4 w-4" /></button>
                            <input value={qty} onChange={(e) => setQty(Math.max(1, Math.min(99, parseInt(e.target.value) || 1)))} className="w-12 border-x border-slate-300 py-2 text-center" aria-label={t('Quantity')} />
                            <button onClick={() => setQty(Math.min(99, qty + 1))} className="px-3 py-2" aria-label={t('Increase quantity')}><Icon name="plus" className="h-4 w-4" /></button>
                        </div>
                        <button disabled={!canBuy} onClick={() => add(true)} className="btn-primary px-8 py-2.5">{product.stock_status === 'pre_order' ? t('Pre Order') : canBuy ? t('Buy Now') : stockLabel}</button>
                        <button disabled={!canBuy} onClick={() => add(false)} className="btn-accent py-2.5"><Icon name="cart" className="h-4 w-4" /> {t('Add to Cart')}</button>
                    </div>
                    <div className="mt-4 flex gap-4 text-sm">
                        <button onClick={() => requireLogin(() => router.post('/account/wishlist', { product_id: product.id }, { preserveScroll: true }))} className={`flex items-center gap-1 ${inWishlist ? 'text-red-600' : 'text-slate-600 hover:text-accent-600'}`}>
                            <Icon name="heart" className="h-4 w-4" fill={inWishlist ? 'currentColor' : 'none'} /> {inWishlist ? t('In Wishlist') : t('Add to Wishlist')}
                        </button>
                        <button onClick={() => router.post('/compare', { product_id: product.id }, { preserveScroll: true })} className={`flex items-center gap-1 ${inCompare ? 'text-brand-600' : 'text-slate-600 hover:text-accent-600'}`}>
                            <Icon name="compare" className="h-4 w-4" /> {inCompare ? t('Added to Compare') : t('Add to Compare')}
                        </button>
                    </div>
                </div>
            </div>

            <div id="details" className="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
                <div>
                    <div className="mb-4 flex flex-wrap gap-2">
                        {tabs.map(([key, label]) => (
                            <button key={key} onClick={() => setTab(key)} className={`rounded-md px-4 py-2 text-sm font-semibold ${tab === key ? 'bg-accent-500 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'}`}>{label}</button>
                        ))}
                    </div>

                    {tab === 'specification' && (
                        <div className="card overflow-hidden">
                            <h2 className="px-5 py-4 text-lg font-semibold">{t('Specification')}</h2>
                            {specGroups.map((g) => (
                                <div key={g.group}>
                                    <div className="bg-brand-50 px-5 py-2 text-sm font-semibold text-brand-700">{g.group}</div>
                                    <table className="w-full text-sm">
                                        <tbody>
                                            {g.items.map((s) => (
                                                <tr key={s.id} className="border-b border-slate-100">
                                                    <td className="w-1/3 px-5 py-2.5 text-slate-500">{s.name}</td>
                                                    <td className="px-5 py-2.5">{s.value}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ))}
                        </div>
                    )}

                    {tab === 'description' && (
                        <div className="card p-5">
                            <h2 className="mb-3 text-lg font-semibold">{t('Description')}</h2>
                            <div className="prose-content text-sm text-slate-700" dangerouslySetInnerHTML={{ __html: product.description || `<p>${t('No description available.')}</p>` }} />
                        </div>
                    )}

                    {tab === 'questions' && (
                        <div className="card p-5">
                            <h2 className="text-lg font-semibold">Questions ({questions.length})</h2>
                            <p className="text-sm text-slate-500">{t('Have question about this product? Get specific details about this product from expert.')}</p>
                            {auth.user ? <QuestionForm product={product} /> : <p className="mt-3 text-sm"><Link href="/login" className="text-accent-600 hover:underline">{t('Log in')}</Link> {t('to ask a question.')}</p>}
                            <div className="mt-5 space-y-4">
                                {questions.map((q) => (
                                    <div key={q.id} className="border-b border-slate-100 pb-3 text-sm">
                                        <p><b>{t('Q:')}</b> {q.question}</p>
                                        <p className="text-xs text-slate-400">By {q.user?.name} on {date(q.created_at)}</p>
                                        {q.answer ? <p className="mt-1 text-slate-700"><b className="text-accent-600">{t('A:')}</b> {q.answer}</p> : <p className="mt-1 text-xs italic text-slate-400">{t('Awaiting answer')}</p>}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {tab === 'reviews' && (
                        <div className="card p-5">
                            <h2 className="text-lg font-semibold">Reviews ({rating.count})</h2>
                            {rating.count > 0 && <div className="mt-1 flex items-center gap-2"><Stars value={rating.average} /> <span className="text-sm">{rating.average} out of 5</span></div>}
                            {auth.user ? (hasReviewed ? <p className="mt-3 text-sm text-slate-500">{t('You have already reviewed this product. Submitting again will update your review.')}</p> : null) : <p className="mt-3 text-sm"><Link href="/login" className="text-accent-600 hover:underline">{t('Log in')}</Link> {t('to write a review.')}</p>}
                            {auth.user && <ReviewForm product={product} />}
                            <div className="mt-5 space-y-4">
                                {reviews.map((r) => (
                                    <div key={r.id} className="border-b border-slate-100 pb-3 text-sm">
                                        <div className="flex items-center gap-2"><Stars value={r.rating} /> <b>{r.user?.name}</b> <span className="text-xs text-slate-400">{date(r.created_at)}</span></div>
                                        <p className="mt-1 text-slate-700">{r.comment}</p>
                                    </div>
                                ))}
                                {reviews.length === 0 && <p className="text-sm text-slate-500">{t('No reviews yet. Be the first to review this product.')}</p>}
                            </div>
                        </div>
                    )}
                </div>

                <aside>
                    <h2 className="mb-3 font-semibold">{t('Related Products')}</h2>
                    <div className="space-y-3">
                        {related.map((p) => (
                            <Link key={p.id} href={`/product/${p.slug}`} className="card flex gap-3 p-3 hover:shadow-md">
                                <img src={p.image_url} alt="" className="h-16 w-16 rounded object-cover" />
                                <div className="min-w-0">
                                    <p className="line-clamp-2 text-sm">{p.name}</p>
                                    <p className="text-sm font-semibold text-accent-600">{money(p.price)}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </aside>
            </div>
        </div>
    );
}

Show.layout = (page) => <ShopLayout>{page}</ShopLayout>;
