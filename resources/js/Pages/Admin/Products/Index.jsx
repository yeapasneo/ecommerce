import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../../Components/Icon';
import Pagination from '../../../Components/Pagination';
import AdminLayout from '../../../Layouts/AdminLayout';
import { cleanParams, money, statusColor } from '../../../lib/format';

export default function Index({ products, filters, categories, brands, stockStatuses }) {
    const [f, setF] = useState({ search: '', category_id: '', brand_id: '', stock_status: '', ...filters });
    const apply = (next = f) => router.get('/admin/products', cleanParams(next), { preserveState: true, preserveScroll: true });
    const set = (k) => (e) => {
        const next = { ...f, [k]: e.target.value };
        setF(next);
        if (k !== 'search') apply(next);
    };

    return (
        <AdminLayout title="Products" actions={<Link href="/admin/products/create" className="btn-primary"><Icon name="plus" className="h-4 w-4" /> Add Product</Link>}>
            <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
                <input className="input lg:col-span-2" placeholder="Search name or SKU…" value={f.search} onChange={set('search')} aria-label="Search" />
                <select className="input" value={f.category_id} onChange={set('category_id')} aria-label="Category">
                    <option value="">All categories</option>
                    {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select className="input" value={f.brand_id} onChange={set('brand_id')} aria-label="Brand">
                    <option value="">All brands</option>
                    {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                </select>
                <select className="input" value={f.stock_status} onChange={set('stock_status')} aria-label="Stock status">
                    <option value="">Any stock status</option>
                    {Object.entries(stockStatuses).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
            </form>

            <div className="card overflow-x-auto">
                <table className="table-x min-w-[820px]">
                    <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th className="text-right">Price</th><th className="text-right">Stock</th><th>Status</th><th className="text-right">Actions</th></tr></thead>
                    <tbody>
                        {products.data.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <div className="flex items-center gap-3">
                                        {p.image_url && <img src={p.image_url} alt="" className="h-10 w-10 rounded object-cover" />}
                                        <div className="min-w-0">
                                            <Link href={`/admin/products/${p.id}/edit`} className="line-clamp-1 font-medium hover:text-accent-600">{p.name}</Link>
                                            <span className="text-xs text-slate-500">{p.brand?.name}{!p.is_active && ' · Hidden'}{p.is_featured && ' · Featured'}</span>
                                        </div>
                                    </div>
                                </td>
                                <td className="font-mono text-xs">{p.sku}</td>
                                <td>{p.category?.name}</td>
                                <td className="text-right">{money(p.price)}</td>
                                <td className="text-right">{p.stock}</td>
                                <td><span className={`badge ${statusColor(p.stock_status)}`}>{p.stock_label}</span></td>
                                <td className="whitespace-nowrap text-right">
                                    <a href={`/product/${p.slug}`} target="_blank" rel="noreferrer" className="btn-outline btn-sm mr-1" aria-label="View"><Icon name="external" className="h-3.5 w-3.5" /></a>
                                    <Link href={`/admin/products/${p.id}/edit`} className="btn-outline btn-sm mr-1" aria-label="Edit"><Icon name="edit" className="h-3.5 w-3.5" /></Link>
                                    <button onClick={() => confirm('Delete this product?') && router.delete(`/admin/products/${p.id}`, { preserveScroll: true })} className="btn-outline btn-sm hover:text-red-600" aria-label="Delete"><Icon name="trash" className="h-3.5 w-3.5" /></button>
                                </td>
                            </tr>
                        ))}
                        {products.data.length === 0 && <tr><td colSpan={7} className="py-10 text-center text-slate-500">No products found.</td></tr>}
                    </tbody>
                </table>
            </div>
            <Pagination meta={products} />
        </AdminLayout>
    );
}
