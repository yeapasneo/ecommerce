import { router, useForm } from '@inertiajs/react';
import Pagination from '../../Components/Pagination';
import AdminLayout from '../../Layouts/AdminLayout';
import { date } from '../../lib/format';

function AnswerForm({ q }) {
    const form = useForm({ answer: q.answer ?? '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.patch(`/admin/questions/${q.id}`, { preserveScroll: true }); }} className="mt-2 flex flex-col gap-2 sm:flex-row">
            <input className="input" placeholder="Write an answer…" value={form.data.answer} onChange={(e) => form.setData('answer', e.target.value)} aria-label="Answer" />
            <button disabled={form.processing} className="btn-primary btn-sm shrink-0">{q.answer ? 'Update' : 'Answer'}</button>
        </form>
    );
}

export default function Questions({ questions, filters }) {
    const tab = filters.status ?? '';
    return (
        <AdminLayout title="Product Questions">
            <div className="mb-4 flex gap-2">
                {[['', 'All'], ['unanswered', 'Unanswered']].map(([v, l]) => (
                    <button key={v} onClick={() => router.get('/admin/questions', v ? { status: v } : {})} className={`rounded-full px-3 py-1 text-sm ${tab === v ? 'bg-brand-700 text-white' : 'bg-white ring-1 ring-slate-200'}`}>{l}</button>
                ))}
            </div>
            <div className="space-y-3">
                {questions.data.map((q) => (
                    <div key={q.id} className="card p-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <b>{q.user?.name}</b> <span className="text-xs text-slate-400">{date(q.created_at)}</span>
                            <a href={`/product/${q.product?.slug}`} target="_blank" rel="noreferrer" className="text-xs text-brand-600 hover:underline">{q.product?.name}</a>
                            <button onClick={() => confirm('Delete this question?') && router.delete(`/admin/questions/${q.id}`, { preserveScroll: true })} className="ml-auto text-xs text-red-600 hover:underline">Delete</button>
                        </div>
                        <p className="mt-1 font-medium">Q: {q.question}</p>
                        <AnswerForm q={q} />
                    </div>
                ))}
                {questions.data.length === 0 && <div className="card p-10 text-center text-slate-500">No questions.</div>}
            </div>
            <Pagination meta={questions} />
        </AdminLayout>
    );
}
