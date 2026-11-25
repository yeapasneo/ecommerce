import { Link, router, usePage } from '@inertiajs/react';
import Breadcrumbs from '../Components/Breadcrumbs';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';
import ShopLayout from './ShopLayout';
import { t } from '../lib/i18n';

const links = [
    ['/account', 'dashboard', 'Dashboard'],
    ['/account/orders', 'receipt', 'Orders'],
    ['/account/wishlist', 'heart', 'Wishlist'],
    ['/account/saved-pcs', 'cpu', 'Saved PCs'],
    ['/account/profile', 'user', 'Edit Profile'],
];

export default function AccountLayout({ title, children }) {
    const { url, props } = usePage();
    return (
        <ShopLayout>
            <Seo title={title} />
            <div className="container-x py-4">
                <Breadcrumbs items={[{ name: 'Account', url: '/account' }, { name: title }]} />
                <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
                    <aside className="card h-fit overflow-hidden">
                        <div className="bg-brand-900 p-4 text-white">
                            <p className="text-xs text-slate-300">Hello,</p>
                            <p className="font-semibold">{props.auth.user.name}</p>
                        </div>
                        <nav className="flex overflow-x-auto lg:block">
                            {links.map(([href, icon, label]) => {
                                const active = href === '/account' ? url === '/account' : url.startsWith(href);
                                return (
                                    <Link key={href} href={href} className={`flex shrink-0 items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm ${active ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'}`}>
                                        <Icon name={icon} className="h-4 w-4" /> {label}
                                    </Link>
                                );
                            })}
                            <button onClick={() => router.post('/logout')} className="flex w-full shrink-0 items-center gap-3 px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50">
                                <Icon name="logout" className="h-4 w-4" /> {t('Logout')}
                            </button>
                        </nav>
                    </aside>
                    <section>
                        <h1 className="mb-4 text-xl font-bold">{title}</h1>
                        {children}
                    </section>
                </div>
            </div>
        </ShopLayout>
    );
}
