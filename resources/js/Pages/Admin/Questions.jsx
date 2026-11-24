import { router, useForm } from '@inertiajs/react';
import { AdminPagination, EmptyState, Tabs } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { date } from '../../lib/format';

function AnswerForm({ q }) {
    const form = useForm({ answer: q.answer ?? '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.patch(`/admin/questions/${q.id}`, { preserveScroll: true }); }} className="mt-3 flex flex-col gap-2 sm:flex-row">
            <input className="input" placeholder="Write an answer…" value={form.data.answer} onChange={(e) => form.setData('answer', e.target.value)} aria-label="Answer" />
            <button disabled={form.processing || !form.data.answer.trim()} className="btn-primary shrink-0"><Icon name="check" className="h-4 w-4" /> {q.answer ? 'Update' : 'Answer'}</button>
        </form>
    );
}

export default function Questions({ questions, filters }) {
    const tab = filters.status ?? '';
    return (
        <AdminLayout title="Product Questions" header={false}>
            <div className="panel mb-4 flex flex-wrap items-center gap-3 px-5 py-4">
                <h1 className="mr-auto flex items-center gap-2 text-xl font-semibold text-slate-900">
                    Product Questions <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{questions.total.toLocaleString()}</span>
                </h1>
                <Tabs value={tab} onChange={(v) => router.get('/admin/questions', v ? { status: v } : {})} tabs={[{ value: '', label: 'All' }, { value: 'unanswered', label: 'Unanswered' }]} />
            </div>
            <div className="panel overflow-hidden">
                {questions.data.length === 0 && <EmptyState icon="chat" title="No questions here" text="Questions customers ask on product pages appear here." />}
                <ul className="divide-y divide-slate-100">
                    {questions.data.map((q) => (
                        <li key={q.id} className="px-6 py-5 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <b className="text-slate-900">{q.user?.name}</b>
                                <span className="text-xs text-slate-500">{date(q.created_at)}</span>
                                {!q.answer && <span className="badge rounded-md bg-amber-50 text-amber-700 ring-1 ring-inset ring-current/20">Unanswered</span>}
                                <button onClick={() => confirm('Delete this question?') && router.delete(`/admin/questions/${q.id}`, { preserveScroll: true })} className="icon-btn ml-auto h-8 w-8 hover:border-red-300 hover:text-red-600" aria-label="Delete question"><Icon name="trash" className="h-3.5 w-3.5" /></button>
                            </div>
                            <a href={`/product/${q.product?.slug}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-primary-700 hover:underline">{q.product?.name} <Icon name="external" className="h-3 w-3" /></a>
                            <p className="mt-2 font-medium text-slate-900">Q: {q.question}</p>
                            <AnswerForm q={q} />
                        </li>
                    ))}
                </ul>
                <AdminPagination meta={questions} params={filters} />
            </div>
        </AdminLayout>
    );
}
