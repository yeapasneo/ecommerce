import { useForm } from '@inertiajs/react';
import { TextInput } from '../../Components/Field';
import ShopLayout from '../../Layouts/ShopLayout';
import AuthCard from '../../Components/AuthCard';
import { t } from '../../lib/i18n';

export default function ResetPassword({ token, email }) {
    const form = useForm({ token, email: email ?? '', password: '', password_confirmation: '' });
    const set = (k) => (e) => form.setData(k, e.target.value);
    return (
        <AuthCard title={t('Reset Password')}>
            <form onSubmit={(e) => { e.preventDefault(); form.post('/reset-password'); }} className="space-y-4">
                <TextInput label={t('Email')} type="email" value={form.data.email} onChange={set('email')} error={form.errors.email} />
                <TextInput label={t('New Password')} type="password" value={form.data.password} onChange={set('password')} error={form.errors.password} />
                <TextInput label={t('Confirm Password')} type="password" value={form.data.password_confirmation} onChange={set('password_confirmation')} />
                <button disabled={form.processing} className="btn-primary w-full py-2.5">{t('Reset Password')}</button>
            </form>
        </AuthCard>
    );
}

ResetPassword.layout = (page) => <ShopLayout>{page}</ShopLayout>;
