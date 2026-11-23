import { router } from '@inertiajs/react';
import { useState } from 'react';
import Breadcrumbs from '../Components/Breadcrumbs';
import OrderTimeline from '../Components/OrderTimeline';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/format';

export default function TrackOrder({ order, searched, query }) {
    const [data, setData] = useState({ order_number: query.order_number ?? '', phone: query.phone ?? '' });
    return (
        <div className="container-x max-w-3xl py-4">
            <Seo title="Track Your Order" />
            <Breadcrumbs items={[{ name: 'Track Order' }]} />
            <form onSubmit={(e) => { e.preventDefault(); router.get('/track-order', data); }} className="card p-6">
                <h1 className="mb-1 text-2xl font-bold">Track Your Order</h1>
                <p className="mb-4 text-sm text-slate-500">Enter your order number and the phone number used at checkout.</p>
                <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <input className="input" placeholder="Order number" value={data.order_number} onChange={(e) => setData({ ...data, order_number: e.target.value })} required aria-label="Order number" />
                    <input className="input" placeholder="Phone number" value={data.phone} onChange={(e) => setData({ ...data, phone: e.target.value })} required aria-label="Phone number" />
                    <button className="btn-primary">Track</button>
                </div>
            </form>
            {searched && !order && <div className="card mt-4 p-6 text-center text-red-600">No order found with these details.</div>}
            {order && (
                <div className="card mt-4 p-6">
                    <div className="mb-4 flex flex-wrap justify-between gap-2">
                        <h2 className="font-semibold">Order #{order.order_number}</h2>
                        <span className="font-semibold text-accent-600">{money(order.total)}</span>
                    </div>
                    <OrderTimeline order={order} />
                </div>
            )}
        </div>
    );
}

TrackOrder.layout = (page) => <ShopLayout>{page}</ShopLayout>;
