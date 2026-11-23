import { Link, useForm } from '@inertiajs/react';
import { TextInput } from '../../Components/Field';
import ShopLayout from '../../Layouts/ShopLayout';
import AuthCard from '../../Components/AuthCard';

export default function Register() {
    const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const set = (k) => (e) => form.setData(k, e.target.value);
    return (
        <AuthCard title="Register Account" subtitle="Create an account for faster checkout and order tracking">
            <form onSubmit={(e) => { e.preventDefault(); form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') }); }} className="space-y-4">
                <TextInput label="Full Name" value={form.data.name} onChange={set('name')} error={form.errors.name} autoFocus />
                <TextInput label="Email" type="email" value={form.data.email} onChange={set('email')} error={form.errors.email} />
                <TextInput label="Mobile" value={form.data.phone} onChange={set('phone')} error={form.errors.phone} />
                <TextInput label="Password" type="password" autoComplete="new-password" value={form.data.password} onChange={set('password')} error={form.errors.password} />
                <TextInput label="Confirm Password" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={set('password_confirmation')} />
                <button disabled={form.processing} className="btn-primary w-full py-2.5">Create Account</button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-500">Already have an account? <Link href="/login" className="font-semibold text-accent-600 hover:underline">Login</Link></p>
        </AuthCard>
    );
}

Register.layout = (page) => <ShopLayout>{page}</ShopLayout>;
