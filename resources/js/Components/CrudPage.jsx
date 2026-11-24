import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../Layouts/AdminLayout';
import { EmptyState, RowMenu, SearchBox } from './Admin/UI';
import { Checkbox, Field, Select, TextArea, TextInput } from './Field';
import Icon from './Icon';
import Modal from './Modal';

/**
 * Generic admin list + modal form for simple resources.
 * fields: [{ name, label, type: text|number|textarea|select|checkbox|file|date|color, options, hint, full }]
 * columns: [{ label, render(row) }]
 */
export default function CrudPage({ title, rows, columns, fields, endpoint, singular, emptyRow, toForm, wide, extraActions, description }) {
    const [editing, setEditing] = useState(null); // null | 'new' | row
    const [search, setSearch] = useState('');
    const shown = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) => ['name', 'title', 'code', 'slug'].some((k) => String(r[k] ?? '').toLowerCase().includes(q)));
    }, [rows, search]);
    const form = useForm(emptyRow);
    const hasFile = fields.some((f) => f.type === 'file');

    const open = (row) => {
        form.clearErrors();
        if (row === 'new') {
            form.setData(emptyRow);
        } else {
            const src = toForm ? toForm(row) : row;
            const fileFields = fields.filter((f) => f.type === 'file').map((f) => f.name);
            form.setData(Object.fromEntries(Object.keys(emptyRow).map((k) => [k, fileFields.includes(k) ? null : (src[k] ?? emptyRow[k])])));
        }
        setEditing(row);
    };
    const close = () => setEditing(null);

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: close, forceFormData: hasFile };
        if (editing === 'new') {
            form.post(endpoint, opts);
        } else {
            form.transform((d) => ({ ...d, _method: 'put' }));
            form.post(`${endpoint}/${editing.id}`, { ...opts, onFinish: () => form.transform((d) => d) });
        }
    };

    const destroy = (row) => confirm(`Delete this ${singular.toLowerCase()}?`) && router.delete(`${endpoint}/${row.id}`, { preserveScroll: true });

    const input = (f) => {
        const common = { key: f.name, label: f.label, error: form.errors[f.name], hint: f.hint, className: f.full ? 'sm:col-span-2' : '' };
        const value = form.data[f.name] ?? '';
        const onChange = (e) => form.setData(f.name, e.target.value);
        switch (f.type) {
            case 'textarea':
                return <TextArea {...common} rows={f.rows ?? 4} value={value} onChange={onChange} />;
            case 'select':
                return <Select {...common} value={value} onChange={onChange} options={f.options} placeholder={f.placeholder} />;
            case 'checkbox':
                return <Checkbox key={f.name} label={f.label} checked={form.data[f.name]} onChange={(v) => form.setData(f.name, v)} className={f.full ? 'sm:col-span-2' : ''} />;
            case 'file':
                return (
                    <Field {...common} id={`f-${f.name}`}>
                        <input id={`f-${f.name}`} type="file" accept="image/*" className="input file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-700" onChange={(e) => form.setData(f.name, e.target.files[0] ?? null)} />
                    </Field>
                );
            default:
                return <TextInput {...common} type={f.type ?? 'text'} value={value} onChange={onChange} step={f.step} />;
        }
    };

    const addButton = <button onClick={() => open('new')} className="btn-primary h-10"><Icon name="plus" className="h-4 w-4" /> Add {singular}</button>;

    return (
        <AdminLayout title={title} header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <div className="mr-auto">
                    <h1 className="flex items-center gap-2 text-xl font-semibold text-slate-900">
                        {title} <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{rows.length.toLocaleString()}</span>
                    </h1>
                    {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
                </div>
                {rows.length > 8 && <SearchBox value={search} onChange={setSearch} onSubmit={() => {}} placeholder={`Search ${title.toLowerCase()}…`} />}
                {extraActions}
                {addButton}
            </div>

            <div className="panel overflow-hidden">
                {shown.length === 0 ? (
                    search ? (
                        <EmptyState icon="search" title="Nothing matches" text={`No ${title.toLowerCase()} match "${search}".`} />
                    ) : (
                        <EmptyState title={`No ${title} Added`} text={`Create your first ${singular.toLowerCase()} to get started.`} action={addButton} />
                    )
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[640px]">
                            <thead>
                                <tr>
                                    {columns.map((c, i) => <th key={c.label} className={`${i === 0 ? 'pl-6' : ''} ${c.className ?? ''}`}>{c.label}</th>)}
                                    <th className="w-px" />
                                </tr>
                            </thead>
                            <tbody>
                                {shown.map((row) => (
                                    <tr key={row.id}>
                                        {columns.map((c, i) => <td key={c.label} className={`${i === 0 ? 'pl-6' : ''} ${c.className ?? ''}`}>{c.render(row)}</td>)}
                                        <td className="pr-6">
                                            <div className="flex items-center justify-end gap-2">
                                                <RowMenu items={[{ label: 'Edit', icon: 'edit', onClick: () => open(row) }, { label: 'Delete', icon: 'trash', onClick: () => destroy(row), danger: true }]} />
                                                <button onClick={() => open(row)} className="btn-outline h-9 px-3 font-medium" aria-label="Edit"><Icon name="edit" className="h-4 w-4" /> Edit</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Modal open={editing !== null} onClose={close} title={editing === 'new' ? `Add ${singular}` : `Edit ${singular}`} wide={wide}>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    {fields.map(input)}
                    <div className="-mx-6 -mb-6 mt-2 flex justify-end gap-2 border-t border-slate-100 px-6 py-4 sm:col-span-2">
                        <button type="button" onClick={close} className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</button>
                        <button disabled={form.processing} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : editing === 'new' ? 'Create' : 'Save'}</button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}

const soft = 'badge rounded-md ring-1 ring-inset ring-current/20';
export const yesNo = (v) => <span className={`${soft} ${v ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'}`}>{v ? 'Yes' : 'No'}</span>;
export const activeBadge = (v) => <span className={`${soft} ${v ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'}`}>{v ? 'Active' : 'Inactive'}</span>;
