import { Link, useForm } from '@inertiajs/react';
import { Checkbox, TextInput } from '../../Components/Field';
import ShopLayout from '../../Layouts/ShopLayout';
import AuthCard from '../../Components/AuthCard';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    return (
        <AuthCard title="Account Login" subtitle="Log in to see your orders, wishlist and saved PCs">
            <form onSubmit={(e) => { e.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }); }} className="space-y-4">
                <TextInput label="Email" type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} autoFocus />
                <TextInput label="Password" type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} />
                <div className="flex items-center justify-between">
                    <Checkbox label="Remember me" checked={form.data.remember} onChange={(v) => form.setData('remember', v)} />
                    <Link href="/forgot-password" className="text-sm text-accent-600 hover:underline">Forgot password?</Link>
                </div>
                <button disabled={form.processing} className="btn-primary w-full py-2.5">Login</button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-500">Don't have an account? <Link href="/register" className="font-semibold text-accent-600 hover:underline">Create one</Link></p>
        </AuthCard>
    );
}

Login.layout = (page) => <ShopLayout>{page}</ShopLayout>;
