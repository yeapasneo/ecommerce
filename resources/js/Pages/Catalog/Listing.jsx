import { Link } from '@inertiajs/react';
import { useState } from 'react';
import Breadcrumbs from '../../Components/Breadcrumbs';
import Filters, { applyFilters } from '../../Components/Filters';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';
import ProductCard from '../../Components/ProductCard';
import Seo from '../../Components/Seo';
import ShopLayout from '../../Layouts/ShopLayout';

export default function Listing({ title, description, breadcrumbs, subcategories, products, filters, query }) {
    const [showFilters, setShowFilters] = useState(false);
    const hasActive = query.status.length || query.brand.length || query.min_price || query.max_price || Object.keys(query.spec ?? {}).length;

    return (
        <div className="container-x py-4">
            <Seo title={title} description={description} />
            <Breadcrumbs items={breadcrumbs} />

            {subcategories?.length > 0 && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {subcategories.map((c) => (
                        <Link key={c.id} href={`/category/${c.slug}`} className="rounded-full border border-slate-300 bg-white px-4 py-1.5 text-sm hover:border-accent-500 hover:text-accent-600">{c.name}</Link>
                    ))}
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[260px_1fr]">
                <aside className="hidden lg:block">
                    <Filters filters={filters} query={query} />
                </aside>

                {showFilters && (
                    <div className="fixed inset-0 z-50 lg:hidden">
                        <div className="absolute inset-0 bg-black/50" onClick={() => setShowFilters(false)} />
                        <div className="absolute inset-y-0 left-0 w-80 max-w-[85%] overflow-y-auto bg-slate-100 p-3">
                            <div className="mb-3 flex items-center justify-between">
                                <span className="font-semibold">Filters</span>
                                <button onClick={() => setShowFilters(false)} aria-label="Close filters"><Icon name="x" /></button>
                            </div>
                            <Filters filters={filters} query={query} onDone={() => setShowFilters(false)} />
                        </div>
                    </div>
                )}

                <div>
                    <div className="card mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                        <h1 className="mr-auto text-lg font-semibold">{title}</h1>
                        <button onClick={() => setShowFilters(true)} className="btn-outline btn-sm lg:hidden"><Icon name="filter" className="h-4 w-4" /> Filter</button>
                        {hasActive ? (
                            <button onClick={() => applyFilters(query, { status: [], brand: [], spec: {}, min_price: '', max_price: '' })} className="text-xs text-red-600 hover:underline">Clear filters</button>
                        ) : null}
                        <label className="flex items-center gap-2 text-sm text-slate-500">
                            Show:
                            <select className="input w-auto py-1" value={query.limit} onChange={(e) => applyFilters(query, { limit: e.target.value })}>
                                {[20, 40, 60, 80].map((n) => <option key={n}>{n}</option>)}
                            </select>
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-500">
                            Sort By:
                            <select className="input w-auto py-1" value={query.sort} onChange={(e) => applyFilters(query, { sort: e.target.value })}>
                                {Object.entries(filters.sorts).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </label>
                    </div>

                    {products.data.length === 0 ? (
                        <div className="card p-12 text-center text-slate-500">
                            <Icon name="search" className="mx-auto mb-3 h-10 w-10 text-slate-300" />
                            No products found. Try changing the filters.
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                            {products.data.map((p) => <ProductCard key={p.id} product={p} />)}
                        </div>
                    )}
                    <Pagination meta={products} />
                    {description && <div className="card mt-6 p-5 text-sm text-slate-600">{description}</div>}
                </div>
            </div>
        </div>
    );
}

Listing.layout = (page) => <ShopLayout>{page}</ShopLayout>;
