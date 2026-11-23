import { router } from '@inertiajs/react';
import Pagination from '../../Components/Pagination';
import Stars from '../../Components/Stars';
import AdminLayout from '../../Layouts/AdminLayout';
import { date } from '../../lib/format';

export default function Reviews({ reviews, filters }) {
    const tab = filters.status ?? '';
    return (
        <AdminLayout title="Reviews">
            <div className="mb-4 flex gap-2">
                {[['', 'All'], ['pending', 'Pending'], ['approved', 'Approved']].map(([v, l]) => (
                    <button key={v} onClick={() => router.get('/admin/reviews', v ? { status: v } : {})} className={`rounded-full px-3 py-1 text-sm ${tab === v ? 'bg-brand-700 text-white' : 'bg-white ring-1 ring-slate-200'}`}>{l}</button>
                ))}
            </div>
            <div className="space-y-3">
                {reviews.data.map((r) => (
                    <div key={r.id} className="card flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
                        <div className="flex-1 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <Stars value={r.rating} /> <b>{r.user?.name}</b> <span className="text-xs text-slate-400">{date(r.created_at)}</span>
                                {!r.is_approved && <span className="badge bg-amber-100 text-amber-800">Pending</span>}
                            </div>
                            <a href={`/product/${r.product?.slug}`} target="_blank" rel="noreferrer" className="text-xs text-brand-600 hover:underline">{r.product?.name}</a>
                            <p className="mt-1 text-slate-700">{r.comment}</p>
                        </div>
                        <div className="flex gap-2">
                            <button onClick={() => router.patch(`/admin/reviews/${r.id}`, { is_approved: !r.is_approved }, { preserveScroll: true })} className={r.is_approved ? 'btn-outline btn-sm' : 'btn-primary btn-sm'}>{r.is_approved ? 'Unapprove' : 'Approve'}</button>
                            <button onClick={() => confirm('Delete this review?') && router.delete(`/admin/reviews/${r.id}`, { preserveScroll: true })} className="btn-outline btn-sm text-red-600">Delete</button>
                        </div>
                    </div>
                ))}
                {reviews.data.length === 0 && <div className="card p-10 text-center text-slate-500">No reviews.</div>}
            </div>
            <Pagination meta={reviews} />
        </AdminLayout>
    );
}
