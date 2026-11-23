import { useForm } from '@inertiajs/react';
import { TextArea, TextInput } from '../../Components/Field';
import AdminLayout from '../../Layouts/AdminLayout';

export default function Settings({ values }) {
    const form = useForm(values);
    const f = (k, label, props = {}) => <TextInput label={label} value={form.data[k] ?? ''} onChange={(e) => form.setData(k, e.target.value)} error={form.errors[k]} {...props} />;
    return (
        <AdminLayout title="Settings">
            <form onSubmit={(e) => { e.preventDefault(); form.put('/admin/settings', { preserveScroll: true }); }} className="max-w-4xl space-y-6">
                <section className="card grid gap-4 p-5 sm:grid-cols-2">
                    <h2 className="font-semibold sm:col-span-2">Store</h2>
                    {f('site_name', 'Store Name')}
                    {f('tagline', 'Tagline')}
                    {f('phone', 'Phone / Hotline')}
                    {f('email', 'Support Email', { type: 'email' })}
                    <TextArea label="Address" className="sm:col-span-2" rows={2} value={form.data.address ?? ''} onChange={(e) => form.setData('address', e.target.value)} />
                    <TextArea label="Notice" className="sm:col-span-2" rows={2} value={form.data.notice ?? ''} onChange={(e) => form.setData('notice', e.target.value)} />
                </section>
                <section className="card grid gap-4 p-5 sm:grid-cols-2">
                    <h2 className="font-semibold sm:col-span-2">Delivery Charges (৳)</h2>
                    {f('delivery_charge_inside', 'Inside Dhaka', { type: 'number' })}
                    {f('delivery_charge_outside', 'Outside Dhaka', { type: 'number' })}
                    {f('express_delivery_charge', 'Express Delivery', { type: 'number' })}
                    {f('free_delivery_over', 'Free delivery for orders over', { type: 'number', hint: '0 to disable' })}
                </section>
                <section className="card grid gap-4 p-5 sm:grid-cols-3">
                    <h2 className="font-semibold sm:col-span-3">Social Links</h2>
                    {f('facebook', 'Facebook URL')}
                    {f('youtube', 'YouTube URL')}
                    {f('instagram', 'Instagram URL')}
                </section>
                <button disabled={form.processing} className="btn-primary">Save Settings</button>
            </form>
        </AdminLayout>
    );
}
