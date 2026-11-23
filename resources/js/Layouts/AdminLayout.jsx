import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Flash from '../Components/Flash';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';

const nav = [
    ['Overview', [['/admin', 'dashboard', 'Dashboard']]],
    ['Catalog', [
        ['/admin/products', 'box', 'Products'],
        ['/admin/categories', 'folder', 'Categories'],
        ['/admin/brands', 'tag', 'Brands'],
        ['/admin/pc-components', 'cpu', 'PC Builder Parts'],
    ]],
    ['Sales', [
        ['/admin/orders', 'receipt', 'Orders'],
        ['/admin/customers', 'users', 'Customers'],
        ['/admin/coupons', 'gift', 'Coupons'],
    ]],
    ['Content', [
        ['/admin/banners', 'image', 'Banners & Sliders'],
        ['/admin/offers', 'gift', 'Offers'],
        ['/admin/pages', 'file', 'Pages'],
        ['/admin/reviews', 'star', 'Reviews'],
        ['/admin/questions', 'chat', 'Questions'],
        ['/admin/messages', 'mail', 'Messages'],
    ]],
    ['System', [['/admin/settings', 'settings', 'Settings']]],
];

export default function AdminLayout({ title, actions, children }) {
    const { url, props } = usePage();
    const [open, setOpen] = useState(false);
    const isActive = (href) => (href === '/admin' ? url === '/admin' : url.startsWith(href));

    const sidebar = (
        <nav className="flex h-full flex-col">
            <Link href="/admin" className="flex items-center gap-2 px-5 py-4 text-white">
                <span className="grid h-8 w-8 place-items-center rounded-md bg-accent-500 font-black">{props.settings.site_name?.[0]}</span>
                <span className="font-bold">{props.settings.site_name} Admin</span>
            </Link>
            <div className="flex-1 overflow-y-auto px-3 pb-4">
                {nav.map(([group, items]) => (
                    <div key={group} className="mt-4">
                        <p className="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{group}</p>
                        {items.map(([href, icon, label]) => (
                            <Link key={href} href={href} onClick={() => setOpen(false)} className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm ${isActive(href) ? 'bg-white/10 font-semibold text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'}`}>
                                <Icon name={icon} className="h-4 w-4" /> {label}
                            </Link>
                        ))}
                    </div>
                ))}
            </div>
        </nav>
    );

    return (
        <div className="min-h-screen bg-slate-100">
            <Seo title={`${title} · Admin`} />
            <aside className="no-print fixed inset-y-0 left-0 hidden w-60 bg-brand-900 lg:block">{sidebar}</aside>
            {open && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/50" onClick={() => setOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-64 bg-brand-900">{sidebar}</aside>
                </div>
            )}
            <div className="lg:pl-60">
                <header className="no-print sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <button onClick={() => setOpen(true)} className="lg:hidden" aria-label="Open menu"><Icon name="menu" /></button>
                    <h1 className="mr-auto truncate text-lg font-semibold">{title}</h1>
                    <a href="/" target="_blank" rel="noreferrer" className="hidden items-center gap-1 text-sm text-slate-600 hover:text-accent-600 sm:flex"><Icon name="external" className="h-4 w-4" /> View Store</a>
                    <span className="hidden text-sm text-slate-500 md:inline">{props.auth.user.name}</span>
                    <button onClick={() => router.post('/logout')} className="text-slate-500 hover:text-red-600" aria-label="Logout"><Icon name="logout" /></button>
                </header>
                <main className="p-4 md:p-6">
                    {actions && <div className="no-print mb-4 flex flex-wrap items-center gap-2">{actions}</div>}
                    {children}
                </main>
            </div>
            <Flash />
        </div>
    );
}
