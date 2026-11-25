import { Link, router, usePage } from '@inertiajs/react';
import { Fragment, useEffect, useRef, useState } from 'react';
import { callUrl, messengerUrl, whatsappUrl } from '../lib/contact';
import { money } from '../lib/format';
import { t } from '../lib/i18n';
import Icon from './Icon';

const STORE_KEY = 'assistant-chat';
const SUGGESTIONS = ['Gaming laptop under 1 lakh', 'Best SSD for my PC', 'Build a PC for 80,000 Tk', 'Track my order'];

const load = () => {
    try {
        return JSON.parse(sessionStorage.getItem(STORE_KEY)) ?? [];
    } catch {
        return [];
    }
};

/** **bold**, bullet lines and links, without rendering any HTML from the AI. */
function Rich({ text }) {
    const inline = (line, key) => line.split(/(\*\*[^*]+\*\*|https?:\/\/[^\s)]+)/g).map((part, i) => {
        if (/^\*\*[^*]+\*\*$/.test(part)) return <strong key={`${key}-${i}`}>{part.slice(2, -2)}</strong>;
        if (/^https?:\/\//.test(part)) {
            const local = part.startsWith(window.location.origin);
            return local
                ? <Link key={`${key}-${i}`} href={part.slice(window.location.origin.length) || '/'} className="font-medium text-accent-600 underline">{t('Open')}</Link>
                : <a key={`${key}-${i}`} href={part} target="_blank" rel="noopener noreferrer nofollow" className="text-accent-600 underline">{part}</a>;
        }
        return <Fragment key={`${key}-${i}`}>{part}</Fragment>;
    });
    const blocks = [];
    text.split('\n').forEach((raw, i) => {
        const line = raw.trimEnd();
        const bullet = line.match(/^\s*(?:[-*•]|\d+[.)])\s+(.*)$/);
        if (bullet) {
            const last = blocks[blocks.length - 1];
            if (last?.type === 'list') last.items.push(bullet[1]);
            else blocks.push({ type: 'list', items: [bullet[1]] });
        } else if (line.trim()) {
            blocks.push({ type: 'p', text: line.replace(/^#+\s*/, '') });
        }
    });
    return blocks.map((b, i) => (b.type === 'list'
        ? <ul key={i} className="my-1 list-disc space-y-0.5 pl-5">{b.items.map((it, j) => <li key={j}>{inline(it, `${i}-${j}`)}</li>)}</ul>
        : <p key={i} className="my-1 first:mt-0 last:mb-0">{inline(b.text, i)}</p>));
}

function ProductRow({ p }) {
    const [state, setState] = useState(null);
    const canAdd = !p.has_variants && p.stock_status === 'in_stock';
    const add = () => router.post('/cart', { product_id: p.id, quantity: 1 }, {
        preserveScroll: true, preserveState: true,
        onStart: () => setState('adding'), onSuccess: () => setState('added'), onError: () => setState(null),
    });
    return (
        <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-2">
            <Link href={`/product/${p.slug}`} className="shrink-0"><img src={p.image_url} alt="" className="h-14 w-14 rounded-lg object-contain" loading="lazy" /></Link>
            <div className="min-w-0 flex-1">
                <Link href={`/product/${p.slug}`} className="line-clamp-2 text-sm font-medium leading-snug text-slate-800 hover:text-accent-600">{p.name}</Link>
                <p className="mt-0.5 text-sm font-bold text-accent-600">
                    {p.has_variants && <span className="mr-1 text-xs font-normal text-slate-500">{t('From')}</span>}
                    {money(p.price)}
                    {p.regular_price > p.price && <span className="ml-1.5 text-xs font-normal text-slate-400 line-through">{money(p.regular_price)}</span>}
                </p>
            </div>
            {canAdd ? (
                <button type="button" onClick={add} disabled={state !== null} className="btn-primary btn-sm shrink-0 px-2.5" aria-label={`${t('Add to Cart')}: ${p.name}`}>
                    <Icon name={state === 'added' ? 'check' : 'cart'} className="h-4 w-4" />
                </button>
            ) : (
                <Link href={`/product/${p.slug}`} className="btn-outline btn-sm shrink-0 px-2.5 text-xs">{p.has_variants ? t('Choose options') : t('View')}</Link>
            )}
        </div>
    );
}

/** "Talk to a person": WhatsApp (with the shopper's question), Messenger or a call. */
function Handover({ settings, question }) {
    const wa = whatsappUrl(settings, question ? `${t('Hi, I was asking your assistant:')} ${question}` : '');
    const ms = messengerUrl(settings);
    const call = callUrl(settings);
    if (!wa && !ms && !call) return null;
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3">
            <p className="mb-2 text-xs font-medium text-slate-600">{t('Talk to a person')}</p>
            <div className="flex flex-wrap gap-2">
                {wa && <a href={wa} target="_blank" rel="noopener noreferrer" className="btn-sm rounded-lg bg-[#25D366] px-3 text-white hover:opacity-90">WhatsApp</a>}
                {ms && <a href={ms} target="_blank" rel="noopener noreferrer" className="btn-sm rounded-lg bg-[#0084FF] px-3 text-white hover:opacity-90">Messenger</a>}
                {call && <a href={call} className="btn-outline btn-sm">{t('Call')} {settings.phone}</a>}
            </div>
        </div>
    );
}

export default function ChatAssistant() {
    const { assistant, settings } = usePage().props;
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const listRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => setMessages(load()), []);
    useEffect(() => {
        try {
            sessionStorage.setItem(STORE_KEY, JSON.stringify(messages.slice(-30)));
        } catch { /* storage full or blocked */ }
        listRef.current?.scrollTo({ top: listRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages, loading]);
    useEffect(() => { window.dispatchEvent(new CustomEvent('assistant:open', { detail: open })); }, [open]);
    useEffect(() => {
        if (!open) return undefined;
        inputRef.current?.focus();
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    if (!assistant) return null;

    const send = async (text) => {
        const content = text.trim();
        if (!content || loading) return;
        const next = [...messages, { role: 'user', content }];
        setMessages(next);
        setInput('');
        setLoading(true);
        try {
            const res = await fetch('/assistant', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
                body: JSON.stringify({ messages: next.slice(-12).map(({ role, content: c }) => ({ role, content: c.slice(0, 2000) })) }),
            });
            const body = res.status === 429 ? { reply: t('You are sending messages too fast. Please wait a minute.') } : await res.json().catch(() => ({}));
            setMessages((m) => [...m, { role: 'assistant', content: body.reply || t('Sorry, something went wrong. Please try again.'), products: body.products ?? [], handover: !!body.handover || !!body.error }]);
        } catch {
            setMessages((m) => [...m, { role: 'assistant', content: t('Could not connect. Check your internet and try again.'), products: [] }]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="no-print">
            {open && (
                <section
                    className="fixed inset-x-0 bottom-0 z-50 flex h-[85vh] flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-slate-50 shadow-2xl sm:inset-x-auto sm:bottom-24 sm:right-5 sm:h-[min(620px,calc(100vh-8rem))] sm:w-[390px] sm:rounded-2xl"
                    role="dialog"
                    aria-label={assistant.name}
                >
                    <header className="flex items-center gap-3 bg-brand-700 px-4 py-3 text-white">
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/15"><Icon name="sparkle" className="h-5 w-5" /></span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-semibold">{assistant.name}</p>
                            <p className="truncate text-xs text-white/70" title={t('AI assistant · answers can be wrong, check before buying')}>{t('AI assistant · answers can be wrong')}</p>
                        </div>
                        <button type="button" onClick={() => setMessages((m) => [...m, { role: 'assistant', content: t('Sure — you can reach our team here:'), products: [], handover: true }])} className="rounded-lg p-1.5 text-white/80 hover:bg-white/10 hover:text-white" aria-label={t('Talk to a person')} title={t('Talk to a person')}><Icon name="user" className="h-5 w-5" /></button>
                        {messages.length > 0 && <button type="button" onClick={() => setMessages([])} className="rounded-lg px-2 py-1 text-xs text-white/80 hover:bg-white/10 hover:text-white">{t('New chat')}</button>}
                        <button type="button" onClick={() => setOpen(false)} className="rounded-lg p-1.5 hover:bg-white/10" aria-label={t('Close')}><Icon name="x" className="h-5 w-5" /></button>
                    </header>

                    <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto px-3 py-4" aria-live="polite">
                        <div className="max-w-[88%] rounded-2xl rounded-tl-sm bg-white px-3.5 py-2.5 text-sm text-slate-700 shadow-sm">{t(assistant.greeting)}</div>
                        {messages.length === 0 && (
                            <div className="flex flex-wrap gap-2 pt-1">
                                {SUGGESTIONS.map((s) => <button key={s} type="button" onClick={() => send(t(s))} className="rounded-full border border-brand-100 bg-white px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-50">{t(s)}</button>)}
                            </div>
                        )}
                        {messages.map((m, i) => (m.role === 'user' ? (
                            <div key={i} className="ml-auto w-fit max-w-[85%] whitespace-pre-wrap break-words rounded-2xl rounded-tr-sm bg-brand-700 px-3.5 py-2.5 text-sm text-white">{m.content}</div>
                        ) : (
                            <div key={i} className="max-w-[92%] space-y-2">
                                <div className="break-words rounded-2xl rounded-tl-sm bg-white px-3.5 py-2.5 text-sm text-slate-700 shadow-sm"><Rich text={m.content} /></div>
                                {m.products?.map((p) => <ProductRow key={p.id} p={p} />)}
                                {m.handover && <Handover settings={settings} question={[...messages.slice(0, i)].reverse().find((x) => x.role === 'user')?.content} />}
                            </div>
                        )))}
                        {loading && (
                            <div className="flex w-fit gap-1 rounded-2xl bg-white px-4 py-3 shadow-sm" aria-label={t('Typing…')}>
                                {[0, 150, 300].map((d) => <span key={d} className="h-2 w-2 animate-bounce rounded-full bg-slate-400" style={{ animationDelay: `${d}ms` }} />)}
                            </div>
                        )}
                    </div>

                    <form onSubmit={(e) => { e.preventDefault(); send(input); }} className="flex items-end gap-2 border-t border-slate-200 bg-white p-3">
                        <textarea
                            ref={inputRef}
                            rows={1}
                            value={input}
                            maxLength={1000}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input); } }}
                            placeholder={t('Ask about products, prices or your order…')}
                            className="input max-h-28 min-h-[42px] flex-1 resize-none py-2.5"
                            aria-label={t('Message')}
                        />
                        <button disabled={loading || !input.trim()} className="btn-primary h-[42px] w-[42px] shrink-0 p-0" aria-label={t('Send')}><Icon name="send" className="h-5 w-5" /></button>
                    </form>
                </section>
            )}

            <button
                type="button"
                onClick={() => setOpen(!open)}
                className={`fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-full bg-accent-500 py-3 pl-4 pr-5 font-semibold text-white shadow-lg transition hover:bg-accent-600 ${open ? 'max-sm:hidden' : ''}`}
                aria-expanded={open}
                aria-label={open ? t('Close') : assistant.name}
            >
                <Icon name={open ? 'x' : 'sparkle'} className="h-5 w-5" />
                <span className="text-sm">{open ? t('Close') : t('Ask AI')}</span>
            </button>
        </div>
    );
}
