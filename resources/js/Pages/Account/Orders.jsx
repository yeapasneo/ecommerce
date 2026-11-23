import Pagination from '../../Components/Pagination';
import AccountLayout from '../../Layouts/AccountLayout';
import { OrdersTable } from './Dashboard';

export default function Orders({ orders }) {
    return (
        <AccountLayout title="Order History">
            <div className="card overflow-x-auto"><OrdersTable orders={orders.data} /></div>
            <Pagination meta={orders} />
        </AccountLayout>
    );
}
