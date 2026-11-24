import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Field, Select, TextArea, TextInput } from '../../../Components/Field';
import SeoFields from '../../../Components/Admin/SeoFields';
import { EmptyState, Panel, SubCard, Toggle } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { money } from '../../../lib/format';

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
        meta_keywords: product?.meta_keywords ?? '',
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
    const price = Number(form.data.price) || 0;
    const regular = Number(form.data.regular_price) || 0;
    const discount = regular > price && price > 0 ? Math.round(((regular - price) / regular) * 100) : 0;
    const upload = useMemo(() => (form.data.image ? URL.createObjectURL(form.data.image) : null), [form.data.image]);
    useEffect(() => () => upload && URL.revokeObjectURL(upload), [upload]);
    const preview = upload ?? product?.image_url;

    // What the storefront uses when the SEO fields are left blank (mirrors ProductController::seo).
    const brandName = brands.find((b) => String(b.id) === String(form.data.brand_id))?.name ?? '';
    const categoryName = categories.find((c) => String(c.id) === String(form.data.category_id))?.name ?? '';
    const features = form.data.key_features.filter((f) => f.trim());
    const name = form.data.name.trim() || 'Product name';
    const autoTitle = price > 0 ? [`${name} Price in Bangladesh`, `${name} Price in BD`].find((t) => t.length <= 60) ?? name : name;
    const autoDescription = `${name}${price > 0 ? ` price in BD: ৳${Math.round(price).toLocaleString('en-US')}.` : '.'} ${features.slice(0, 4).join(', ')}`.trim();

    return (
        <AdminLayout
            title={editing ? 'Edit Product' : 'Create Product'}
            subtitle={editing ? product.name : undefined}
            back="/admin/products"
            actions={
                <>
                    <Link href="/admin/products" className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</Link>
                    <button form="product-form" disabled={form.processing} className="btn-primary rounded-lg"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : editing ? 'Update' : 'Create'}</button>
                </>
            }
        >
            <form id="product-form" onSubmit={submit} className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                <div className="space-y-6">
                    <Panel title="Product Details">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextInput label="Product Name*" className="sm:col-span-2" value={form.data.name} onChange={set('name')} error={e.name} placeholder="e.g. AMD Ryzen 5 7600 Processor" />
                            <TextInput label="SKU / Product Code*" value={form.data.sku} onChange={set('sku')} error={e.sku} />
                            <TextInput label="Model" value={form.data.model} onChange={set('model')} error={e.model} />
                            <TextArea label="Description (HTML allowed)" className="sm:col-span-2" rows={6} value={form.data.description} onChange={set('description')} error={e.description} />
                        </div>
                    </Panel>

                    <Panel title="Pricing & Inventory">
                        <div className="grid gap-4 md:grid-cols-2">
                            <SubCard title="Pricing" icon="percent" tone="orange">
                                <div className="grid grid-cols-2 gap-3">
                                    <TextInput label="Price (৳)*" type="number" step="0.01" value={form.data.price} onChange={set('price')} error={e.price} />
                                    <TextInput label="Regular Price (৳)" type="number" step="0.01" value={form.data.regular_price} onChange={set('regular_price')} error={e.regular_price} />
                                </div>
                                <p className="mt-2 text-xs text-slate-500">Set a regular price above the price to show a discount.</p>
                            </SubCard>
                            <SubCard title="Inventory" icon="box" tone="blue">
                                <div className="grid grid-cols-2 gap-3">
                                    <TextInput label="Stock Quantity*" type="number" value={form.data.stock} onChange={set('stock')} error={e.stock} />
                                    <TextInput label="Warranty (months)" type="number" value={form.data.warranty_months} onChange={set('warranty_months')} error={e.warranty_months} />
                                    <Select label="Stock Status*" className="col-span-2" value={form.data.stock_status} onChange={set('stock_status')} error={e.stock_status} options={Object.entries(stockStatuses).map(([v, l]) => ({ value: v, label: l }))} />
                                </div>
                            </SubCard>
                        </div>
                        <div className="mt-6 flex justify-end border-t border-slate-200 pt-6">
                            <div className="rounded-xl border-2 border-primary-200 bg-primary-50/60 px-6 py-3 text-center">
                                <p className="text-sm font-medium text-primary-700">Selling Price</p>
                                <p className="text-3xl font-bold text-primary-700">{money(price)}</p>
                                {discount > 0 && <p className="text-xs font-medium text-green-700">{discount}% off {money(regular)}</p>}
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Key Features">
                        <div className="space-y-2">
                            {form.data.key_features.map((f, i) => (
                                <div key={i} className="flex gap-2">
                                    <input className="input rounded-lg" value={f} onChange={(ev) => setFeature(i, ev.target.value)} placeholder="e.g. Processor: Intel Core i7" aria-label={`Feature ${i + 1}`} />
                                    <button type="button" onClick={() => form.setData('key_features', form.data.key_features.filter((_, j) => j !== i))} className="icon-btn h-[38px] w-[38px] shrink-0 hover:text-red-600" aria-label="Remove feature"><Icon name="x" className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                        <button type="button" onClick={() => form.setData('key_features', [...form.data.key_features, ''])} className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary-700 hover:underline"><Icon name="plus" className="h-4 w-4" /> Add feature</button>
                    </Panel>

                    <Panel title="Specifications">
                        <p className="-mt-2 mb-4 text-sm text-slate-500">Spec names used in a category's filter attributes appear as storefront filters.</p>
                        <div className="space-y-2">
                            {form.data.specs.map((s, i) => (
                                <div key={i} className="grid grid-cols-[1fr_1fr_1.3fr_auto] gap-2">
                                    <input className="input rounded-lg" value={s.group} onChange={(ev) => setSpec(i, 'group', ev.target.value)} placeholder="Group" aria-label="Spec group" />
                                    <input className="input rounded-lg" value={s.name} onChange={(ev) => setSpec(i, 'name', ev.target.value)} placeholder="Name (e.g. RAM)" aria-label="Spec name" />
                                    <input className="input rounded-lg" value={s.value} onChange={(ev) => setSpec(i, 'value', ev.target.value)} placeholder="Value (e.g. 16GB)" aria-label="Spec value" />
                                    <button type="button" onClick={() => form.setData('specs', form.data.specs.filter((_, j) => j !== i))} className="icon-btn h-[38px] w-[38px] hover:text-red-600" aria-label="Remove spec"><Icon name="x" className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                        {specErrors.length > 0 && <p className="mt-2 text-xs text-red-600">Each specification needs both a name and a value.</p>}
                        <button type="button" onClick={() => form.setData('specs', [...form.data.specs, { group: form.data.specs.at(-1)?.group ?? 'General', name: '', value: '' }])} className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary-700 hover:underline"><Icon name="plus" className="h-4 w-4" /> Add specification</button>
                    </Panel>

                    <SeoFields
                        form={form}
                        path="/product/"
                        auto={{ title: autoTitle, description: autoDescription, slug: form.data.name }}
                        generate={() => ({
                            title: autoTitle,
                            description: autoDescription,
                            keywords: [form.data.name, brandName, categoryName, brandName && categoryName ? `${brandName} ${categoryName}` : null, categoryName ? `${categoryName} price in bangladesh` : null].filter(Boolean).join(', ').toLowerCase(),
                        })}
                        checks={[
                            { ok: !!preview, label: 'Has a main image' },
                            { ok: features.length >= 3, label: 'At least 3 key features' },
                            { ok: form.data.description.replace(/<[^>]+>/g, '').length >= 150, label: 'Description has 150+ characters' },
                            { ok: form.data.specs.filter((sp) => sp.name && sp.value).length >= 5, label: '5+ specifications' },
                        ]}
                    />
                </div>

                <div className="space-y-6">
                    <Panel title="Product Status">
                        <div className="space-y-5">
                            <Toggle label="Visible in store" description="Hidden products can't be found or bought." checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
                            <Toggle label="Featured on homepage" description="Shows the product in the homepage featured list." checked={form.data.is_featured} onChange={(v) => form.setData('is_featured', v)} />
                        </div>
                    </Panel>

                    <Panel title="Organization">
                        <div className="grid gap-4">
                            <Select label="Category*" value={form.data.category_id} onChange={set('category_id')} error={e.category_id} placeholder="Select category" options={categories.map((c) => ({ value: c.id, label: c.name }))} />
                            <Select label="Brand" value={form.data.brand_id} onChange={set('brand_id')} error={e.brand_id} placeholder="No brand" options={brands.map((b) => ({ value: b.id, label: b.name }))} />
                        </div>
                    </Panel>

                    <Panel title="Images">
                        {preview ? (
                            <div className="mb-4 grid h-56 place-items-center rounded-xl border border-slate-200 bg-white p-3">
                                <img src={preview} alt="" className="max-h-full max-w-full object-contain" />
                            </div>
                        ) : (
                            <div className="mb-4 rounded-xl border border-dashed border-slate-300">
                                <EmptyState icon="image" title="No image yet" text="Upload a main product photo." />
                            </div>
                        )}
                        <Field label={preview ? 'Replace main image' : 'Main image'} error={e.image}>
                            <input type="file" accept="image/*" className="input rounded-lg file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-700" onChange={(ev) => form.setData('image', ev.target.files[0] ?? null)} />
                        </Field>
                        {product?.images?.length > 0 && (
                            <div className="mt-4 grid grid-cols-4 gap-2">
                                {product.images.map((img) => (
                                    <div key={img.id} className="relative rounded-lg border border-slate-200 p-1">
                                        <img src={img.url} alt="" className="h-16 w-full object-contain" />
                                        <button type="button" onClick={() => router.delete(`/admin/products/${product.id}/images/${img.id}`, { preserveScroll: true })} className="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full bg-white text-red-600 shadow ring-1 ring-slate-200" aria-label="Delete image"><Icon name="x" className="h-3 w-3" /></button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <Field label="Add gallery images" className="mt-4" error={e.gallery || Object.entries(e).find(([k]) => k.startsWith('gallery.'))?.[1]}>
                            <input type="file" accept="image/*" multiple className="input rounded-lg file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-700" onChange={(ev) => form.setData('gallery', Array.from(ev.target.files))} />
                        </Field>
                    </Panel>

                </div>
            </form>
        </AdminLayout>
    );
}
