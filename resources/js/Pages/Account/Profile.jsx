import { useForm } from '@inertiajs/react';
import { TextArea, TextInput } from '../../Components/Field';
import AccountLayout from '../../Layouts/AccountLayout';

export default function Profile({ user }) {
    const profile = useForm({ name: user.name ?? '', email: user.email ?? '', phone: user.phone ?? '', address: user.address ?? '', city: user.city ?? '' });
    const pass = useForm({ current_password: '', password: '', password_confirmation: '' });
    const set = (k) => (e) => profile.setData(k, e.target.value);
    return (
        <AccountLayout title="Edit Profile">
            <form onSubmit={(e) => { e.preventDefault(); profile.put('/account/profile', { preserveScroll: true }); }} className="card grid gap-4 p-5 sm:grid-cols-2">
                <h2 className="font-semibold sm:col-span-2">Personal Information</h2>
                <TextInput label="Name" value={profile.data.name} onChange={set('name')} error={profile.errors.name} />
                <TextInput label="Email" type="email" value={profile.data.email} onChange={set('email')} error={profile.errors.email} />
                <TextInput label="Phone" value={profile.data.phone} onChange={set('phone')} error={profile.errors.phone} />
                <TextInput label="City" value={profile.data.city} onChange={set('city')} error={profile.errors.city} />
                <TextArea label="Address" rows={2} className="sm:col-span-2" value={profile.data.address} onChange={set('address')} error={profile.errors.address} />
                <div><button disabled={profile.processing} className="btn-primary">Save Changes</button></div>
            </form>
            <form onSubmit={(e) => { e.preventDefault(); pass.put('/account/password', { preserveScroll: true, onSuccess: () => pass.reset() }); }} className="card mt-6 grid gap-4 p-5 sm:grid-cols-3">
                <h2 className="font-semibold sm:col-span-3">Change Password</h2>
                <TextInput label="Current Password" type="password" value={pass.data.current_password} onChange={(e) => pass.setData('current_password', e.target.value)} error={pass.errors.current_password} />
                <TextInput label="New Password" type="password" value={pass.data.password} onChange={(e) => pass.setData('password', e.target.value)} error={pass.errors.password} />
                <TextInput label="Confirm Password" type="password" value={pass.data.password_confirmation} onChange={(e) => pass.setData('password_confirmation', e.target.value)} />
                <div><button disabled={pass.processing} className="btn-primary">Update Password</button></div>
            </form>
        </AccountLayout>
    );
}
