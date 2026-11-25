import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ChatAssistant from '../Components/ChatAssistant';
import ContactButtons from '../Components/ContactButtons';
import InstallApp from '../Components/InstallApp';
import Flash from '../Components/Flash';
import Icon from '../Components/Icon';
import SearchBox from '../Components/SearchBox';
import { locale, t } from '../lib/i18n';

/** English ⇄ বাংলা. A full page load so every text switches at once. */
function LanguageSwitch({ className = '' }) {
    const other = locale() === 'bn' ? ['en', 'English'] : ['bn', 'বাংলা'];
    return (
        <a href={`/locale/${other[0]}`} className={`items-center gap-1 text-sm font-semibold ${className}`} aria-label={`${t('Language')}: ${other[1]}`} lang={other[0]}>
            <Icon name="chat" className="h-4 w-4" /> {other[1]}
        </a>
    );
}

function Logo({ name }) {
    return (
        <Link href="/" className="flex shrink-0 items-center gap-2 text-white">
            <span className="grid h-9 w-9 place-items-center rounded-lg bg-accent-500 text-lg font-black">{name?.[0] ?? 'T'}</span>
            <span className="text-xl font-extrabold tracking-tight">{name}</span>
        </Link>
    );
}

function HeaderLink({ href, icon, title, subtitle, count }) {
    return (
        <Link href={href} className="relative hidden items-center gap-2 text-white hover:text-accent-400 lg:flex">
            <span className="relative">
                <Icon name={icon} className="h-6 w-6 text-accent-400" />
                {count > 0 && <span className="absolute -right-2 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white">{count}</span>}
            </span>
            <span className="leading-tight">
                <span className="block text-sm font-semibold">{title}</span>
                <span className="block text-xs text-slate-400">{subtitle}</span>
            </span>
        </Link>
    );
}

function MegaMenu({ menu }) {
    return (
        <nav className="hidden border-b border-slate-200 bg-white shadow-sm lg:block">
            <ul className="container-x flex flex-wrap justify-center">
                {menu.map((cat) => (
                    <li key={cat.id} className="group relative">
                        <Link href={`/category/${cat.slug}`} className="block px-3 py-3 text-sm font-medium text-slate-700 hover:text-accent-600">
                            {cat.name}
                        </Link>
                        {cat.children.length > 0 && (
                            <ul className="invisible absolute left-0 top-full z-40 min-w-56 rounded-b-md bg-white py-1 opacity-0 shadow-lg ring-1 ring-slate-200 transition group-hover:visible group-hover:opacity-100">
                                {cat.children.map((c) => (
                                    <li key={c.id} className="group/sub relative">
                                        <Link href={`/category/${c.slug}`} className="flex items-center justify-between px-4 py-2 text-sm text-slate-700 hover:bg-brand-50 hover:text-accent-600">
                                            {c.name}
                                            {c.children.length > 0 && <Icon name="chevronRight" className="h-3.5 w-3.5" />}
                                        </Link>
                                        {c.children.length > 0 && (
                                            <ul className="invisible absolute left-full top-0 min-w-52 rounded-md bg-white py-1 opacity-0 shadow-lg ring-1 ring-slate-200 group-hover/sub:visible group-hover/sub:opacity-100">
                                                {c.children.map((g) => (
                                                    <li key={g.id}>
                                                        <Link href={`/category/${g.slug}`} className="block px-4 py-2 text-sm hover:bg-brand-50 hover:text-accent-600">{g.name}</Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                                <li className="border-t border-slate-100">
                                    <Link href={`/category/${cat.slug}`} className="block px-4 py-2 text-sm font-semibold text-brand-600 hover:underline">Show all {cat.name}</Link>
                                </li>
                            </ul>
                        )}
                    </li>
                ))}
            </ul>
        </nav>
    );
}

function MobileMenu({ menu, open, onClose, user }) {
    const [expanded, setExpanded] = useState({});
    if (!open) return null;
    const toggle = (id) => setExpanded((e) => ({ ...e, [id]: !e[id] }));
    const renderItems = (items, depth = 0) =>
        items.map((c) => (
            <li key={c.id}>
                <div className="flex items-center justify-between border-b border-slate-100" style={{ paddingLeft: depth * 14 }}>
                    <Link href={`/category/${c.slug}`} onClick={onClose} className="flex-1 px-4 py-2.5 text-sm">{c.name}</Link>
                    {c.children.length > 0 && (
                        <button onClick={() => toggle(c.id)} className="px-4 py-2.5 text-slate-500" aria-label={`Expand ${c.name}`}>
                            <Icon name={expanded[c.id] ? 'minus' : 'plus'} className="h-4 w-4" />
                        </button>
                    )}
                </div>
                {expanded[c.id] && <ul className="bg-slate-50">{renderItems(c.children, depth + 1)}</ul>}
            </li>
        ));
    return (
        <div className="fixed inset-0 z-50 lg:hidden">
            <div className="absolute inset-0 bg-black/50" onClick={onClose} />
            <aside className="absolute inset-y-0 left-0 w-80 max-w-[85%] overflow-y-auto bg-white shadow-xl">
                <div className="flex items-center justify-between bg-brand-900 px-4 py-3 text-white">
                    <span className="font-semibold">{t('Menu')}</span>
                    <button onClick={onClose} aria-label={t('Close menu')}><Icon name="x" /></button>
                </div>
                <div className="grid grid-cols-2 gap-2 border-b border-slate-200 p-3 text-sm">
                    <Link href="/offers" onClick={onClose} className="btn-outline btn-sm"><Icon name="gift" className="h-4 w-4" />{t('Offers')}</Link>
                    <Link href="/pc-builder" onClick={onClose} className="btn-outline btn-sm"><Icon name="cpu" className="h-4 w-4" />{t('PC Builder')}</Link>
                    <Link href="/compare" onClick={onClose} className="btn-outline btn-sm"><Icon name="compare" className="h-4 w-4" />{t('Compare')}</Link>
                    <Link href={user ? '/account' : '/login'} onClick={onClose} className="btn-outline btn-sm"><Icon name="user" className="h-4 w-4" />{user ? t('Account') : t('Login')}</Link>
                    <LanguageSwitch className="btn-outline btn-sm" />
                </div>
                <ul>{renderItems(menu)}</ul>
            </aside>
        </div>
    );
}

function Footer({ settings }) {
    const form = useForm({ email: '' });
    const subscribe = (e) => {
        e.preventDefault();
        form.post('/newsletter', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <footer className="no-print mt-12 bg-brand-900 text-slate-300">
            <div className="container-x grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <Logo name={settings.site_name} />
                    <p className="mt-3 text-sm">{settings.tagline}</p>
                    <ul className="mt-4 space-y-2 text-sm">
                        <li className="flex gap-2"><Icon name="phone" className="h-4 w-4 text-accent-400" /> {settings.phone}</li>
                        <li className="flex gap-2"><Icon name="mail" className="h-4 w-4 text-accent-400" /> {settings.email}</li>
                        <li className="flex gap-2"><Icon name="pin" className="h-4 w-4 shrink-0 text-accent-400" /> {settings.address}</li>
                    </ul>
                </div>
                <div>
                    <h4 className="mb-3 font-semibold text-white">{t('Information')}</h4>
                    <ul className="space-y-2 text-sm">
                        <li><Link href="/page/about-us" className="hover:text-accent-400">{t('About Us')}</Link></li>
                        <li><Link href="/page/terms-and-conditions" className="hover:text-accent-400">{t('Terms and Conditions')}</Link></li>
                        <li><Link href="/page/privacy-policy" className="hover:text-accent-400">{t('Privacy Policy')}</Link></li>
                        <li><Link href="/page/refund-policy" className="hover:text-accent-400">{t('Refund and Return Policy')}</Link></li>
                        <li><Link href="/page/delivery-policy" className="hover:text-accent-400">{t('Delivery Policy')}</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 className="mb-3 font-semibold text-white">{t('Customer Service')}</h4>
                    <ul className="space-y-2 text-sm">
                        <li><Link href="/account" className="hover:text-accent-400">{t('My Account')}</Link></li>
                        <li><Link href="/track-order" className="hover:text-accent-400">{t('Track Order')}</Link></li>
                        <li><Link href="/pc-builder" className="hover:text-accent-400">{t('PC Builder')}</Link></li>
                        <li><Link href="/brands" className="hover:text-accent-400">{t('Brands')}</Link></li>
                        <li><Link href="/contact" className="hover:text-accent-400">{t('Contact Us')}</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 className="mb-3 font-semibold text-white">{t('Newsletter')}</h4>
                    <p className="mb-3 text-sm">{t('Get the latest deals and offers in your inbox.')}</p>
                    <form onSubmit={subscribe} className="flex gap-2">
                        <input type="email" required value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder={t('Your email')} className="input border-0" aria-label={t('Email for newsletter')} />
                        <button disabled={form.processing} className="btn-accent">{t('Join')}</button>
                    </form>
                    {form.errors.email && <p className="mt-1 text-xs text-red-400">{form.errors.email}</p>}
                    <div className="mt-4 flex gap-3 text-sm">
                        {settings.facebook && <a href={settings.facebook} target="_blank" rel="noreferrer" className="hover:text-accent-400">{t('Facebook')}</a>}
                        {settings.youtube && <a href={settings.youtube} target="_blank" rel="noreferrer" className="hover:text-accent-400">{t('YouTube')}</a>}
                        {settings.instagram && <a href={settings.instagram} target="_blank" rel="noreferrer" className="hover:text-accent-400">{t('Instagram')}</a>}
                    </div>
                    <InstallApp className="mt-4" />
                </div>
            </div>
            <div className="border-t border-white/10 py-4 text-center text-xs text-slate-400">
                © {new Date().getFullYear()} {settings.site_name}. All rights reserved.
            </div>
        </footer>
    );
}

export default function ShopLayout({ children }) {
    const { auth, settings, menu, cartCount, compareCount } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const user = auth.user;

    useEffect(() => {
        document.body.style.overflow = mobileOpen ? 'hidden' : '';
    }, [mobileOpen]);

    return (
        <div className="flex min-h-screen flex-col">
            <header className="no-print sticky top-0 z-40 bg-brand-900 shadow">
                <div className="container-x flex items-center gap-4 py-3">
                    <button onClick={() => setMobileOpen(true)} className="text-white lg:hidden" aria-label={t('Open menu')}><Icon name="menu" className="h-6 w-6" /></button>
                    <Logo name={settings.site_name} />
                    <SearchBox className="hidden flex-1 md:block" />
                    <HeaderLink href="/offers" icon="gift" title={t('Offers')} subtitle={t('Latest Offers')} />
                    <HeaderLink href="/compare" icon="compare" title={t('Compare')} subtitle={t('Up to 3 items')} count={compareCount} />
                    <HeaderLink href={user ? '/account' : '/login'} icon="user" title={t('Account')} subtitle={user ? user.name.split(' ')[0] : t('Register or Login')} />
                    <LanguageSwitch className="hidden text-white/80 hover:text-white lg:inline-flex" />
                    {user?.is_admin && (
                        <Link href="/admin" className="hidden rounded-md border border-white/20 px-3 py-2 text-xs font-semibold text-white hover:border-accent-400 lg:block">{t('Admin')}</Link>
                    )}
                    <Link href="/pc-builder" className="btn-accent hidden lg:inline-flex">{t('PC Builder')}</Link>
                    <Link href="/cart" className="relative ml-auto text-white hover:text-accent-400 lg:ml-0" aria-label={t('Cart')}>
                        <Icon name="cart" className="h-7 w-7" />
                        <span className="absolute -right-2 -top-2 grid h-5 min-w-5 place-items-center rounded-full bg-accent-500 px-1 text-xs font-bold">{cartCount}</span>
                    </Link>
                </div>
                <div className="container-x pb-3 md:hidden"><SearchBox /></div>
            </header>
            <MegaMenu menu={menu} />
            <MobileMenu menu={menu} open={mobileOpen} onClose={() => setMobileOpen(false)} user={user} />
            <main className="flex-1">{children}</main>
            <Footer settings={settings} />
            <Flash />
            <ContactButtons />
            <ChatAssistant />
        </div>
    );
}
