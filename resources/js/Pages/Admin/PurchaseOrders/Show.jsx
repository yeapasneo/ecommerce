import { Link, router, useForm } from '@inertiajs/react';
import { Panel } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { date, dateTime, money } from '../../../lib/format';

export const PO_TONE = {
    draft: 'bg-slate-100 text-slate-600',
    ordered: 'bg-blue-50 text-blue-700',
    partial: 'bg-amber-50 text-amber-700',
    received: 'bg-green-50 text-green-700',
    cancelled: 'bg-red-50 text-red-700',
};

export default function Show({ order, statuses, movements }) {
    const open = ['draft', 'ordered', 'partial'].includes(order.status);
    const receive = useForm({ items: Object.fromEntries(order.items.map((i) => [i.id, Math.max(0, i.quantity - i.received_quantity)])), update_cost: true });
    const setStatus = (status) => (status !== 'cancelled' || confirm('Cancel this purchase order?')) && router.post(`/admin/purchase-orders/${order.id}/status`, { status }, { preserveScroll: true });
    const expected = order.items.reduce((s, i) => s + i.quantity, 0);
    const got = order.items.reduce((s, i) => s + i.received_quantity, 0);

    return (
        <AdminLayout
            title={order.number}
            subtitle={`${order.supplier?.name ?? 'No supplier'} · created ${date(order.created_at)}${order.creator ? ` by ${order.creator.name}` : ''}`}
            back="/admin/purchase-orders"
            actions={
                <>
                    {['draft', 'ordered'].includes(order.status) && <Link href={`/admin/purchase-orders/${order.id}/edit`} className="btn-outline"><Icon name="edit" className="h-4 w-4" /> Edit</Link>}
                    {order.status === 'draft' && <button type="button" onClick={() => setStatus('ordered')} className="btn-primary"><Icon name="check" className="h-4 w-4" /> Mark as ordered</button>}
                    {['draft', 'ordered'].includes(order.status) && <button type="button" onClick={() => setStatus('cancelled')} className="btn-cancel rounded-lg">Cancel order</button>}
                    {['draft', 'cancelled'].includes(order.status) && <button type="button" onClick={() => confirm('Delete this purchase order?') && router.delete(`/admin/purchase-orders/${order.id}`)} className="icon-btn hover:text-red-600" aria-label="Delete"><Icon name="trash" className="h-4 w-4" /></button>}
                </>
            }
        >
            <div className="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
                <div className="space-y-6">
                    <form onSubmit={(e) => { e.preventDefault(); receive.post(`/admin/purchase-orders/${order.id}/receive`, { preserveScroll: true }); }}>
                        <Panel title="Items" actions={<span className={`badge rounded-md ${PO_TONE[order.status]}`}>{statuses[order.status]}</span>}>
                            <div className="overflow-x-auto">
                                <table className="table-admin min-w-[640px]">
                                    <thead><tr><th className="pl-0">Product</th><th className="text-right">Unit cost</th><th className="text-right">Ordered</th><th className="text-right">Received</th>{open && <th className="w-32">Receive now</th>}<th className="text-right">Total</th></tr></thead>
                                    <tbody>
                                        {order.items.map((i) => {
                                            const left = i.quantity - i.received_quantity;
                                            return (
                                                <tr key={i.id}>
                                                    <td className="pl-0">
                                                        <Link href={`/admin/products/${i.product_id}/edit`} className="font-medium text-slate-900 hover:text-primary-700">{i.name}</Link>
                                                        <p className="text-xs text-slate-500">{i.variant?.sku || i.product?.sku}</p>
                                                    </td>
                                                    <td className="text-right tabular-nums">{money(i.unit_cost)}</td>
                                                    <td className="text-right tabular-nums">{i.quantity}</td>
                                                    <td className={`text-right tabular-nums ${left === 0 ? 'text-green-700' : ''}`}>{i.received_quantity}</td>
                                                    {open && (
                                                        <td>
                                                            {left > 0 ? <input className="input h-9 py-1" type="number" min="0" max={left} value={receive.data.items[i.id]} onChange={(e) => receive.setData('items', { ...receive.data.items, [i.id]: e.target.value })} aria-label={`Receive ${i.name}`} /> : <span className="text-xs text-green-700">Done</span>}
                                                            {receive.errors[`items.${i.id}`] && <p className="mt-1 text-xs text-red-600">{receive.errors[`items.${i.id}`]}</p>}
                                                        </td>
                                                    )}
                                                    <td className="text-right tabular-nums">{money(i.unit_cost * i.quantity)}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot><tr><td colSpan={open ? 5 : 4} className="pl-0 text-right font-medium">Total</td><td className="text-right font-bold tabular-nums">{money(order.total)}</td></tr></tfoot>
                                </table>
                            </div>
                            {open && (
                                <div className="mt-4 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-4">
                                    <label className="flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" className="h-4 w-4 accent-primary-600" checked={receive.data.update_cost} onChange={(e) => receive.setData('update_cost', e.target.checked)} />
                                        Update cost prices to these unit costs
                                    </label>
                                    <button disabled={receive.processing} className="btn-primary ml-auto"><Icon name="box" className="h-4 w-4" /> {receive.processing ? 'Adding…' : 'Add to stock'}</button>
                                </div>
                            )}
                        </Panel>
                    </form>

                    {movements.length > 0 && (
                        <Panel title="Received into stock" bodyClassName="px-6 pb-5">
                            <ul className="divide-y divide-slate-100 text-sm">
                                {movements.map((m) => (
                                    <li key={m.id} className="flex justify-between gap-3 py-2">
                                        <span>+{m.change} · stock now {m.stock_after}</span>
                                        <span className="text-xs text-slate-500">{dateTime(m.created_at)}{m.user && ` · ${m.user.name}`}</span>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}
                </div>

                <Panel title="Summary" bodyClassName="px-6 pb-5">
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                        <dt className="text-slate-500">Supplier</dt><dd>{order.supplier?.name ?? '—'}{order.supplier?.phone && <span className="block text-xs text-slate-500">{order.supplier.phone}</span>}</dd>
                        <dt className="text-slate-500">Expected</dt><dd>{order.expected_at ? date(order.expected_at) : '—'}</dd>
                        <dt className="text-slate-500">Received</dt><dd>{got} of {expected} units{order.received_at && <span className="block text-xs text-slate-500">completed {date(order.received_at)}</span>}</dd>
                        {order.note && <><dt className="text-slate-500">Note</dt><dd className="whitespace-pre-line">{order.note}</dd></>}
                    </dl>
                </Panel>
            </div>
        </AdminLayout>
    );
}
