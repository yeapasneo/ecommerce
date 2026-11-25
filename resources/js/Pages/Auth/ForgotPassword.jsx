import { Link, useForm } from '@inertiajs/react';
import { TextInput } from '../../Components/Field';
import ShopLayout from '../../Layouts/ShopLayout';
import AuthCard from '../../Components/AuthCard';
import { t } from '../../lib/i18n';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    return (
        <AuthCard title={t('Forgot Password')} subtitle={t('We\'ll email you a link to reset your password')}>
            <form onSubmit={(e) => { e.preventDefault(); form.post('/forgot-password'); }} className="space-y-4">
                <TextInput label={t('Email')} type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} autoFocus />
                <button disabled={form.processing} className="btn-primary w-full py-2.5">{t('Send Reset Link')}</button>
            </form>
            <p className="mt-6 text-center text-sm"><Link href="/login" className="text-accent-600 hover:underline">{t('Back to login')}</Link></p>
        </AuthCard>
    );
}

ForgotPassword.layout = (page) => <ShopLayout>{page}</ShopLayout>;
