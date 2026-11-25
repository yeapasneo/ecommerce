import { Link } from '@inertiajs/react';
import Icon from '../../Components/Icon';
import AccountLayout from '../../Layouts/AccountLayout';
import { date, money, statusColor, titleCase } from '../../lib/format';
import { t } from '../../lib/i18n';

export default function Dashboard({ stats, recentOrders }) {
    const cards = [
        ['receipt', 'Total Orders', stats.orders, '/account/orders'],
        ['truck', 'Active Orders', stats.pending, '/account/orders'],
        ['heart', 'Wishlist', stats.wishlist, '/account/wishlist'],
        ['cpu', 'Saved PCs', stats.savedPcs, '/account/saved-pcs'],
    ];
    return (
        <AccountLayout title={t('Dashboard')}>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {cards.map(([icon, label, value, href]) => (
                    <Link key={label} href={href} className="card flex items-center gap-3 p-4 hover:shadow-md">
                        <span className="grid h-10 w-10 place-items-center rounded-full bg-accent-500/10 text-accent-600"><Icon name={icon} /></span>
                        <span><span className="block text-xl font-bold">{value}</span><span className="text-xs text-slate-500">{label}</span></span>
                    </Link>
                ))}
            </div>
            <div className="card mt-6 overflow-x-auto">
                <h2 className="px-4 py-3 font-semibold">{t('Recent Orders')}</h2>
                <OrdersTable orders={recentOrders} />
            </div>
        </AccountLayout>
    );
}

export function OrdersTable({ orders }) {
    if (!orders.length) return <p className="p-6 text-center text-sm text-slate-500">{t('You have not placed any orders yet.')}</p>;
    return (
        <table className="table-x min-w-[560px]">
            <thead><tr><th>{t('Order')}</th><th>{t('Date')}</th><th>{t('Status')}</th><th className="text-right">{t('Total')}</th><th /></tr></thead>
            <tbody>
                {orders.map((o) => (
                    <tr key={o.id}>
                        <td className="font-medium">#{o.order_number}</td>
                        <td>{date(o.created_at)}</td>
                        <td><span className={`badge ${statusColor(o.status)}`}>{titleCase(o.status)}</span></td>
                        <td className="text-right">{money(o.total)}</td>
                        <td className="text-right"><Link href={`/account/orders/${o.order_number}`} className="text-sm text-accent-600 hover:underline">{t('View')}</Link></td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
