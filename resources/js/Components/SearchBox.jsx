import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Icon from './Icon';
import { money } from '../lib/format';
import { t } from '../lib/i18n';

export default function SearchBox({ className = '' }) {
    const initial = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('q') ?? '' : '';
    const [q, setQ] = useState(initial);
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const box = useRef(null);

    useEffect(() => {
        if (q.trim().length < 2) {
            setResults([]);
            return;
        }
        const ctrl = new AbortController();
        const t = setTimeout(() => {
            fetch(`/search/suggest?q=${encodeURIComponent(q)}`, { signal: ctrl.signal, headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then(setResults)
                .catch(() => {});
        }, 250);
        return () => {
            clearTimeout(t);
            ctrl.abort();
        };
    }, [q]);

    useEffect(() => {
        const close = (e) => box.current && !box.current.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const submit = (e) => {
        e.preventDefault();
        setOpen(false);
        router.get('/search', { q });
    };

    return (
        <form onSubmit={submit} ref={box} className={`relative ${className}`} role="search">
            <input
                value={q}
                onChange={(e) => {
                    setQ(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                placeholder={t('Search products...')}
                aria-label={t('Search products')}
                className="w-full rounded-md border-0 bg-white py-2.5 pl-4 pr-11 text-sm text-slate-800 focus:ring-2 focus:ring-accent-400"
            />
            <button type="submit" className="absolute right-1 top-1/2 -translate-y-1/2 rounded p-1.5 text-slate-500 hover:text-accent-600" aria-label={t('Search')}>
                <Icon name="search" />
            </button>
            {open && results.length > 0 && (
                <div className="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md bg-white text-slate-800 shadow-xl ring-1 ring-slate-200">
                    {results.map((r) => (
                        <Link key={r.slug} href={`/product/${r.slug}`} onClick={() => setOpen(false)} className="flex items-center gap-3 border-b border-slate-100 px-3 py-2 hover:bg-slate-50">
                            {r.image_url && <img src={r.image_url} alt="" className="h-10 w-10 rounded object-cover" />}
                            <span className="flex-1 text-sm">{r.name}</span>
                            <span className="text-sm font-semibold text-accent-600">{money(r.price)}</span>
                        </Link>
                    ))}
                    <button type="submit" className="w-full bg-slate-50 py-2 text-center text-sm font-medium text-brand-600 hover:underline">{t('See all results')}</button>
                </div>
            )}
        </form>
    );
}
