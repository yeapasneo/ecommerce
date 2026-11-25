import { Link, router } from '@inertiajs/react';
import Filters, { applyFilters } from '../../Components/Filters';
import Pagination from '../../Components/Pagination';
import ProductCard from '../../Components/ProductCard';
import Seo from '../../Components/Seo';
import ShopLayout from '../../Layouts/ShopLayout';
import { t } from '../../lib/i18n';

export default function Choose({ component, products, filters, query }) {
    return (
        <div className="container-x py-4">
            <Seo title={`Choose ${component.name}`} />
            <div className="mb-4 flex items-center gap-3 text-sm">
                <Link href="/pc-builder" className="text-accent-600 hover:underline">{t('PC Builder')}</Link>
                <span>/</span>
                <span>Choose {component.name}</span>
            </div>
            <div className="grid gap-4 lg:grid-cols-[260px_1fr]">
                <aside className="hidden lg:block"><Filters filters={filters} query={query} /></aside>
                <div>
                    <div className="card mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                        <h1 className="mr-auto text-lg font-semibold">Choose {component.name}</h1>
                        <select className="input w-auto py-1" value={query.sort} onChange={(e) => applyFilters(query, { sort: e.target.value })} aria-label={t('Sort')}>
                            {Object.entries(filters.sorts).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                    {products.data.length === 0 && <div className="card p-10 text-center text-slate-500">{t('No compatible products available right now.')}</div>}
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        {products.data.map((p) => (
                            <ProductCard
                                key={p.id}
                                product={p}
                                action={<button onClick={() => router.post(`/pc-builder/select/${component.slug}/${p.id}`)} className="btn-primary btn-sm mt-2 w-full py-1.5">{t('Add to PC')}</button>}
                            />
                        ))}
                    </div>
                    <Pagination meta={products} />
                </div>
            </div>
        </div>
    );
}

Choose.layout = (page) => <ShopLayout>{page}</ShopLayout>;
