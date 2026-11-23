import { router } from '@inertiajs/react';
import Pagination from '../../Components/Pagination';
import AdminLayout from '../../Layouts/AdminLayout';
import { dateTime } from '../../lib/format';

export default function Messages({ messages, subscribers }) {
    return (
        <AdminLayout title="Messages">
            <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
                <div>
                    <div className="space-y-3">
                        {messages.data.map((m) => (
                            <div key={m.id} className={`card p-4 text-sm ${m.is_read ? '' : 'ring-2 ring-accent-400'}`}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <b>{m.subject}</b>
                                    {!m.is_read && <span className="badge bg-orange-100 text-orange-800">New</span>}
                                    <span className="ml-auto text-xs text-slate-400">{dateTime(m.created_at)}</span>
                                </div>
                                <p className="text-xs text-slate-500">{m.name} · <a href={`mailto:${m.email}`} className="text-brand-600 hover:underline">{m.email}</a>{m.phone && ` · ${m.phone}`}</p>
                                <p className="mt-2 whitespace-pre-line text-slate-700">{m.message}</p>
                                <div className="mt-3 flex gap-2">
                                    {!m.is_read && <button onClick={() => router.patch(`/admin/messages/${m.id}`, {}, { preserveScroll: true })} className="btn-outline btn-sm">Mark as read</button>}
                                    <button onClick={() => confirm('Delete this message?') && router.delete(`/admin/messages/${m.id}`, { preserveScroll: true })} className="btn-outline btn-sm text-red-600">Delete</button>
                                </div>
                            </div>
                        ))}
                        {messages.data.length === 0 && <div className="card p-10 text-center text-slate-500">No messages yet.</div>}
                    </div>
                    <Pagination meta={messages} />
                </div>
                <div className="card h-fit p-5">
                    <h2 className="mb-3 font-semibold">Newsletter Subscribers ({subscribers.length})</h2>
                    <ul className="max-h-96 space-y-1 overflow-y-auto text-sm">
                        {subscribers.map((s) => <li key={s.id} className="truncate">{s.email}</li>)}
                        {subscribers.length === 0 && <li className="text-slate-500">No subscribers yet.</li>}
                    </ul>
                </div>
            </div>
        </AdminLayout>
    );
}
