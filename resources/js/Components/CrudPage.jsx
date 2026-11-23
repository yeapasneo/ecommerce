import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../Layouts/AdminLayout';
import { Checkbox, Field, Select, TextArea, TextInput } from './Field';
import Icon from './Icon';
import Modal from './Modal';

/**
 * Generic admin list + modal form for simple resources.
 * fields: [{ name, label, type: text|number|textarea|select|checkbox|file|date|color, options, hint, full }]
 * columns: [{ label, render(row) }]
 */
export default function CrudPage({ title, rows, columns, fields, endpoint, singular, emptyRow, toForm, wide, extraActions }) {
    const [editing, setEditing] = useState(null); // null | 'new' | row
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
                        <input id={`f-${f.name}`} type="file" accept="image/*" className="input" onChange={(e) => form.setData(f.name, e.target.files[0] ?? null)} />
                    </Field>
                );
            default:
                return <TextInput {...common} type={f.type ?? 'text'} value={value} onChange={onChange} step={f.step} />;
        }
    };

    return (
        <AdminLayout
            title={title}
            actions={
                <>
                    <button onClick={() => open('new')} className="btn-primary"><Icon name="plus" className="h-4 w-4" /> Add {singular}</button>
                    {extraActions}
                </>
            }
        >
            <div className="card overflow-x-auto">
                <table className="table-x min-w-[640px]">
                    <thead>
                        <tr>
                            {columns.map((c) => <th key={c.label} className={c.className}>{c.label}</th>)}
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id}>
                                {columns.map((c) => <td key={c.label} className={c.className}>{c.render(row)}</td>)}
                                <td className="whitespace-nowrap text-right">
                                    <button onClick={() => open(row)} className="btn-outline btn-sm mr-1" aria-label="Edit"><Icon name="edit" className="h-3.5 w-3.5" /></button>
                                    <button onClick={() => destroy(row)} className="btn-outline btn-sm hover:text-red-600" aria-label="Delete"><Icon name="trash" className="h-3.5 w-3.5" /></button>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr><td colSpan={columns.length + 1} className="py-10 text-center text-slate-500">Nothing here yet.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Modal open={editing !== null} onClose={close} title={editing === 'new' ? `Add ${singular}` : `Edit ${singular}`} wide={wide}>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    {fields.map(input)}
                    <div className="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" onClick={close} className="btn-outline">Cancel</button>
                        <button disabled={form.processing} className="btn-primary">{form.processing ? 'Saving…' : 'Save'}</button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}

export const yesNo = (v) => <span className={`badge ${v ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'}`}>{v ? 'Yes' : 'No'}</span>;
export const activeBadge = (v) => <span className={`badge ${v ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'}`}>{v ? 'Active' : 'Inactive'}</span>;
