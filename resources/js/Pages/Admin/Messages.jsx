import { router } from '@inertiajs/react';
import { AdminPagination, EmptyState, Panel } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { dateTime } from '../../lib/format';

export default function Messages({ messages, subscribers }) {
    return (
        <AdminLayout title="Messages" count={messages.total} subtitle="Contact form messages and newsletter subscribers">
            <div className="grid items-start gap-6 xl:grid-cols-[1fr_360px]">
                <div className="panel overflow-hidden">
                    {messages.data.length === 0 && <EmptyState icon="mail" title="No messages yet" text="Messages sent from the contact page appear here." />}
                    <ul className="divide-y divide-slate-100">
                        {messages.data.map((m) => (
                            <li key={m.id} className={`relative px-6 py-5 text-sm ${m.is_read ? '' : 'bg-primary-50/40'}`}>
                                {!m.is_read && <span className="absolute left-2.5 top-7 h-2 w-2 rounded-full bg-primary-600" aria-label="Unread" />}
                                <div className="flex flex-wrap items-center gap-2">
                                    <b className="text-slate-900">{m.subject}</b>
                                    {!m.is_read && <span className="badge rounded-md bg-primary-50 text-primary-700 ring-1 ring-inset ring-current/20">New</span>}
                                    <span className="ml-auto text-xs text-slate-500">{dateTime(m.created_at)}</span>
                                </div>
                                <p className="mt-0.5 text-xs text-slate-500">{m.name} · <a href={`mailto:${m.email}`} className="text-primary-700 hover:underline">{m.email}</a>{m.phone && ` · ${m.phone}`}</p>
                                <p className="mt-2 whitespace-pre-line text-slate-700">{m.message}</p>
                                <div className="mt-3 flex gap-2">
                                    <a href={`mailto:${m.email}?subject=${encodeURIComponent('Re: ' + m.subject)}`} className="btn-outline h-9 px-3"><Icon name="mail" className="h-4 w-4" /> Reply</a>
                                    {!m.is_read && <button onClick={() => router.patch(`/admin/messages/${m.id}`, {}, { preserveScroll: true })} className="btn-outline h-9 px-3"><Icon name="check" className="h-4 w-4" /> Mark as read</button>}
                                    <button onClick={() => confirm('Delete this message?') && router.delete(`/admin/messages/${m.id}`, { preserveScroll: true })} className="icon-btn hover:border-red-300 hover:text-red-600" aria-label="Delete message"><Icon name="trash" className="h-4 w-4" /></button>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <AdminPagination meta={messages} />
                </div>
                <Panel title="Newsletter Subscribers" actions={<span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{subscribers.length}</span>}>
                    {subscribers.length === 0 ? <p className="text-sm text-slate-500">No subscribers yet.</p> : (
                        <ul className="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto text-sm">
                            {subscribers.map((s) => <li key={s.id} className="flex items-center gap-2 truncate py-2"><Icon name="mail" className="h-4 w-4 shrink-0 text-slate-400" /> {s.email}</li>)}
                        </ul>
                    )}
                </Panel>
            </div>
        </AdminLayout>
    );
}
