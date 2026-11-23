import { Link, router, useForm } from '@inertiajs/react';
import { Checkbox, Field, Select, TextArea, TextInput } from '../../../Components/Field';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Form({ product, categories, brands, stockStatuses }) {
    const editing = !!product;
    const form = useForm({
        name: product?.name ?? '',
        slug: product?.slug ?? '',
        sku: product?.sku ?? '',
        model: product?.model ?? '',
        category_id: product?.category_id ?? '',
        brand_id: product?.brand_id ?? '',
        price: product?.price ?? '',
        regular_price: product?.regular_price ?? '',
        stock: product?.stock ?? 0,
        stock_status: product?.stock_status ?? 'in_stock',
        warranty_months: product?.warranty_months ?? '',
        key_features: product?.key_features?.length ? product.key_features : [''],
        description: product?.description ?? '',
        is_active: product?.is_active ?? true,
        is_featured: product?.is_featured ?? false,
        meta_title: product?.meta_title ?? '',
        meta_description: product?.meta_description ?? '',
        specs: product?.specs?.length ? product.specs.map(({ group, name, value }) => ({ group, name, value })) : [{ group: 'Basic Information', name: '', value: '' }],
        image: null,
        gallery: [],
    });
    const set = (k) => (e) => form.setData(k, e.target.value);
    const e = form.errors;

    const submit = (ev) => {
        ev.preventDefault();
        if (editing) {
            form.transform((d) => ({ ...d, _method: 'put' }));
            form.post(`/admin/products/${product.id}`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.setData((d) => ({ ...d, image: null, gallery: [] })) });
        } else {
            form.post('/admin/products', { forceFormData: true });
        }
    };

    const setFeature = (i, v) => form.setData('key_features', form.data.key_features.map((f, j) => (j === i ? v : f)));
    const setSpec = (i, k, v) => form.setData('specs', form.data.specs.map((s, j) => (j === i ? { ...s, [k]: v } : s)));
    const specErrors = Object.entries(e).filter(([k]) => k.startsWith('specs.'));

    return (
        <AdminLayout title={editing ? `Edit: ${product.name}` : 'Add Product'} actions={<Link href="/admin/products" className="btn-outline"><Icon name="chevronLeft" className="h-4 w-4" /> Back to products</Link>}>
            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[1fr_340px]">
                <div className="space-y-6">
                    <section className="card grid gap-4 p-5 sm:grid-cols-2">
                        <h2 className="font-semibold sm:col-span-2">General</h2>
                        <TextInput label="Product Name*" className="sm:col-span-2" value={form.data.name} onChange={set('name')} error={e.name} />
                        <TextInput label="SKU / Product Code*" value={form.data.sku} onChange={set('sku')} error={e.sku} />
                        <TextInput label="Model" value={form.data.model} onChange={set('model')} error={e.model} />
                        <Select label="Category*" value={form.data.category_id} onChange={set('category_id')} error={e.category_id} placeholder="Select category" options={categories.map((c) => ({ value: c.id, label: c.name }))} />
                        <Select label="Brand" value={form.data.brand_id} onChange={set('brand_id')} error={e.brand_id} placeholder="No brand" options={brands.map((b) => ({ value: b.id, label: b.name }))} />
                        <TextArea label="Description (HTML allowed)" className="sm:col-span-2" rows={6} value={form.data.description} onChange={set('description')} error={e.description} />
                    </section>

                    <section className="card p-5">
                        <h2 className="mb-3 font-semibold">Key Features</h2>
                        <div className="space-y-2">
                            {form.data.key_features.map((f, i) => (
                                <div key={i} className="flex gap-2">
                                    <input className="input" value={f} onChange={(ev) => setFeature(i, ev.target.value)} placeholder="e.g. Processor: Intel Core i7" aria-label={`Feature ${i + 1}`} />
                                    <button type="button" onClick={() => form.setData('key_features', form.data.key_features.filter((_, j) => j !== i))} className="btn-outline btn-sm" aria-label="Remove feature"><Icon name="x" className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                        <button type="button" onClick={() => form.setData('key_features', [...form.data.key_features, ''])} className="btn-outline btn-sm mt-3"><Icon name="plus" className="h-4 w-4" /> Add feature</button>
                    </section>

                    <section className="card p-5">
                        <h2 className="mb-1 font-semibold">Specifications</h2>
                        <p className="mb-3 text-xs text-slate-500">Spec names used in a category's "filter attributes" appear as storefront filters.</p>
                        <div className="space-y-2">
                            {form.data.specs.map((s, i) => (
                                <div key={i} className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2">
                                    <input className="input" value={s.group} onChange={(ev) => setSpec(i, 'group', ev.target.value)} placeholder="Group" aria-label="Spec group" />
                                    <input className="input" value={s.name} onChange={(ev) => setSpec(i, 'name', ev.target.value)} placeholder="Name (e.g. RAM)" aria-label="Spec name" />
                                    <input className="input" value={s.value} onChange={(ev) => setSpec(i, 'value', ev.target.value)} placeholder="Value (e.g. 16GB)" aria-label="Spec value" />
                                    <button type="button" onClick={() => form.setData('specs', form.data.specs.filter((_, j) => j !== i))} className="btn-outline btn-sm" aria-label="Remove spec"><Icon name="x" className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                        {specErrors.length > 0 && <p className="mt-2 text-xs text-red-600">Each specification needs both a name and a value.</p>}
                        <button type="button" onClick={() => form.setData('specs', [...form.data.specs, { group: form.data.specs.at(-1)?.group ?? 'General', name: '', value: '' }])} className="btn-outline btn-sm mt-3"><Icon name="plus" className="h-4 w-4" /> Add specification</button>
                    </section>

                    <section className="card grid gap-4 p-5">
                        <h2 className="font-semibold">SEO</h2>
                        <TextInput label="Slug" value={form.data.slug} onChange={set('slug')} error={e.slug} hint="Leave blank to generate from the name" />
                        <TextInput label="Meta Title" value={form.data.meta_title} onChange={set('meta_title')} error={e.meta_title} />
                        <TextArea label="Meta Description" rows={2} value={form.data.meta_description} onChange={set('meta_description')} error={e.meta_description} />
                    </section>
                </div>

                <div className="space-y-6">
                    <section className="card grid gap-4 p-5">
                        <h2 className="font-semibold">Pricing & Stock</h2>
                        <TextInput label="Price (৳)*" type="number" step="0.01" value={form.data.price} onChange={set('price')} error={e.price} />
                        <TextInput label="Regular Price (৳)" type="number" step="0.01" value={form.data.regular_price} onChange={set('regular_price')} error={e.regular_price} hint="Higher than price to show a discount" />
                        <TextInput label="Stock Quantity*" type="number" value={form.data.stock} onChange={set('stock')} error={e.stock} />
                        <Select label="Stock Status*" value={form.data.stock_status} onChange={set('stock_status')} error={e.stock_status} options={Object.entries(stockStatuses).map(([v, l]) => ({ value: v, label: l }))} />
                        <TextInput label="Warranty (months)" type="number" value={form.data.warranty_months} onChange={set('warranty_months')} error={e.warranty_months} />
                        <Checkbox label="Visible in store" checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
                        <Checkbox label="Featured on homepage" checked={form.data.is_featured} onChange={(v) => form.setData('is_featured', v)} />
                    </section>

                    <section className="card grid gap-4 p-5">
                        <h2 className="font-semibold">Images</h2>
                        {product?.image_url && <img src={product.image_url} alt="" className="h-40 w-full rounded object-contain" />}
                        <Field label="Main Image" error={e.image}>
                            <input type="file" accept="image/*" className="input" onChange={(ev) => form.setData('image', ev.target.files[0] ?? null)} />
                        </Field>
                        {product?.images?.length > 0 && (
                            <div className="grid grid-cols-3 gap-2">
                                {product.images.map((img) => (
                                    <div key={img.id} className="group relative">
                                        <img src={img.url} alt="" className="h-20 w-full rounded object-cover" />
                                        <button type="button" onClick={() => router.delete(`/admin/products/${product.id}/images/${img.id}`, { preserveScroll: true })} className="absolute right-1 top-1 rounded bg-white/90 p-1 text-red-600 shadow" aria-label="Delete image"><Icon name="trash" className="h-3.5 w-3.5" /></button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <Field label="Add Gallery Images" error={e.gallery || Object.entries(e).find(([k]) => k.startsWith('gallery.'))?.[1]}>
                            <input type="file" accept="image/*" multiple className="input" onChange={(ev) => form.setData('gallery', Array.from(ev.target.files))} />
                        </Field>
                    </section>

                    <button disabled={form.processing} className="btn-primary w-full py-3">{form.processing ? 'Saving…' : editing ? 'Update Product' : 'Create Product'}</button>
                </div>
            </form>
        </AdminLayout>
    );
}
