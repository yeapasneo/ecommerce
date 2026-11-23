import { Link } from '@inertiajs/react';

export default function Pagination({ meta }) {
    const links = meta?.links ?? [];
    if (!meta || meta.last_page <= 1) return null;
    return (
        <nav className="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
            <p className="text-slate-500">
                Showing {meta.from ?? 0} to {meta.to ?? 0} of {meta.total} ({meta.last_page} pages)
            </p>
            <div className="flex flex-wrap gap-1">
                {links.map((l, i) =>
                    l.url ? (
                        <Link
                            key={i}
                            href={l.url}
                            preserveScroll={false}
                            className={`min-w-9 rounded border px-3 py-1.5 text-center ${l.active ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 bg-white hover:border-accent-500 hover:text-accent-600'}`}
                            dangerouslySetInnerHTML={{ __html: l.label }}
                        />
                    ) : (
                        <span key={i} className="min-w-9 rounded border border-slate-200 px-3 py-1.5 text-center text-slate-300" dangerouslySetInnerHTML={{ __html: l.label }} />
                    ),
                )}
            </div>
        </nav>
    );
}
