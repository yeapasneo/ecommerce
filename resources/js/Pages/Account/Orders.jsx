import Pagination from '../../Components/Pagination';
import AccountLayout from '../../Layouts/AccountLayout';
import { OrdersTable } from './Dashboard';
import { t } from '../../lib/i18n';

export default function Orders({ orders }) {
    return (
        <AccountLayout title={t('Order History')}>
            <div className="card overflow-x-auto"><OrdersTable orders={orders.data} /></div>
            <Pagination meta={orders} />
        </AccountLayout>
    );
}
