import { Link, useForm } from '@inertiajs/react';
import { TextInput } from '../../Components/Field';
import ShopLayout from '../../Layouts/ShopLayout';
import AuthCard from '../../Components/AuthCard';
import { t } from '../../lib/i18n';

export default function Register() {
    const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const set = (k) => (e) => form.setData(k, e.target.value);
    return (
        <AuthCard title={t('Register Account')} subtitle={t('Create an account for faster checkout and order tracking')}>
            <form onSubmit={(e) => { e.preventDefault(); form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') }); }} className="space-y-4">
                <TextInput label={t('Full Name')} value={form.data.name} onChange={set('name')} error={form.errors.name} autoFocus />
                <TextInput label={t('Email')} type="email" value={form.data.email} onChange={set('email')} error={form.errors.email} />
                <TextInput label={t('Mobile')} value={form.data.phone} onChange={set('phone')} error={form.errors.phone} />
                <TextInput label={t('Password')} type="password" autoComplete="new-password" value={form.data.password} onChange={set('password')} error={form.errors.password} />
                <TextInput label={t('Confirm Password')} type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={set('password_confirmation')} />
                <button disabled={form.processing} className="btn-primary w-full py-2.5">{t('Create Account')}</button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-500">{t('Already have an account?')} <Link href="/login" className="font-semibold text-accent-600 hover:underline">{t('Login')}</Link></p>
        </AuthCard>
    );
}

Register.layout = (page) => <ShopLayout>{page}</ShopLayout>;
