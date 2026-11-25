import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Dropdown, PageHeader } from '../Components/Admin/UI';
import Flash from '../Components/Flash';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';

// [group label, [href, icon, label, badge key]]
// [group label, [href, icon, label, badge key, permission]]
const nav = [
    [null, [
        ['/admin', 'dashboard', 'Dashboard'],
        ['/admin/orders', 'receipt', 'Orders', 'orders', 'orders'],
        ['/admin/products', 'box', 'Products', null, 'catalog'],
        ['/admin/categories', 'folder', 'Categories', null, 'catalog'],
        ['/admin/brands', 'tag', 'Brands', null, 'catalog'],
        ['/admin/customers', 'users', 'Customers', null, 'orders'],
        ['/admin/abandoned-carts', 'cart', 'Abandoned Carts', null, 'orders'],
        ['/admin/blocked-phones', 'shield', 'Blocked Numbers', null, 'orders'],
        ['/admin/reports', 'dashboard', 'Reports', null, 'reports'],
    ]],
    ['Marketing', [
        ['/admin/flash-sales', 'gift', 'Flash Sales', null, 'marketing'],
        ['/admin/coupons', 'percent', 'Coupons', null, 'marketing'],
        ['/admin/offers', 'megaphone', 'Offers', null, 'marketing'],
        ['/admin/banners', 'image', 'Banners & Sliders', null, 'marketing'],
    ]],
    ['Storefront', [
        ['/admin/pc-components', 'cpu', 'PC Builder Parts', null, 'catalog'],
        ['/admin/pages', 'file', 'Pages', null, 'marketing'],
    ]],
    ['Engagement', [
        ['/admin/reviews', 'star', 'Reviews', 'reviews', 'engagement'],
        ['/admin/questions', 'chat', 'Questions', 'questions', 'engagement'],
        ['/admin/messages', 'mail', 'Messages', 'messages', 'engagement'],
    ]],
    ['Settings', [
        ['/admin/staff', 'users', 'Staff & Roles', null, 'staff'],
        ['/admin/activity', 'refresh', 'Activity Log', null, 'staff'],
        ['/admin/settings', 'settings', 'Store Settings', null, 'settings'],
        ['/admin/system', 'info', 'System Status', null, 'settings'],
    ]],
];

const readCollapsed = () => {
    try {
        return localStorage.getItem('admin.sidebar') === 'collapsed';
    } catch {
        return false;
    }
};

/**
 * Admin shell. `title` + `actions` (+ optional `back`, `count`, `subtitle`) render the page header;
 * pass `header={false}` when the page draws its own.
 */
export default function AdminLayout({ title, actions, back, count, subtitle, header = true, children }) {
    const { url, props } = usePage();
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [drawer, setDrawer] = useState(false);
    const user = props.auth.user;
    const counts = props.adminCounts ?? {};
    const perms = user.permissions ?? [];
    const visibleNav = nav
        .map(([group, items]) => [group, items.filter((i) => !i[4] || perms.includes(i[4]))])
        .filter(([, items]) => items.length > 0);
    const siteName = props.settings.site_name ?? 'Store';
    const path = url.split('?')[0];
    const isActive = (href) => (href === '/admin' ? path === '/admin' : path.startsWith(href));

    useEffect(() => {
        try {
            localStorage.setItem('admin.sidebar', collapsed ? 'collapsed' : 'open');
        } catch {
            // storage unavailable: the choice just isn't remembered
        }
    }, [collapsed]);

    const toggle = () => (window.matchMedia('(min-width: 1024px)').matches ? setCollapsed(!collapsed) : setDrawer(true));

    const sidebar = (mini) => (
        <nav className="flex h-full flex-col" aria-label="Admin">
            <Link href="/admin" className={`flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-100 ${mini ? 'justify-center' : 'px-5'}`}>
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-600 text-lg font-black text-white">{siteName[0]}</span>
                {!mini && <span className="truncate text-xl font-bold tracking-tight text-slate-900">{siteName}</span>}
            </Link>
            <div className="flex-1 overflow-y-auto px-3 py-3">
                {visibleNav.map(([group, items], gi) => (
                    <div key={gi} className={gi ? 'mt-3.5' : ''}>
                        {group && !mini && <p className="px-3 pb-1 text-xs font-medium text-slate-500">{group}</p>}
                        {group && mini && <div className="mx-3 mb-2 border-t border-slate-100" />}
                        {items.map(([href, icon, label, badge]) => {
                            const active = isActive(href);
                            const n = badge ? counts[badge] : 0;
                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    title={mini ? label : undefined}
                                    onClick={() => setDrawer(false)}
                                    className={`relative mb-0.5 flex items-center gap-3 rounded-lg text-[15px] transition ${mini ? 'justify-center p-2.5' : 'px-3 py-[7px]'} ${active ? 'bg-primary-100 font-medium text-primary-700' : 'text-slate-700 hover:bg-slate-100'}`}
                                >
                                    <Icon name={icon} className="h-[18px] w-[18px] shrink-0" />
                                    {!mini && <span className="mr-auto truncate">{label}</span>}
                                    {n > 0 && (mini
                                        ? <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-primary-600" />
                                        : <span className="rounded-full bg-primary-700 px-1.5 py-px text-[10px] font-bold text-white">{n > 99 ? '99+' : n}</span>)}
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </div>
            {mini ? (
                <a href="/" target="_blank" rel="noreferrer" title="Visit store" className="mx-auto mb-4 grid h-10 w-10 place-items-center rounded-lg text-primary-700 hover:bg-primary-50"><Icon name="store" /></a>
            ) : (
                <div className="m-3 flex shrink-0 items-center gap-2.5 rounded-xl border border-primary-200 bg-gradient-to-r from-primary-50 to-white p-2.5">
                    <span className="relative grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-primary-700 ring-1 ring-primary-200">
                        <Icon name="store" className="h-4 w-4" />
                        <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-green-500" title="Online" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-primary-700">{siteName} Store</p>
                        <p className="truncate text-xs text-slate-500">Storefront is live</p>
                    </div>
                    <a href="/" target="_blank" rel="noreferrer" className="btn-primary shrink-0 rounded-lg px-3 py-1.5 text-xs">Visit</a>
                </div>
            )}
        </nav>
    );

    return (
        <div className="admin-theme min-h-screen text-slate-800">
            <Seo title={`${title} · Admin`} />

            <aside className={`no-print fixed inset-y-3 left-3 z-30 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white transition-[width] lg:block ${collapsed ? 'w-[72px]' : 'w-60'}`}>
                {sidebar(collapsed)}
            </aside>
            {drawer && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-slate-900/40" onClick={() => setDrawer(false)} />
                    <aside className="absolute inset-y-0 left-0 w-64 bg-white shadow-xl">{sidebar(false)}</aside>
                </div>
            )}

            <div className={`transition-[padding] ${collapsed ? 'lg:pl-[90px]' : 'lg:pl-[258px]'}`}>
                <header className="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur md:px-6">
                    <button onClick={toggle} className="grid h-9 w-9 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Toggle sidebar">
                        <Icon name="sidebar" className="h-5 w-5" />
                    </button>
                    <span className="h-6 w-px bg-slate-200" />
                    <div className="ml-auto flex items-center gap-2">
                        <a href="/" target="_blank" rel="noreferrer" className="grid h-10 w-10 place-items-center rounded-full text-slate-600 hover:bg-slate-100" title="View store" aria-label="View store">
                            <Icon name="cart" className="h-5 w-5" />
                        </a>
                        <Dropdown
                            button={({ toggle: t }) => (
                                <button onClick={t} className="grid h-10 w-10 place-items-center rounded-full bg-primary-700 font-semibold text-white ring-offset-2 hover:ring-2 hover:ring-primary-300" aria-label="Account menu">
                                    {user.name?.[0]?.toUpperCase()}
                                </button>
                            )}
                        >
                            <div className="border-b border-slate-100 px-3 pb-2 pt-1">
                                <p className="truncate font-semibold text-slate-900">{user.name}</p>
                                <p className="truncate text-xs text-slate-500">{user.email}</p>
                            </div>
                            <Link href="/account/profile" data-close className="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><Icon name="user" className="h-4 w-4" /> My profile</Link>
                            <a href="/" target="_blank" rel="noreferrer" data-close className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><Icon name="external" className="h-4 w-4" /> View store</a>
                            <button onClick={() => router.post('/logout')} data-close className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50"><Icon name="logout" className="h-4 w-4" /> Log out</button>
                        </Dropdown>
                    </div>
                </header>

                <main className="mx-auto max-w-[1440px] p-4 md:p-6">
                    {header && (
                        <PageHeader title={title} back={back} count={count} subtitle={subtitle}>
                            {actions}
                        </PageHeader>
                    )}
                    {children}
                </main>
            </div>
            <Flash />
        </div>
    );
}
