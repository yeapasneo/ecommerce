import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, Dropdown, EmptyState, Pill, RowMenu, SearchBox, Tabs } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, money, statusColor } from '../../../lib/format';

export default function Index({ products, filters, categories, brands, stockStatuses, statusCounts }) {
    const [f, setF] = useState({ search: '', category_id: '', brand_id: '', stock_status: '', ...filters });
    const apply = (next = f) => router.get('/admin/products', cleanParams({ ...next, per_page: products.per_page === 20 ? undefined : products.per_page, page: undefined }), { preserveState: true, preserveScroll: true });
    const update = (patch) => {
        const next = { ...f, ...patch };
        setF(next);
        apply(next);
    };
    const total = Object.values(statusCounts).reduce((a, b) => a + Number(b), 0);
    const activeFilters = [f.category_id, f.brand_id].filter(Boolean).length;
    const destroy = (p) => confirm(`Delete "${p.name}"?`) && router.delete(`/admin/products/${p.id}`, { preserveScroll: true });

    return (
        <AdminLayout title="Products" header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <h1 className="mr-auto flex items-center gap-2 text-xl font-semibold text-slate-900">
                    All Products <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{total.toLocaleString()}</span>
                </h1>
                <SearchBox value={f.search} onChange={(v) => setF({ ...f, search: v })} onSubmit={() => apply()} placeholder="Search name or SKU…" />
                <Link href="/admin/products/import" className="btn-outline h-10 rounded-lg"><Icon name="file" className="h-4 w-4" /> Import / Export</Link>
                <Link href="/admin/products/create" className="btn-primary h-10 rounded-lg"><Icon name="plus" className="h-4 w-4" /> Add Product</Link>
            </div>

            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="mr-auto min-w-0 max-w-full">
                    <Tabs
                        value={f.stock_status}
                        onChange={(v) => update({ stock_status: v })}
                        tabs={[{ value: '', label: 'All products', count: total }, ...Object.entries(stockStatuses).map(([v, l]) => ({ value: v, label: l, count: statusCounts[v] ?? 0 }))]}
                    />
                </div>
                <Dropdown
                    width="w-72"
                    button={({ toggle }) => (
                        <button type="button" onClick={toggle} className="btn-outline h-10 rounded-lg font-medium">
                            <Icon name="filter" className="h-4 w-4" /> {activeFilters ? `Filters (${activeFilters})` : 'Show All'} <Icon name="chevronDown" className="h-4 w-4" />
                        </button>
                    )}
                >
                    <div className="grid gap-3 p-2">
                        <label className="text-sm">
                            <span className="label">Category</span>
                            <select className="input rounded-lg" value={f.category_id} onChange={(e) => update({ category_id: e.target.value })}>
                                <option value="">All categories</option>
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </label>
                        <label className="text-sm">
                            <span className="label">Brand</span>
                            <select className="input rounded-lg" value={f.brand_id} onChange={(e) => update({ brand_id: e.target.value })}>
                                <option value="">All brands</option>
                                {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                            </select>
                        </label>
                        {activeFilters > 0 && <button type="button" data-close onClick={() => update({ category_id: '', brand_id: '' })} className="text-left text-sm font-medium text-primary-700 hover:underline">Clear filters</button>}
                    </div>
                </Dropdown>
            </div>

            <div className="panel overflow-hidden">
                {products.data.length === 0 ? (
                    <EmptyState
                        icon={f.search || f.stock_status || activeFilters ? 'search' : 'plus'}
                        title={f.search || f.stock_status || activeFilters ? 'No products match' : 'No Products Added'}
                        text={f.search || f.stock_status || activeFilters ? 'Try a different search or clear the filters.' : 'Start building your catalog by adding your first product.'}
                        action={<Link href="/admin/products/create" className="btn-primary rounded-lg px-6 py-3 text-base"><Icon name="plus" className="h-4 w-4" /> Add Product</Link>}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[900px]">
                            <thead>
                                <tr><th className="pl-6">Product</th><th>Status</th><th>SKU</th><th>Price</th><th>Qty</th><th className="w-px" /></tr>
                            </thead>
                            <tbody>
                                {products.data.map((p) => (
                                    <tr key={p.id}>
                                        <td className="pl-6">
                                            <div className="flex items-center gap-4">
                                                <div className="grid h-12 w-16 shrink-0 place-items-center overflow-hidden rounded-md">
                                                    {p.image_url ? <img src={p.image_url} alt="" loading="lazy" className="max-h-12 max-w-16 object-contain" /> : <Icon name="image" className="h-6 w-6 text-slate-300" />}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <Link href={`/admin/products/${p.id}/edit`} className="line-clamp-1 font-medium text-slate-900 hover:text-primary-700">{p.name}</Link>
                                                        <a href={`/product/${p.slug}`} target="_blank" rel="noreferrer" className="shrink-0 text-slate-500 hover:text-primary-700" aria-label="Open in store"><Icon name="external" className="h-3.5 w-3.5" /></a>
                                                    </div>
                                                    <p className="truncate text-xs text-slate-500">
                                                        {[p.category?.name, p.brand?.name].filter(Boolean).join(' · ')}
                                                        {!p.is_active && <span className="ml-1.5 text-red-600">· Hidden</span>}
                                                        {p.is_featured && <span className="ml-1.5 text-primary-700">· Featured</span>}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${statusColor(p.stock_status)}`}>{p.stock_label}</span></td>
                                        <td className="font-mono text-xs text-slate-600">{p.sku}</td>
                                        <td className="whitespace-nowrap font-medium">{Number(p.price) > 0 ? money(p.price) : <span className="text-slate-400">—</span>}</td>
                                        <td><Pill>{p.stock}</Pill></td>
                                        <td className="pr-6">
                                            <div className="flex items-center justify-end gap-2">
                                                <RowMenu
                                                    items={[
                                                        { label: 'Edit', icon: 'edit', href: `/admin/products/${p.id}/edit` },
                                                        { label: 'View in store', icon: 'external', href: `/product/${p.slug}`, external: true },
                                                        { label: 'Delete', icon: 'trash', onClick: () => destroy(p), danger: true },
                                                    ]}
                                                />
                                                <Link href={`/admin/products/${p.id}/edit`} className="btn-outline h-9 rounded-lg px-3 font-medium"><Icon name="edit" className="h-4 w-4" /> Edit</Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <AdminPagination meta={products} params={cleanParams(f)} />
            </div>
        </AdminLayout>
    );
}
