import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';

const show = (v) => (v === null || v === undefined || v === '' ? '—' : v === true ? 'yes' : v === false ? 'no' : String(v));

export default function Import({ columns, editable, categories, brands, preview }) {
    const upload = useForm({ file: null });
    const [filters, setFilters] = useState({ category_id: '', brand_id: '', stock: '' });
    const [applying, setApplying] = useState(false);
    const qs = new URLSearchParams(Object.entries(filters).filter(([, v]) => v)).toString();

    return (
        <AdminLayout title="Import / Export Products" subtitle="Update prices, cost, stock and status for many products at once" back="/admin/products">
            <div className="grid items-start gap-6 xl:grid-cols-2">
                <Panel title="1. Download the product sheet" icon="file">
                    <p className="-mt-2 mb-4 text-sm text-slate-500">A CSV file that opens in Excel or Google Sheets. Products with variants have one row per variant.</p>
                    <div className="mb-4 grid gap-3 sm:grid-cols-3">
                        <select className="input" value={filters.category_id} onChange={(e) => setFilters({ ...filters, category_id: e.target.value })} aria-label="Category">
                            <option value="">All categories</option>
                            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        <select className="input" value={filters.brand_id} onChange={(e) => setFilters({ ...filters, brand_id: e.target.value })} aria-label="Brand">
                            <option value="">All brands</option>
                            {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                        </select>
                        <select className="input" value={filters.stock} onChange={(e) => setFilters({ ...filters, stock: e.target.value })} aria-label="Stock">
                            <option value="">Any stock</option>
                            <option value="low">Low stock only</option>
                        </select>
                    </div>
                    <a href={`/admin/products/export${qs ? `?${qs}` : ''}`} className="btn-primary"><Icon name="file" className="h-4 w-4" /> Download CSV</a>
                </Panel>

                <Panel title="2. Upload the edited file" icon="refresh">
                    <ul className="-mt-2 mb-4 list-disc space-y-1 pl-5 text-sm text-slate-600">
                        <li>You can change: <b>{editable.join(', ')}</b>. Other columns are ignored.</li>
                        <li>Leave a cell <b>blank</b> to keep the current value. Put <b>-</b> in regular_price to remove a discount.</li>
                        <li>stock_status: in_stock, out_of_stock, pre_order or up_coming. active: yes or no.</li>
                        <li>Save as CSV, or upload the Excel (.xlsx) file directly. You'll see every change before anything is saved.</li>
                    </ul>
                    <form onSubmit={(e) => { e.preventDefault(); upload.post('/admin/products/import', { forceFormData: true }); }} className="flex flex-wrap items-center gap-3">
                        <input type="file" accept=".csv,.xlsx,text/csv" onChange={(e) => upload.setData('file', e.target.files[0])} className="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700" aria-label="Spreadsheet file" />
                        <button disabled={!upload.data.file || upload.processing} className="btn-primary">{upload.processing ? 'Reading…' : 'Check changes'}</button>
                    </form>
                    {upload.errors.file && <p className="mt-2 text-sm text-red-600" role="alert">{upload.errors.file}</p>}
                </Panel>
            </div>

            {preview && (
                <div className="panel mt-6 overflow-hidden">
                    <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 px-6 py-4">
                        <div className="mr-auto">
                            <h2 className="font-semibold text-slate-900">3. Check and apply · {preview.file}</h2>
                            <p className="text-sm text-slate-500"><b className="text-slate-900">{preview.count}</b> to change · {preview.unchanged} unchanged · <span className={preview.errorCount ? 'text-red-600' : ''}>{preview.errorCount} row(s) with problems (skipped)</span></p>
                        </div>
                        {preview.count > 0 && (
                            <button type="button" disabled={applying} onClick={() => confirm(`Update ${preview.count} product(s)/variant(s)?`) && router.post('/admin/products/import/apply', { token: preview.token }, { onStart: () => setApplying(true), onFinish: () => setApplying(false) })} className="btn-primary">
                                <Icon name="check" className="h-4 w-4" /> {applying ? 'Saving…' : `Apply ${preview.count} change${preview.count === 1 ? '' : 's'}`}
                            </button>
                        )}
                    </div>
                    {preview.errors.length > 0 && (
                        <ul className="max-h-48 space-y-1 overflow-y-auto border-b border-slate-100 bg-red-50/60 px-6 py-3 text-sm text-red-700">
                            {preview.errors.map((e, i) => <li key={i}>Row {e.line}: {e.message}</li>)}
                            {preview.errorCount > preview.errors.length && <li>…and {preview.errorCount - preview.errors.length} more.</li>}
                        </ul>
                    )}
                    {preview.count === 0 ? <p className="px-6 py-8 text-center text-sm text-slate-500">Nothing to change.</p> : (
                        <div className="max-h-[32rem] overflow-auto">
                            <table className="table-admin min-w-[720px]">
                                <thead className="sticky top-0 bg-white"><tr><th className="pl-6">Row</th><th>Product</th><th className="pr-6">Changes</th></tr></thead>
                                <tbody>
                                    {preview.changes.map((c) => (
                                        <tr key={`${c.type}-${c.id}`}>
                                            <td className="pl-6 text-xs text-slate-500">{c.line}</td>
                                            <td className="font-medium text-slate-900">{c.label}</td>
                                            <td className="pr-6">
                                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                                    {Object.entries(c.fields).map(([k, [from, to]]) => (
                                                        <span key={k}><span className="text-slate-500">{k.replace('_', ' ')}:</span> <span className="text-red-600 line-through decoration-red-300">{show(from)}</span> → <b className="text-green-700">{show(to)}</b></span>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {preview.count > preview.changes.length && <p className="px-6 py-3 text-sm text-slate-500">Showing the first {preview.changes.length} of {preview.count}. All of them will be applied.</p>}
                        </div>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
