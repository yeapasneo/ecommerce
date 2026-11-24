import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cleanParams } from '../../lib/format';
import Icon from '../Icon';

/** Page title row: optional back arrow, title, count badge and actions on the right. */
export function PageHeader({ title, back, count, subtitle, children }) {
    return (
        <div className="no-print mb-6 flex flex-wrap items-center gap-3">
            {back && (
                <Link href={back} className="-ml-1 grid h-9 w-9 place-items-center rounded-lg text-slate-700 hover:bg-white" aria-label="Back">
                    <Icon name="arrowLeft" className="h-5 w-5" />
                </Link>
            )}
            <div className="mr-auto min-w-0">
                <h1 className="flex items-center gap-2 truncate text-xl font-semibold text-slate-900">
                    {title}
                    {count !== undefined && <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{Number(count).toLocaleString()}</span>}
                </h1>
                {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
            </div>
            {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
        </div>
    );
}

/** White card with a title and a chevron that collapses the body. */
export function Panel({ title, icon, actions, collapsible = true, defaultOpen = true, className = '', bodyClassName = 'px-6 pb-6', children }) {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <section className={`panel ${className}`}>
            {title && (
                <div className="flex items-center gap-3 px-6 py-5">
                    {icon && <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700"><Icon name={icon} className="h-4 w-4" /></span>}
                    <h2 className="mr-auto text-lg font-semibold text-slate-900">{title}</h2>
                    {actions}
                    {collapsible && (
                        <button type="button" onClick={() => setOpen(!open)} className="grid h-8 w-8 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label={open ? `Collapse ${title}` : `Expand ${title}`} aria-expanded={open}>
                            <Icon name={open ? 'chevronUp' : 'chevronDown'} className="h-4 w-4" />
                        </button>
                    )}
                </div>
            )}
            {open && <div className={bodyClassName}>{children}</div>}
        </section>
    );
}

/** Small bordered box inside a panel (e.g. Discount, Delivery). */
export function SubCard({ title, icon, tone = 'primary', children, className = '' }) {
    const tones = { primary: 'bg-primary-50 text-primary-700', orange: 'bg-orange-50 text-orange-600', green: 'bg-green-50 text-green-600', blue: 'bg-blue-50 text-blue-600' };
    return (
        <div className={`rounded-xl border border-slate-200 p-4 ${className}`}>
            {title && (
                <div className="mb-3 flex items-center gap-2.5">
                    {icon && <span className={`grid h-7 w-7 place-items-center rounded-full ${tones[tone]}`}><Icon name={icon} className="h-3.5 w-3.5" /></span>}
                    <h3 className="font-semibold text-slate-900">{title}</h3>
                </div>
            )}
            {children}
        </div>
    );
}

export function EmptyState({ icon = 'plus', title, text, action, dashed = false }) {
    return (
        <div className={`flex flex-col items-center px-6 py-14 text-center ${dashed ? 'rounded-xl border border-dashed border-slate-300 bg-slate-50/60' : ''}`}>
            <span className="mb-5 grid h-20 w-20 place-items-center rounded-full bg-slate-100 text-slate-500"><Icon name={icon} className="h-8 w-8" strokeWidth={1.5} /></span>
            <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
            {text && <p className="mt-1 max-w-sm text-sm text-slate-500">{text}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

/** Pill tabs; the active one gets a purple outline. */
export function Tabs({ tabs, value, onChange }) {
    return (
        <div className="-mb-1 flex max-w-full gap-2 overflow-x-auto pb-1">
            {tabs.map((t) => (
                <button
                    key={t.value}
                    type="button"
                    onClick={() => onChange(t.value)}
                    className={`inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg border px-4 py-2 text-sm font-medium transition ${value === t.value ? 'border-primary-600 bg-primary-50 text-primary-700 ring-1 ring-primary-600' : 'border-slate-200 bg-white text-slate-600 hover:border-primary-300'}`}
                >
                    {t.label}
                    {t.count !== undefined && <span className={`rounded-full px-1.5 text-xs ${value === t.value ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-500'}`}>{Number(t.count).toLocaleString()}</span>}
                </button>
            ))}
        </div>
    );
}

function useOutsideClose(open, setOpen) {
    const ref = useRef(null);
    useEffect(() => {
        if (!open) return;
        const onDown = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, setOpen]);
    return ref;
}

/**
 * Button that opens a floating panel (filters, user menu…).
 * `floating` positions the panel against the viewport so scrolling tables don't clip it.
 */
export function Dropdown({ button, children, align = 'right', width = 'w-64', floating = false }) {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState(null);
    const ref = useOutsideClose(open, setOpen);

    useEffect(() => {
        if (!open || !floating) return;
        const close = () => setOpen(false);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);
        return () => {
            window.removeEventListener('scroll', close, true);
            window.removeEventListener('resize', close);
        };
    }, [open, floating]);

    const toggle = () => {
        if (!open && floating && ref.current) {
            const r = ref.current.getBoundingClientRect();
            const below = window.innerHeight - r.bottom > 200;
            setPos({ right: window.innerWidth - r.right, ...(below ? { top: r.bottom + 6 } : { bottom: window.innerHeight - r.top + 6 }) });
        }
        setOpen(!open);
    };

    return (
        <div className="relative" ref={ref}>
            {button({ open, toggle })}
            {open && (
                <div
                    className={`z-50 ${width} rounded-xl border border-slate-200 bg-white p-2 shadow-lg ${floating ? 'fixed' : `absolute mt-2 ${align === 'right' ? 'right-0' : 'left-0'}`}`}
                    style={floating ? pos : undefined}
                    onClick={(e) => e.target.closest('[data-close]') && setOpen(false)}
                >
                    {children}
                </div>
            )}
        </div>
    );
}

/** "⋮" button with row actions: [{ label, icon, href, external, onClick, danger }]. */
export function RowMenu({ items, label = 'Row actions' }) {
    return (
        <Dropdown
            width="w-44"
            floating
            button={({ toggle }) => (
                <button type="button" onClick={toggle} className="icon-btn" aria-label={label}><Icon name="dots" className="h-4 w-4" /></button>
            )}
        >
            {items.filter(Boolean).map((it) => {
                const cls = `flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm ${it.danger ? 'text-red-600 hover:bg-red-50' : 'text-slate-700 hover:bg-slate-50'}`;
                const body = <>{it.icon && <Icon name={it.icon} className="h-4 w-4" />}{it.label}</>;
                if (it.external) return <a key={it.label} href={it.href} target="_blank" rel="noreferrer" className={cls} data-close>{body}</a>;
                if (it.href) return <Link key={it.label} href={it.href} className={cls} data-close>{body}</Link>;
                return <button key={it.label} type="button" onClick={it.onClick} className={cls} data-close>{body}</button>;
            })}
        </Dropdown>
    );
}

/** Search icon that expands into an input; submits on Enter. */
export function SearchBox({ value, onChange, onSubmit, placeholder = 'Search…' }) {
    const [open, setOpen] = useState(!!value);
    if (!open) {
        return <button type="button" onClick={() => setOpen(true)} className="icon-btn h-10 w-10" aria-label="Search"><Icon name="search" className="h-4 w-4" /></button>;
    }
    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit(); }} className="relative">
            <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input autoFocus className="input h-10 w-56 rounded-lg pl-9 sm:w-72" placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} aria-label="Search" />
        </form>
    );
}

/** "Showing 1–20 of 300 · [20] per page" on the left, Previous / pages / Next on the right. */
export function AdminPagination({ meta, params = {} }) {
    if (!meta || !meta.total) return null;
    const path = meta.path;
    const go = (extra) => router.get(path, cleanParams({ ...params, ...extra }), { preserveState: true, preserveScroll: false });
    const pages = (meta.links ?? []).slice(1, -1);
    const navCls = 'inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm';
    return (
        <div className="no-print flex flex-wrap items-center justify-between gap-3 px-4 py-4 text-sm text-slate-600">
            <div className="flex items-center gap-2">
                <span>Showing {meta.from}–{meta.to} of {meta.total.toLocaleString()}</span>
                <select className="input h-9 w-auto rounded-lg py-1" value={meta.per_page} onChange={(e) => go({ per_page: e.target.value, page: undefined })} aria-label="Rows per page">
                    {[10, 20, 50, 100].map((n) => <option key={n} value={n}>{n}</option>)}
                </select>
                <span>per page</span>
            </div>
            {meta.last_page > 1 && (
                <nav className="flex flex-wrap items-center gap-1" aria-label="Pagination">
                    {meta.prev_page_url ? <Link href={meta.prev_page_url} className={`${navCls} hover:bg-slate-100`}><Icon name="chevronLeft" className="h-3.5 w-3.5" /> Previous</Link> : <span className={`${navCls} text-slate-300`}><Icon name="chevronLeft" className="h-3.5 w-3.5" /> Previous</span>}
                    {pages.map((l, i) =>
                        l.url ? (
                            <Link key={i} href={l.url} className={`grid h-9 min-w-9 place-items-center rounded-lg border px-2 ${l.active ? 'border-slate-300 bg-white font-semibold text-slate-900' : 'border-transparent hover:bg-slate-100'}`}>{l.label}</Link>
                        ) : (
                            <span key={i} className="px-1 text-slate-400">{l.label}</span>
                        ),
                    )}
                    {meta.next_page_url ? <Link href={meta.next_page_url} className={`${navCls} hover:bg-slate-100`}>Next <Icon name="chevronRight" className="h-3.5 w-3.5" /></Link> : <span className={`${navCls} text-slate-300`}>Next <Icon name="chevronRight" className="h-3.5 w-3.5" /></span>}
                </nav>
            )}
        </div>
    );
}

/** Rounded outline pill, e.g. quantities in tables. */
export const Pill = ({ children, className = '' }) => <span className={`inline-flex min-w-10 justify-center rounded-full border border-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700 ${className}`}>{children}</span>;

/** Switch with a title and helper text. */
export function Toggle({ label, description, checked, onChange }) {
    return (
        <label className="flex cursor-pointer items-start gap-4">
            <span className="mr-auto">
                <span className="block font-medium text-slate-900">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-slate-500">{description}</span>}
            </span>
            <input type="checkbox" className="peer sr-only" checked={!!checked} onChange={(e) => onChange(e.target.checked)} />
            <span className="relative mt-0.5 h-6 w-11 shrink-0 rounded-full border-2 border-slate-700 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-300 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-slate-700 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-white" />
        </label>
    );
}
