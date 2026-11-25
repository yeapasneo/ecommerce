import { Link, router } from '@inertiajs/react';
import Icon from './Icon';
import { money } from '../lib/format';
import { t } from '../lib/i18n';

export default function ProductCard({ product, action }) {
    const canBuy = ['in_stock', 'pre_order'].includes(product.stock_status);
    const percent = product.regular_price ? Math.round((product.discount / product.regular_price) * 100) : 0;

    // Products with options are bought from their page, where the option is chosen.
    const addToCart = () => (product.has_variants ? router.visit(`/product/${product.slug}`) : router.post('/cart', { product_id: product.id }, { preserveScroll: true }));
    const addToCompare = () => router.post('/compare', { product_id: product.id }, { preserveScroll: true });

    return (
        <div className="card group relative flex flex-col overflow-hidden transition hover:shadow-md">
            {product.discount > 0 && (
                <span className="absolute left-0 top-3 z-10 rounded-r-full bg-accent-500 px-2.5 py-1 text-xs font-bold text-white">
                    {t('Save')}: {money(product.discount)}
                </span>
            )}
            {product.flash_ends_at && (
                <span className="absolute right-2 top-3 z-10 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">{t('Flash sale')}</span>
            )}
            <Link href={`/product/${product.slug}`} prefetch={['hover', 'click']} cacheFor="1m" className="block aspect-square overflow-hidden border-b border-slate-100 bg-slate-50 p-3">
                {product.image_url ? (
                    <img src={product.image_url} alt={product.name} loading="lazy" decoding="async" width={500} height={500} className="h-full w-full rounded object-contain transition group-hover:scale-105" />
                ) : (
                    <div className="flex h-full items-center justify-center text-slate-300"><Icon name="image" className="h-16 w-16" /></div>
                )}
            </Link>
            <div className="flex flex-1 flex-col p-3">
                <Link href={`/product/${product.slug}`} prefetch={['hover', 'click']} cacheFor="1m" className="mb-2 line-clamp-2 text-sm font-medium text-slate-800 hover:text-accent-600 hover:underline">
                    {product.name}
                </Link>
                {product.key_features?.length > 0 && (
                    <ul className="mb-3 hidden space-y-0.5 text-xs text-slate-500 sm:block">
                        {product.key_features.slice(0, 4).map((f, i) => (
                            <li key={i} className="line-clamp-1">• {f}</li>
                        ))}
                    </ul>
                )}
                <div className="mt-auto border-t border-slate-100 pt-2 text-center">
                    {canBuy || product.stock_status === 'up_coming' ? (
                        <div>
                            {product.has_variants && <span className="mr-1 text-xs text-slate-500">{t('From')}</span>}
                            <span className="text-base font-bold text-accent-600">{money(product.price)}</span>
                            {product.regular_price > product.price && (
                                <span className="ml-2 text-xs text-slate-400 line-through">{money(product.regular_price)}</span>
                            )}
                            {percent > 0 && <span className="ml-1 text-xs text-green-600">-{percent}%</span>}
                        </div>
                    ) : (
                        <span className="text-sm font-semibold text-red-600">{t(product.stock_label)}</span>
                    )}
                </div>
                {action ?? (
                    <div className="mt-2 flex gap-2">
                        <button onClick={addToCart} disabled={!canBuy} className="btn-primary btn-sm flex-1 py-1.5">
                            <Icon name="cart" className="h-4 w-4" /> {product.has_variants && canBuy ? t('Choose options') : product.stock_status === 'pre_order' ? t('Pre Order') : canBuy ? t('Buy Now') : t(product.stock_label)}
                        </button>
                        <button onClick={addToCompare} title={t('Add to Compare')} className="btn-outline btn-sm px-2">
                            <Icon name="compare" className="h-4 w-4" />
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
