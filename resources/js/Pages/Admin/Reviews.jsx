import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, Tabs } from '../../Components/Admin/UI';
import AiDraftButton from '../../Components/Admin/AiDraftButton';
import Icon from '../../Components/Icon';
import Stars from '../../Components/Stars';
import AdminLayout from '../../Layouts/AdminLayout';
import { date } from '../../lib/format';

function ReplyForm({ r }) {
    const [open, setOpen] = useState(!!r.reply);
    const form = useForm({ reply: r.reply ?? '' });
    if (!open) {
        return (
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" onClick={() => setOpen(true)} className="btn-outline h-9 px-3"><Icon name="chat" className="h-4 w-4" /> Reply</button>
                <AiDraftButton url={`/admin/ai/reply/review/${r.id}`} onDraft={(text) => { form.setData('reply', text); setOpen(true); }} label="Reply with AI" />
            </div>
        );
    }
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.put(`/admin/reviews/${r.id}/reply`, { preserveScroll: true }); }} className="mt-3 space-y-2 rounded-lg border-l-2 border-primary-300 bg-slate-50 p-3">
            <p className="text-xs font-medium text-slate-500">Shop's reply (shown under the review)</p>
            <textarea className="input bg-white" rows={3} value={form.data.reply} onChange={(e) => form.setData('reply', e.target.value)} aria-label="Reply" />
            <div className="flex flex-wrap items-center gap-2">
                <button disabled={form.processing || (!r.reply && !form.data.reply.trim())} className="btn-primary h-9 px-3"><Icon name="check" className="h-4 w-4" /> {!form.data.reply.trim() && r.reply ? 'Remove reply' : r.reply ? 'Update reply' : 'Post reply'}</button>
                <AiDraftButton url={`/admin/ai/reply/review/${r.id}`} onDraft={(text) => form.setData('reply', text)} />
                {!r.reply && <button type="button" onClick={() => setOpen(false)} className="text-sm text-slate-500 hover:underline">Cancel</button>}
            </div>
        </form>
    );
}

export default function Reviews({ reviews, filters }) {
    const tab = filters.status ?? '';
    return (
        <AdminLayout title="Reviews" header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <h1 className="mr-auto flex items-center gap-2 text-xl font-semibold text-slate-900">
                    Product Reviews <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{reviews.total.toLocaleString()}</span>
                </h1>
                <Tabs value={tab} onChange={(v) => router.get('/admin/reviews', v ? { status: v } : {})} tabs={[{ value: '', label: 'All' }, { value: 'pending', label: 'Pending' }, { value: 'approved', label: 'Approved' }]} />
            </div>
            <div className="panel overflow-hidden">
                {reviews.data.length === 0 && <EmptyState icon="star" title="No reviews here" text="Customer reviews will appear here for approval." />}
                <ul className="divide-y divide-slate-100">
                    {reviews.data.map((r) => (
                        <li key={r.id} className="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-start">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary-100 font-semibold text-primary-700">{r.user?.name?.[0]?.toUpperCase()}</span>
                            <div className="min-w-0 flex-1 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <b className="text-slate-900">{r.user?.name}</b>
                                    <Stars value={r.rating} />
                                    <span className="text-xs text-slate-500">{date(r.created_at)}</span>
                                    {!r.is_approved && <span className="badge rounded-md bg-amber-50 text-amber-700 ring-1 ring-inset ring-current/20">Pending</span>}
                                </div>
                                <a href={`/product/${r.product?.slug}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-primary-700 hover:underline">{r.product?.name} <Icon name="external" className="h-3 w-3" /></a>
                                <p className="mt-1.5 text-slate-700">{r.comment}</p>
                                <ReplyForm key={`${r.id}-${r.replied_at}`} r={r} />
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <button onClick={() => router.patch(`/admin/reviews/${r.id}`, { is_approved: !r.is_approved }, { preserveScroll: true })} className={r.is_approved ? 'btn-outline h-9 px-3' : 'btn-primary h-9 px-3'}>
                                    <Icon name={r.is_approved ? 'x' : 'check'} className="h-4 w-4" /> {r.is_approved ? 'Unapprove' : 'Approve'}
                                </button>
                                <button onClick={() => confirm('Delete this review?') && router.delete(`/admin/reviews/${r.id}`, { preserveScroll: true })} className="icon-btn hover:border-red-300 hover:text-red-600" aria-label="Delete review"><Icon name="trash" className="h-4 w-4" /></button>
                            </div>
                        </li>
                    ))}
                </ul>
                <AdminPagination meta={reviews} params={filters} />
            </div>
        </AdminLayout>
    );
}
