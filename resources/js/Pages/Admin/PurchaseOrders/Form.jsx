import { Link, useForm } from '@inertiajs/react';
import ProductPicker from '../../../Components/Admin/ProductPicker';
import { EmptyState, Panel } from '../../../Components/Admin/UI';
import { TextArea, TextInput } from '../../../Components/Field';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { money } from '../../../lib/format';

export default function Form({ order, prefill, suppliers }) {
    const form = useForm({
        supplier_id: order?.supplier_id ?? '',
        expected_at: order?.expected_at?.slice(0, 10) ?? '',
        note: order?.note ?? '',
        items: order
            ? order.items.map((i) => ({ product_id: i.product_id, variant_id: i.variant_id, name: i.name, sku: i.variant?.sku || i.product?.sku, quantity: i.quantity, unit_cost: i.unit_cost }))
            : prefill,
    });
    const d = form.data;
    const e = form.errors;
    const setItem = (i, patch) => form.setData('items', d.items.map((it, j) => (j === i ? { ...it, ...patch } : it)));
    const add = (p) => {
        if (d.items.some((i) => i.product_id === p.id && (i.variant_id ?? null) === (p.variant_id ?? null))) return;
        form.setData('items', [...d.items, { product_id: p.id, variant_id: p.variant_id, name: p.name + (p.variant_name ? ` (${p.variant_name})` : ''), sku: p.sku, quantity: 1, unit_cost: p.cost_price ?? '' }]);
    };
    const total = d.items.reduce((s, i) => s + (Number(i.quantity) || 0) * (Number(i.unit_cost) || 0), 0);
    const submit = (ev) => {
        ev.preventDefault();
        order ? form.put(`/admin/purchase-orders/${order.id}`) : form.post('/admin/purchase-orders');
    };

    return (
        <AdminLayout
            title={order ? `Edit ${order.number}` : 'New Purchase Order'}
            back="/admin/purchase-orders"
            actions={
                <>
                    <Link href="/admin/purchase-orders" className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</Link>
                    <button form="po-form" disabled={form.processing} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : 'Save'}</button>
                </>
            }
        >
            <form id="po-form" onSubmit={submit} className="grid items-start gap-6 xl:grid-cols-[1fr_360px]">
                <Panel title="Products">
                    <ProductPicker onPick={add} />
                    {e.items && <p className="mt-2 text-sm text-red-600">{e.items}</p>}
                    {d.items.length === 0 ? (
                        <div className="mt-4"><EmptyState dashed icon="box" title="No products yet" text="Search above to add the products you're buying." /></div>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="table-admin min-w-[640px]">
                                <thead><tr><th className="pl-0">Product</th><th className="w-28">Quantity</th><th className="w-36">Unit cost (৳)</th><th className="w-32 text-right">Line total</th><th className="w-px" /></tr></thead>
                                <tbody>
                                    {d.items.map((it, i) => (
                                        <tr key={`${it.product_id}-${it.variant_id ?? 0}`}>
                                            <td className="pl-0"><p className="font-medium text-slate-900">{it.name}</p><p className="text-xs text-slate-500">{it.sku}</p></td>
                                            <td>
                                                <input className="input h-9 py-1" type="number" min="1" value={it.quantity} onChange={(ev) => setItem(i, { quantity: ev.target.value })} aria-label="Quantity" />
                                                {e[`items.${i}.quantity`] && <p className="mt-1 text-xs text-red-600">{e[`items.${i}.quantity`]}</p>}
                                            </td>
                                            <td>
                                                <input className="input h-9 py-1" type="number" min="0" step="0.01" value={it.unit_cost} onChange={(ev) => setItem(i, { unit_cost: ev.target.value })} aria-label="Unit cost" />
                                                {e[`items.${i}.unit_cost`] && <p className="mt-1 text-xs text-red-600">{e[`items.${i}.unit_cost`]}</p>}
                                            </td>
                                            <td className="text-right tabular-nums">{money((Number(it.quantity) || 0) * (Number(it.unit_cost) || 0))}</td>
                                            <td><button type="button" onClick={() => form.setData('items', d.items.filter((_, j) => j !== i))} className="icon-btn h-9 w-9 hover:text-red-600" aria-label="Remove"><Icon name="x" className="h-4 w-4" /></button></td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot><tr><td colSpan={3} className="pl-0 text-right font-medium">Total</td><td className="text-right font-bold tabular-nums">{money(total)}</td><td /></tr></tfoot>
                            </table>
                        </div>
                    )}
                </Panel>
                <Panel title="Details">
                    <div className="space-y-4">
                        <div>
                            <label className="label" htmlFor="po-supplier">Supplier</label>
                            <select id="po-supplier" className="input" value={d.supplier_id ?? ''} onChange={(ev) => form.setData('supplier_id', ev.target.value)}>
                                <option value="">— None —</option>
                                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                            <Link href="/admin/suppliers" className="mt-1 inline-block text-xs text-primary-700 hover:underline">Manage suppliers</Link>
                        </div>
                        <TextInput label="Expected delivery" type="date" value={d.expected_at} onChange={(ev) => form.setData('expected_at', ev.target.value)} error={e.expected_at} />
                        <TextArea label="Note" rows={3} value={d.note} onChange={(ev) => form.setData('note', ev.target.value)} error={e.note} />
                        <p className="text-xs text-slate-500">Stock is added only when you receive the delivery on the next screen. Receiving also updates each product's cost price.</p>
                    </div>
                </Panel>
            </form>
        </AdminLayout>
    );
}
