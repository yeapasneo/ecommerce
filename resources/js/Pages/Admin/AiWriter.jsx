import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPagination, EmptyState, Tabs } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';
import { dateTime } from '../../lib/format';

const FIELDS = [
    ['meta_title', 'SEO title', 'text'],
    ['meta_description', 'SEO description', 'text'],
    ['key_features', 'Key features', 'list'],
    ['description', 'Description', 'html'],
    ['key_features_bn', 'Key features (Bangla)', 'list'],
    ['description_bn', 'Description (Bangla)', 'html'],
];

function Value({ type, value }) {
    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) return <span className="text-slate-400">—</span>;
    if (type === 'list') return <ul className="list-disc space-y-0.5 pl-4">{value.map((v, i) => <li key={i}>{v}</li>)}</ul>;
    if (type === 'html') return <div className="prose-sm space-y-2 [&_li]:ml-4 [&_li]:list-disc [&_h3]:font-semibold" dangerouslySetInnerHTML={{ __html: value }} />;
    return <span>{value}</span>;
}

function Draft({ draft }) {
    const [editing, setEditing] = useState(false);
    const [fields, setFields] = useState(draft.fields ?? {});
    const [busy, setBusy] = useState(false);
    const p = draft.product;
    const post = (url, data = {}) => router.post(url, data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    const setField = (k, v) => setFields((f) => ({ ...f, [k]: v }));

    return (
        <article className="panel overflow-hidden">
            <header className="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3">
                {p?.image_url && <img src={p.image_url} alt="" className="h-10 w-10 rounded-lg border border-slate-100 object-contain" />}
                <div className="min-w-0 flex-1">
                    <Link href={`/admin/products/${draft.product_id}/edit`} className="font-medium text-slate-900 hover:text-primary-700">{p?.name ?? 'Deleted product'}</Link>
                    <p className="text-xs text-slate-500">
                        {dateTime(draft.updated_at)}
                        {draft.reviewer && ` · ${draft.status === 'approved' ? 'published' : 'reviewed'} by ${draft.reviewer.name}`}
                    </p>
                </div>
                {draft.status === 'pending' && draft.fields && (
                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => setEditing(!editing)} className="btn-outline btn-sm"><Icon name="edit" className="h-4 w-4" /> {editing ? 'Preview' : 'Edit'}</button>
                        <button type="button" disabled={busy} onClick={() => post(`/admin/ai-writer/${draft.id}/reject`)} className="btn-cancel btn-sm rounded-lg"><Icon name="x" className="h-4 w-4" /> Reject</button>
                        <button type="button" disabled={busy} onClick={() => post(`/admin/ai-writer/${draft.id}/approve`, { fields })} className="btn-primary btn-sm"><Icon name="check" className="h-4 w-4" /> Publish</button>
                    </div>
                )}
                {['failed', 'rejected'].includes(draft.status) && (
                    <button type="button" disabled={busy} onClick={() => post(`/admin/ai-writer/${draft.id}/retry`)} className="btn-outline btn-sm"><Icon name="refresh" className="h-4 w-4" /> {busy ? 'Writing…' : 'Write again'}</button>
                )}
            </header>
            {draft.error && <p className="border-b border-red-100 bg-red-50 px-5 py-2 text-sm text-red-700">{draft.error}</p>}
            {draft.fields && (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th className="w-40 px-5 py-2 font-medium" />
                                <th className="px-3 py-2 font-medium">Now</th>
                                <th className="px-3 py-2 font-medium text-primary-700">Written by AI</th>
                            </tr>
                        </thead>
                        <tbody>
                            {FIELDS.map(([k, label, type]) => (
                                <tr key={k} className="border-t border-slate-100 align-top">
                                    <td className="px-5 py-3 font-medium text-slate-700">{label}</td>
                                    <td className="w-[40%] px-3 py-3 text-slate-500"><Value type={type} value={draft.status === 'approved' ? null : p?.[k]} /></td>
                                    <td className="w-[45%] px-3 py-3 text-slate-800">
                                        {editing ? (
                                            type === 'list' ? (
                                                <textarea className="input min-h-[110px] font-normal" value={(fields[k] ?? []).join('\n')} onChange={(e) => setField(k, e.target.value.split('\n'))} aria-label={label} />
                                            ) : (
                                                <textarea className={`input ${type === 'html' ? 'min-h-[180px] font-mono text-xs' : 'min-h-[60px]'}`} value={fields[k] ?? ''} onChange={(e) => setField(k, e.target.value)} aria-label={label} />
                                            )
                                        ) : (
                                            <Value type={type} value={fields[k]} />
                                        )}
                                        {!editing && k === 'meta_title' && fields[k] && <p className={`mt-1 text-xs ${fields[k].length > 60 ? 'text-amber-600' : 'text-slate-400'}`}>{fields[k].length}/60</p>}
                                        {!editing && k === 'meta_description' && fields[k] && <p className={`mt-1 text-xs ${fields[k].length > 160 ? 'text-amber-600' : 'text-slate-400'}`}>{fields[k].length}/160</p>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </article>
    );
}

export default function AiWriter({ drafts, status, counts, needing, ai }) {
    const [count, setCount] = useState(10);
    const [busy, setBusy] = useState(false);
    const go = (s) => router.get('/admin/ai-writer', { status: s }, { preserveScroll: true });
    const queue = (e) => {
        e.preventDefault();
        router.post('/admin/ai-writer/queue', { count }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };
    const approveAll = () => confirm(`Publish all ${counts.pending ?? 0} pending texts without reviewing them one by one?`) && router.post('/admin/ai-writer/approve-all', {}, { preserveScroll: true });

    const stats = [
        ['Need a better text', needing.toLocaleString(), 'file'],
        ['Waiting for review', (counts.pending ?? 0).toLocaleString(), 'clock'],
        ['Published', (counts.approved ?? 0).toLocaleString(), 'check'],
        ['AI requests today', `${ai.usedToday.toLocaleString()}${ai.limit ? ` / ${ai.limit.toLocaleString()}` : ''}`, 'sparkle'],
    ];

    return (
        <AdminLayout
            title="AI Writer"
            subtitle="Product descriptions, key features, SEO and Bangla text written by AI from each product's specs. Nothing goes live until you publish it."
            actions={counts.pending > 0 && <button type="button" onClick={approveAll} className="btn-outline"><Icon name="check" className="h-4 w-4" /> Publish all</button>}
        >
            {!ai.configured && (
                <div className="panel mb-6 flex flex-wrap items-center gap-3 border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                    <Icon name="info" className="h-5 w-5 shrink-0" />
                    <p className="flex-1">AI isn't set up yet. Add a free Gemini key (or Groq / OpenRouter) in Settings, then come back here.</p>
                    <Link href="/admin/settings#ai" className="btn-primary btn-sm">Open AI settings</Link>
                </div>
            )}

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map(([label, value, icon]) => (
                    <div key={label} className="panel flex items-center gap-3 p-5">
                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700"><Icon name={icon} className="h-5 w-5" /></span>
                        <div>
                            <p className="text-sm text-slate-500">{label}</p>
                            <p className="text-xl font-bold tabular-nums text-slate-900">{value}</p>
                        </div>
                    </div>
                ))}
            </div>

            <form onSubmit={queue} className="panel mb-6 flex flex-wrap items-center gap-3 px-5 py-4">
                <Icon name="sparkle" className="h-5 w-5 text-primary-600" />
                <span className="text-sm text-slate-700">Write texts for the next</span>
                <input type="number" min={1} max={100} value={count} onChange={(e) => setCount(e.target.value)} className="input h-9 w-20 py-1" aria-label="Number of products" />
                <span className="text-sm text-slate-700">products (best sellers first)</span>
                <button disabled={busy || !ai.configured || needing === 0} className="btn-primary h-9 py-1">{busy ? 'Starting…' : 'Start writing'}</button>
                <p className="w-full text-xs text-slate-500 sm:ml-auto sm:w-auto">
                    {ai.auto ? `Also writing ${ai.perHour} per hour automatically.` : 'Automatic writing is off (Settings → AI).'} Runs in the background through the queue worker.
                </p>
            </form>

            {ai.errorsToday > 0 && ai.lastError && <p className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{ai.errorsToday} AI request(s) failed today. Last error: {ai.lastError}</p>}

            <div className="panel mb-4 px-4 py-3">
                <Tabs
                    tabs={[
                        { value: 'pending', label: 'Waiting for review', count: counts.pending ?? 0 },
                        { value: 'approved', label: 'Published', count: counts.approved ?? 0 },
                        { value: 'rejected', label: 'Rejected', count: counts.rejected ?? 0 },
                        { value: 'failed', label: 'Failed', count: counts.failed ?? 0 },
                    ]}
                    value={status}
                    onChange={go}
                />
            </div>

            {drafts.data.length === 0 ? (
                <div className="panel"><EmptyState icon="sparkle" title="Nothing here yet" text={status === 'pending' ? 'Start writing above. New texts appear here for review.' : 'No texts in this list.'} /></div>
            ) : (
                <div className="space-y-4">
                    {drafts.data.map((d) => <Draft key={`${d.id}-${d.updated_at}`} draft={d} />)}
                </div>
            )}
            <div className="panel mt-4 overflow-hidden"><AdminPagination meta={drafts} params={{ status }} /></div>
        </AdminLayout>
    );
}
