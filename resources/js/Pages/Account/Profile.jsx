import { useForm } from '@inertiajs/react';
import { TextArea, TextInput } from '../../Components/Field';
import AccountLayout from '../../Layouts/AccountLayout';
import { t } from '../../lib/i18n';

export default function Profile({ user }) {
    const profile = useForm({ name: user.name ?? '', email: user.email ?? '', phone: user.phone ?? '', address: user.address ?? '', city: user.city ?? '' });
    const pass = useForm({ current_password: '', password: '', password_confirmation: '' });
    const set = (k) => (e) => profile.setData(k, e.target.value);
    return (
        <AccountLayout title={t('Edit Profile')}>
            <form onSubmit={(e) => { e.preventDefault(); profile.put('/account/profile', { preserveScroll: true }); }} className="card grid gap-4 p-5 sm:grid-cols-2">
                <h2 className="font-semibold sm:col-span-2">{t('Personal Information')}</h2>
                <TextInput label={t('Name')} value={profile.data.name} onChange={set('name')} error={profile.errors.name} />
                <TextInput label={t('Email')} type="email" value={profile.data.email} onChange={set('email')} error={profile.errors.email} />
                <TextInput label={t('Phone')} value={profile.data.phone} onChange={set('phone')} error={profile.errors.phone} />
                <TextInput label={t('City')} value={profile.data.city} onChange={set('city')} error={profile.errors.city} />
                <TextArea label={t('Address')} rows={2} className="sm:col-span-2" value={profile.data.address} onChange={set('address')} error={profile.errors.address} />
                <div><button disabled={profile.processing} className="btn-primary">{t('Save Changes')}</button></div>
            </form>
            <form onSubmit={(e) => { e.preventDefault(); pass.put('/account/password', { preserveScroll: true, onSuccess: () => pass.reset() }); }} className="card mt-6 grid gap-4 p-5 sm:grid-cols-3">
                <h2 className="font-semibold sm:col-span-3">{t('Change Password')}</h2>
                <TextInput label={t('Current Password')} type="password" value={pass.data.current_password} onChange={(e) => pass.setData('current_password', e.target.value)} error={pass.errors.current_password} />
                <TextInput label={t('New Password')} type="password" value={pass.data.password} onChange={(e) => pass.setData('password', e.target.value)} error={pass.errors.password} />
                <TextInput label={t('Confirm Password')} type="password" value={pass.data.password_confirmation} onChange={(e) => pass.setData('password_confirmation', e.target.value)} />
                <div><button disabled={pass.processing} className="btn-primary">{t('Update Password')}</button></div>
            </form>
        </AccountLayout>
    );
}
