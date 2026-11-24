import { useForm } from '@inertiajs/react';
import { Panel } from '../../Components/Admin/UI';
import { TextArea, TextInput } from '../../Components/Field';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';

export default function Settings({ values }) {
    const form = useForm(values);
    const f = (k, label, props = {}) => <TextInput label={label} value={form.data[k] ?? ''} onChange={(e) => form.setData(k, e.target.value)} error={form.errors[k]} {...props} />;
    return (
        <AdminLayout
            title="Store Settings"
            subtitle="Store details, delivery charges and social links"
            actions={
                <>
                    <button type="button" onClick={() => form.reset()} disabled={!form.isDirty} className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Discard</button>
                    <button form="settings-form" disabled={form.processing} className="btn-primary"><Icon name="check" className="h-4 w-4" /> {form.processing ? 'Saving…' : 'Save Settings'}</button>
                </>
            }
        >
            <form id="settings-form" onSubmit={(e) => { e.preventDefault(); form.put('/admin/settings', { preserveScroll: true }); }} className="grid items-start gap-6 xl:grid-cols-[1fr_420px]">
                <Panel title="Store Information" icon="store">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {f('site_name', 'Store Name')}
                        {f('tagline', 'Tagline')}
                        {f('phone', 'Phone / Hotline')}
                        {f('email', 'Support Email', { type: 'email' })}
                        <TextArea label="Address" className="sm:col-span-2" rows={2} value={form.data.address ?? ''} onChange={(e) => form.setData('address', e.target.value)} />
                        <TextArea label="Notice" className="sm:col-span-2" rows={2} value={form.data.notice ?? ''} onChange={(e) => form.setData('notice', e.target.value)} hint="Shown in the storefront header bar" />
                    </div>
                </Panel>
                <div className="space-y-6">
                    <Panel title="Delivery Charges" icon="truck">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {f('delivery_charge_inside', 'Inside Dhaka (৳)', { type: 'number' })}
                            {f('delivery_charge_outside', 'Outside Dhaka (৳)', { type: 'number' })}
                            {f('express_delivery_charge', 'Express Delivery (৳)', { type: 'number' })}
                            {f('free_delivery_over', 'Free delivery over (৳)', { type: 'number', hint: '0 to disable' })}
                        </div>
                    </Panel>
                    <Panel title="Social Links" icon="megaphone">
                        <div className="grid gap-4">
                            {f('facebook', 'Facebook URL')}
                            {f('youtube', 'YouTube URL')}
                            {f('instagram', 'Instagram URL')}
                        </div>
                    </Panel>
                </div>
            </form>
        </AdminLayout>
    );
}
