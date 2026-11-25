import { router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, SearchBox } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { cleanParams, dateTime } from '../../lib/format';

const ACTION = {
    created: ['plus', 'bg-green-50 text-green-700'],
    updated: ['edit', 'bg-blue-50 text-blue-700'],
    deleted: ['trash', 'bg-red-50 text-red-700'],
    signed_in: ['user', 'bg-slate-100 text-slate-600'],
};

const show = (v) => (v === null || v === undefined || v === '' ? '—' : v === true ? 'yes' : v === false ? 'no' : String(v));

export default function Activity({ logs, filters, staff, types }) {
    const [f, setF] = useState({ user_id: '', type: '', search: '', ...filters });
    const [open, setOpen] = useState(null);
    const apply = (next) => { setF(next); router.get('/admin/activity', cleanParams(next), { preserveState: true, preserveScroll: true }); };

    return (
        <AdminLayout title="Activity Log" header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <h1 className="mr-auto flex items-center gap-2 text-xl font-semibold text-slate-900">
                    Activity Log <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{logs.total.toLocaleString()}</span>
                </h1>
                <select className="input h-10 w-auto" value={f.user_id} onChange={(e) => apply({ ...f, user_id: e.target.value })} aria-label="Staff member">
                    <option value="">Everyone</option>
                    {staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
                <select className="input h-10 w-auto" value={f.type} onChange={(e) => apply({ ...f, type: e.target.value })} aria-label="Type">
                    <option value="">All types</option>
                    {types.map((t) => <option key={t} value={t}>{t.replace(/([a-z])([A-Z])/g, '$1 $2')}</option>)}
                </select>
                <SearchBox value={f.search} onChange={(v) => setF({ ...f, search: v })} onSubmit={() => apply(f)} placeholder="Search activity…" />
            </div>
            <div className="panel overflow-hidden">
                {logs.data.length === 0 ? (
                    <EmptyState icon="refresh" title="No activity yet" text="Changes made by staff in this panel are recorded here." />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {logs.data.map((l) => {
                            const [icon, tone] = ACTION[l.action] ?? ['info', 'bg-slate-100 text-slate-600'];
                            const changes = l.changes ? Object.entries(l.changes) : [];
                            return (
                                <li key={l.id} className="px-6 py-3.5">
                                    <div className="flex items-start gap-3">
                                        <span className={`mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full ${tone}`}><Icon name={icon} className="h-4 w-4" /></span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm text-slate-900"><b>{l.user?.name ?? 'Someone'}</b> · {l.description}</p>
                                            <p className="text-xs text-slate-500">{dateTime(l.created_at)}{l.ip && ` · ${l.ip}`}</p>
                                            {changes.length > 0 && (
                                                <>
                                                    <button type="button" onClick={() => setOpen(open === l.id ? null : l.id)} className="mt-1 text-xs font-medium text-primary-700 hover:underline">{open === l.id ? 'Hide' : 'Show'} changes ({changes.length})</button>
                                                    {open === l.id && (
                                                        <div className="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                                                            <table className="w-full text-xs">
                                                                <tbody>
                                                                    {changes.map(([k, [from, to]]) => (
                                                                        <tr key={k} className="border-t border-slate-100 first:border-0">
                                                                            <td className="whitespace-nowrap px-3 py-1.5 font-medium text-slate-700">{k.replace(/_/g, ' ')}</td>
                                                                            <td className="px-3 py-1.5 text-red-700 line-through decoration-red-300">{show(from)}</td>
                                                                            <td className="px-3 py-1.5 text-green-700">{show(to)}</td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <AdminPagination meta={logs} params={cleanParams(f)} />
            </div>
        </AdminLayout>
    );
}
