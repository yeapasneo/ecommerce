import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Countdown from '../Components/Countdown';
import Icon from '../Components/Icon';
import { t } from '../lib/i18n';
import ProductCard from '../Components/ProductCard';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';

function Slider({ slides }) {
    const [i, setI] = useState(0);
    useEffect(() => {
        if (slides.length < 2) return;
        const t = setInterval(() => setI((n) => (n + 1) % slides.length), 5000);
        return () => clearInterval(t);
    }, [slides.length]);
    if (!slides.length) return null;
    return (
        <div className="relative overflow-hidden rounded-lg">
            <div className="flex transition-transform duration-500" style={{ transform: `translateX(-${i * 100}%)` }}>
                {slides.map((s) => (
                    <Link key={s.id} href={s.link || '#'} className="block w-full shrink-0" style={{ background: s.bg_color }}>
                        {s.image_url ? (
                            <img src={s.image_url} alt={s.title} className="aspect-[8/3] w-full object-cover" />
                        ) : (
                            <div className="flex aspect-[8/3] flex-col justify-center p-8 text-white">
                                <h2 className="text-2xl font-bold md:text-4xl">{s.title}</h2>
                                <p className="mt-2 opacity-80">{s.subtitle}</p>
                            </div>
                        )}
                    </Link>
                ))}
            </div>
            {slides.length > 1 && (
                <>
                    <button onClick={() => setI((i - 1 + slides.length) % slides.length)} className="absolute left-2 top-1/2 hidden -translate-y-1/2 rounded-full bg-black/30 sm:block p-1.5 text-white hover:bg-black/50" aria-label={t('Previous slide')}><Icon name="chevronLeft" /></button>
                    <button onClick={() => setI((i + 1) % slides.length)} className="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-full bg-black/30 sm:block p-1.5 text-white hover:bg-black/50" aria-label={t('Next slide')}><Icon name="chevronRight" /></button>
                    <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                        {slides.map((s, n) => (
                            <button key={s.id} onClick={() => setI(n)} className={`h-2 rounded-full transition-all ${n === i ? 'w-6 bg-accent-500' : 'w-2 bg-white/70'}`} aria-label={`Slide ${n + 1}`} />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function SectionTitle({ title, subtitle }) {
    return (
        <div className="mb-6 text-center">
            <h2 className="text-2xl font-bold text-slate-800">{title}</h2>
            {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
        </div>
    );
}

export default function Home({ sliders, sideBanners, featuredCategories, featuredProducts, newArrivals, brands, flashSale = null }) {
    return (
        <>
            <Seo />
            <section className="container-x mt-4 grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2"><Slider slides={sliders} /></div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 lg:grid-rows-2">
                    {sideBanners.map((b) => (
                        <Link key={b.id} href={b.link || '#'} className="relative block overflow-hidden rounded-lg" style={{ background: b.bg_color }}>
                            {b.image_url ? <img src={b.image_url} alt={b.title} className="aspect-[2/1] w-full object-cover object-left lg:absolute lg:inset-0 lg:aspect-auto lg:h-full" /> : <div className="aspect-[2/1] h-full p-6 text-white">{b.title}</div>}
                        </Link>
                    ))}
                </div>
            </section>

            {flashSale && (
                <section className="container-x mt-8">
                    <div className="overflow-hidden rounded-lg bg-gradient-to-r from-red-600 to-accent-500 p-4 text-white sm:p-5">
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <Icon name="gift" className="h-6 w-6" />
                            <h2 className="text-xl font-bold">{flashSale.title}</h2>
                            <span className="ml-auto flex items-center gap-2 text-sm">{t('Ends in')} <Countdown to={flashSale.ends_at} /></span>
                        </div>
                        <div className="grid grid-cols-2 gap-3 text-slate-800 md:grid-cols-4 lg:grid-cols-6">
                            {flashSale.products.map((p) => <ProductCard key={p.id} product={p} />)}
                        </div>
                    </div>
                </section>
            )}

            <section className="container-x mt-12">
                <SectionTitle title={t('Featured Category')} subtitle={t('Get your desired product from featured category!')} />
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                    {featuredCategories.map((c) => (
                        <Link key={c.id} href={`/category/${c.slug}`} className="card flex flex-col items-center gap-2 p-4 text-center transition hover:-translate-y-0.5 hover:shadow-md">
                            <span className="grid h-12 w-12 place-items-center rounded-full bg-brand-50 text-brand-600"><Icon name={c.icon} className="h-6 w-6" /></span>
                            <span className="text-xs font-medium">{c.name}</span>
                        </Link>
                    ))}
                </div>
            </section>

            <section className="container-x mt-12">
                <SectionTitle title={t('Featured Products')} subtitle={t('Check & get your desired product!')} />
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                    {featuredProducts.map((p) => <ProductCard key={p.id} product={p} />)}
                </div>
            </section>

            <section className="container-x mt-12">
                <SectionTitle title={t('New Arrivals')} subtitle={t('Fresh in stock this week')} />
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                    {newArrivals.map((p) => <ProductCard key={p.id} product={p} />)}
                </div>
            </section>

            <section className="container-x mt-12">
                <div className="card grid gap-6 p-6 sm:grid-cols-3">
                    {[
                        ['truck', 'Fast Delivery', 'Inside Dhaka within 24-48 hours, nationwide in 3-5 days.'],
                        ['shield', 'Official Warranty', 'Every product comes with genuine brand warranty.'],
                        ['refresh', 'Easy Returns', '7-day return policy on unused products.'],
                    ].map(([icon, title, text]) => (
                        <div key={title} className="flex gap-4">
                            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-accent-500/10 text-accent-600"><Icon name={icon} className="h-6 w-6" /></span>
                            <div>
                                <h3 className="font-semibold">{title}</h3>
                                <p className="text-sm text-slate-500">{text}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {brands.length > 0 && (
                <section className="container-x mt-12">
                    <SectionTitle title={t('Top Brands')} />
                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                        {brands.map((b) => (
                            <Link key={b.id} href={`/brand/${b.slug}`} className="card flex h-16 items-center justify-center p-3 text-sm font-bold text-slate-600 hover:text-accent-600">
                                {b.logo_url ? <img src={b.logo_url} alt={b.name} className="max-h-10" /> : b.name}
                            </Link>
                        ))}
                    </div>
                    <div className="mt-4 text-center"><Link href="/brands" className="text-sm font-semibold text-brand-600 hover:underline">{t('View all brands')}</Link></div>
                </section>
            )}
        </>
    );
}

Home.layout = (page) => <ShopLayout>{page}</ShopLayout>;
