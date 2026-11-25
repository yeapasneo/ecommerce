import { Panel } from '../../../Components/Admin/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { ReturnItemsForm } from '../../Account/ReturnForm';

export default function Create({ order, claimable, types, reasons }) {
    return (
        <AdminLayout title={`New return or claim · #${order.order_number}`} subtitle={`${order.first_name} ${order.last_name ?? ''} · ${order.phone}. Staff can go past the return window and warranty period.`} back={`/admin/orders/${order.id}`}>
            <Panel title="Items">
                <ReturnItemsForm admin order={order} claimable={claimable} types={types} reasons={reasons} action={`/admin/orders/${order.id}/returns`} cancelHref={`/admin/orders/${order.id}`} />
            </Panel>
        </AdminLayout>
    );
}
