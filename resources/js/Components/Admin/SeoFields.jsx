import { usePage } from '@inertiajs/react';
import Icon from '../Icon';
import { Panel } from './UI';

export const slugify = (s) =>
    String(s ?? '')
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120);

const clip = (s, max) => {
    s = String(s ?? '').replace(/\s+/g, ' ').trim();
    if (s.length <= max) return s;
    const cut = s.slice(0, max - 1);
    return cut.slice(0, cut.lastIndexOf(' ') > max * 0.6 ? cut.lastIndexOf(' ') : cut.length).replace(/[,.;:\s-]+$/, '') + '…';
};

function Counter({ value, min, max }) {
    const n = value.length;
    const tone = n === 0 ? 'text-slate-400' : n > max ? 'text-red-600' : n < min ? 'text-amber-600' : 'text-green-600';
    return <span className={`text-xs font-medium ${tone}`}>{n} / {max}</span>;
}

/**
 * Search engine fields with a live Google preview, length guidance and a checklist.
 * `auto` holds what the storefront uses when a field is left blank.
 */
export default function SeoFields({ form, path, auto, generate, checks = [] }) {
    const { settings } = usePage().props;
    const site = settings?.site_name ?? '';
    const e = form.errors;
    const d = form.data;

    const title = d.meta_title || auto.title;
    const fullTitle = title.includes(site) ? title : `${title} | ${site}`;
    const description = d.meta_description || auto.description;
    const slug = d.slug || slugify(auto.slug);
    const host = typeof window !== 'undefined' ? window.location.host : '';

    const allChecks = [
        { ok: fullTitle.length >= 30 && fullTitle.length <= 65, label: 'Title is 30–65 characters (incl. store name)' },
        { ok: description.length >= 70 && description.length <= 160, label: 'Description is 70–160 characters' },
        { ok: slug.length > 0 && slug.length <= 80, label: 'Short, readable URL' },
        ...checks,
    ];
    const score = Math.round((allChecks.filter((c) => c.ok).length / allChecks.length) * 100);
    const scoreTone = score >= 80 ? 'bg-green-500' : score >= 50 ? 'bg-amber-500' : 'bg-red-500';

    const fill = () => {
        const g = generate();
        form.setData((cur) => ({
            ...cur,
            meta_title: clip(g.title, 60),
            meta_description: clip(g.description, 160),
            meta_keywords: g.keywords ?? cur.meta_keywords,
            slug: cur.slug || slugify(auto.slug),
        }));
    };

    return (
        <Panel
            title="Search Engine Optimization"
            icon="search"
            actions={
                <button type="button" onClick={fill} className="btn-outline h-9 px-3 text-sm">
                    <Icon name="refresh" className="h-4 w-4" /> Generate from product
                </button>
            }
        >
            <div className="mb-5 rounded-xl border border-slate-200 bg-white p-4">
                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Google preview</p>
                <p className="truncate text-xs text-slate-600">{host}{path}{slug || 'your-product-url'}</p>
                <p className="truncate text-lg leading-snug text-[#1a0dab]">{fullTitle || 'Product title'}</p>
                <p className="line-clamp-2 text-sm text-slate-600">{description || 'Add a description to show in search results.'}</p>
            </div>

            <div className="grid gap-4">
                <div>
                    <div className="mb-1 flex items-center justify-between">
                        <label className="label mb-0" htmlFor="seo-title">Meta title</label>
                        <Counter value={d.meta_title ?? ''} min={30} max={60} />
                    </div>
                    <input id="seo-title" className="input" value={d.meta_title ?? ''} onChange={(ev) => form.setData('meta_title', ev.target.value)} placeholder={auto.title} />
                    {e.meta_title ? <p className="mt-1 text-xs text-red-600">{e.meta_title}</p> : <p className="mt-1 text-xs text-slate-500">Blank uses "{clip(auto.title, 70)}".</p>}
                </div>
                <div>
                    <div className="mb-1 flex items-center justify-between">
                        <label className="label mb-0" htmlFor="seo-description">Meta description</label>
                        <Counter value={d.meta_description ?? ''} min={70} max={160} />
                    </div>
                    <textarea id="seo-description" className="input" rows={3} value={d.meta_description ?? ''} onChange={(ev) => form.setData('meta_description', ev.target.value)} placeholder={auto.description} />
                    {e.meta_description && <p className="mt-1 text-xs text-red-600">{e.meta_description}</p>}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="label" htmlFor="seo-slug">URL slug</label>
                        <div className="flex rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100">
                            <span className="hidden shrink-0 items-center rounded-l-lg bg-slate-50 px-3 text-xs text-slate-500 sm:flex">{path}</span>
                            <input id="seo-slug" className="w-full min-w-0 rounded-r-lg px-3 py-2 text-sm outline-none" value={d.slug ?? ''} onChange={(ev) => form.setData('slug', ev.target.value)} onBlur={(ev) => ev.target.value && form.setData('slug', slugify(ev.target.value))} placeholder={slugify(auto.slug)} />
                        </div>
                        {e.slug ? <p className="mt-1 text-xs text-red-600">{e.slug}</p> : <p className="mt-1 text-xs text-slate-500">Changing it on a live product breaks old links.</p>}
                    </div>
                    <div>
                        <label className="label" htmlFor="seo-keywords">Keywords</label>
                        <input id="seo-keywords" className="input" value={d.meta_keywords ?? ''} onChange={(ev) => form.setData('meta_keywords', ev.target.value)} placeholder="e.g. ryzen 5 7600, amd processor" />
                        {e.meta_keywords ? <p className="mt-1 text-xs text-red-600">{e.meta_keywords}</p> : <p className="mt-1 text-xs text-slate-500">Comma separated</p>}
                    </div>
                </div>
            </div>

            <div className="mt-6 rounded-xl border border-slate-200 p-4">
                <div className="mb-3 flex items-center gap-3">
                    <p className="text-sm font-semibold text-slate-900">SEO score</p>
                    <div className="h-2 flex-1 rounded-full bg-slate-100"><div className={`h-2 rounded-full transition-all ${scoreTone}`} style={{ width: `${score}%` }} /></div>
                    <span className="text-sm font-semibold text-slate-900">{score}%</span>
                </div>
                <ul className="grid gap-1.5 sm:grid-cols-2">
                    {allChecks.map((c) => (
                        <li key={c.label} className="flex items-start gap-2 text-sm">
                            <span className={`mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full ${c.ok ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-400'}`}>
                                <Icon name={c.ok ? 'check' : 'minus'} className="h-3 w-3" strokeWidth={3} />
                            </span>
                            <span className={c.ok ? 'text-slate-700' : 'text-slate-500'}>{c.label}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </Panel>
    );
}
