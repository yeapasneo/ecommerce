import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '../../Components/Admin/UI';
import { TextInput } from '../../Components/Field';
import Icon from '../../Components/Icon';
import Modal from '../../Components/Modal';
import AdminLayout from '../../Layouts/AdminLayout';
import { dateTime } from '../../lib/format';

export default function Staff({ staff, roles, permissions }) {
    const me = usePage().props.auth.user;
    const [adding, setAdding] = useState(false);
    const form = useForm({ name: '', email: '', password: '', role: 'orders' });
    const roleLabel = Object.fromEntries(roles.map((r) => [r.value, r.label]));
    const errors = usePage().props.errors;

    const save = (u, patch) => router.put(`/admin/staff/${u.id}`, { role: u.role, is_active: u.is_active, ...patch }, { preserveScroll: true });
    const remove = (u) => confirm(`Remove admin access for ${u.name}? Their customer account stays.`) && router.delete(`/admin/staff/${u.id}`, { preserveScroll: true });
    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/staff', { preserveScroll: true, onSuccess: () => { form.reset(); setAdding(false); } });
    };

    return (
        <AdminLayout
            title="Staff & Roles"
            count={staff.length}
            subtitle="Who can sign in to this panel and what each person can do"
            actions={<button onClick={() => setAdding(true)} className="btn-primary"><Icon name="plus" className="h-4 w-4" /> Add staff</button>}
        >
            {errors.role && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{errors.role}</p>}
            <div className="grid items-start gap-6 xl:grid-cols-[1fr_400px]">
                <div className="panel overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[720px]">
                            <thead><tr><th className="pl-6">Person</th><th>Role</th><th>Last active</th><th>Access</th><th className="w-px" /></tr></thead>
                            <tbody>
                                {staff.map((u) => {
                                    const self = u.id === me.id;
                                    return (
                                        <tr key={u.id}>
                                            <td className="pl-6">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">{u.name?.[0]?.toUpperCase()}</span>
                                                    <div className="min-w-0">
                                                        <p className="font-medium text-slate-900">{u.name}{self && <span className="ml-1.5 text-xs font-normal text-slate-500">(you)</span>}</p>
                                                        <p className="truncate text-xs text-slate-500">{u.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <select className="input h-9 w-44 py-1" value={u.role} disabled={self} onChange={(e) => save(u, { role: e.target.value })} aria-label={`Role for ${u.name}`}>
                                                    {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                                                </select>
                                            </td>
                                            <td className="whitespace-nowrap text-xs text-slate-500">{u.last_active ? dateTime(u.last_active) : 'Never'}</td>
                                            <td>
                                                <button type="button" disabled={self} onClick={() => save(u, { is_active: !u.is_active })} className={`badge rounded-md ring-1 ring-inset ring-current/20 ${u.is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'} ${self ? '' : 'cursor-pointer'}`} title={self ? '' : u.is_active ? 'Click to block' : 'Click to unblock'}>
                                                    {u.is_active ? 'Active' : 'Blocked'}
                                                </button>
                                            </td>
                                            <td className="pr-6">{!self && <button type="button" onClick={() => remove(u)} className="icon-btn hover:border-red-300 hover:text-red-600" aria-label={`Remove ${u.name}`}><Icon name="trash" className="h-4 w-4" /></button>}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <Panel title="What each role can do" icon="shield">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="text-slate-500"><th className="py-1.5 pr-2 text-left font-medium">Area</th>{roles.map((r) => <th key={r.value} className="px-1 py-1.5 font-medium">{r.label.split(' ')[0]}</th>)}</tr>
                            </thead>
                            <tbody>
                                <tr className="border-t border-slate-100"><td className="py-1.5 pr-2">Dashboard</td>{roles.map((r) => <td key={r.value} className="text-center text-green-600">✓</td>)}</tr>
                                {Object.entries(permissions).map(([p, desc]) => (
                                    <tr key={p} className="border-t border-slate-100">
                                        <td className="py-1.5 pr-2" title={desc}>{p[0].toUpperCase() + p.slice(1)}</td>
                                        {roles.map((r) => <td key={r.value} className="text-center">{r.permissions.includes(p) ? <span className="text-green-600">✓</span> : <span className="text-slate-300">—</span>}</td>)}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <ul className="mt-4 space-y-1 text-xs text-slate-500">
                        {Object.entries(permissions).map(([p, desc]) => <li key={p}><b className="text-slate-700">{p[0].toUpperCase() + p.slice(1)}:</b> {desc}</li>)}
                    </ul>
                </Panel>
            </div>

            <Modal open={adding} onClose={() => setAdding(false)} title="Add staff">
                <form onSubmit={submit} className="grid gap-4">
                    <p className="-mt-2 text-sm text-slate-500">Enter the email of an existing customer to give them access, or fill in all fields to create a new account.</p>
                    <TextInput label="Email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                    <TextInput label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} hint="Not needed for an existing account" />
                    <TextInput label="Password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} hint="At least 8 characters. Not needed for an existing account" />
                    <div>
                        <label className="label" htmlFor="staff-role">Role</label>
                        <select id="staff-role" className="input" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                            {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">{roleLabel[form.data.role]}: {roles.find((r) => r.value === form.data.role)?.permissions.join(', ')}</p>
                    </div>
                    <div className="-mx-6 -mb-6 mt-2 flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <button type="button" onClick={() => setAdding(false)} className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</button>
                        <button disabled={form.processing} className="btn-primary"><Icon name="check" className="h-4 w-4" /> Add</button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
