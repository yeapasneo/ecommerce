import { Link, router } from '@inertiajs/react';
import Breadcrumbs from '../Components/Breadcrumbs';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/format';
import { t } from '../lib/i18n';

export default function Compare({ products, rows }) {
    return (
        <div className="container-x py-4">
            <Seo title={t('Product Comparison')} />
            <Breadcrumbs items={[{ name: 'Product Comparison' }]} />
            {products.length === 0 ? (
                <div className="card p-12 text-center">
                    <Icon name="compare" className="mx-auto mb-3 h-12 w-12 text-slate-300" />
                    <p className="text-slate-500">{t('You have not chosen any products to compare.')}</p>
                    <Link href="/" className="btn-primary mt-4">{t('Continue Shopping')}</Link>
                </div>
            ) : (
                <div className="card overflow-x-auto">
                    <table className="w-full min-w-[640px] text-sm">
                        <tbody>
                            <tr className="border-b border-slate-200">
                                <td className="w-48 bg-slate-50 p-4 font-semibold">{t('Product')}</td>
                                {products.map((p) => (
                                    <td key={p.id} className="p-4 align-top">
                                        <div className="relative">
                                            <button onClick={() => router.delete(`/compare/${p.id}`, { preserveScroll: true })} className="absolute right-0 top-0 text-slate-400 hover:text-red-600" aria-label={t('Remove')}><Icon name="x" className="h-4 w-4" /></button>
                                            <img src={p.image_url} alt="" className="mx-auto mb-2 h-32 w-32 rounded object-cover" />
                                            <Link href={`/product/${p.slug}`} className="font-medium hover:text-accent-600">{p.name}</Link>
                                        </div>
                                    </td>
                                ))}
                            </tr>
                            {[
                                ['Price', (p) => <span className="font-bold text-accent-600">{money(p.price)}</span>],
                                ['Model', (p) => p.model],
                                ['Brand', (p) => p.brand],
                                ['Availability', (p) => p.stock_label],
                            ].map(([label, fn]) => (
                                <tr key={label} className="border-b border-slate-100">
                                    <td className="bg-slate-50 p-3 font-medium">{label}</td>
                                    {products.map((p) => <td key={p.id} className="p-3">{fn(p)}</td>)}
                                </tr>
                            ))}
                            {rows.map((r, i) => (
                                <tr key={i} className="border-b border-slate-100">
                                    <td className="bg-slate-50 p-3 font-medium">{r.name}</td>
                                    {r.values.map((v, j) => <td key={j} className="p-3">{v ?? <span className="text-slate-300">—</span>}</td>)}
                                </tr>
                            ))}
                            <tr>
                                <td className="bg-slate-50 p-3" />
                                {products.map((p) => (
                                    <td key={p.id} className="p-3">
                                        <button disabled={!['in_stock', 'pre_order'].includes(p.stock_status)} onClick={() => router.post('/cart', { product_id: p.id })} className="btn-primary w-full">{t('Add to Cart')}</button>
                                    </td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

Compare.layout = (page) => <ShopLayout>{page}</ShopLayout>;
