import { useForm, usePage } from '@inertiajs/react';
import Breadcrumbs from '../Components/Breadcrumbs';
import { TextArea, TextInput } from '../Components/Field';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';

export default function Contact() {
    const { settings } = usePage().props;
    const form = useForm({ name: '', email: '', phone: '', subject: '', message: '' });
    const set = (k) => (e) => form.setData(k, e.target.value);
    return (
        <div className="container-x py-4">
            <Seo title="Contact Us" />
            <Breadcrumbs items={[{ name: 'Contact Us' }]} />
            <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                <form onSubmit={(e) => { e.preventDefault(); form.post('/contact', { preserveScroll: true, onSuccess: () => form.reset() }); }} className="card grid gap-4 p-6 sm:grid-cols-2">
                    <h1 className="text-2xl font-bold sm:col-span-2">Send us a message</h1>
                    <TextInput label="Name*" value={form.data.name} onChange={set('name')} error={form.errors.name} />
                    <TextInput label="Email*" type="email" value={form.data.email} onChange={set('email')} error={form.errors.email} />
                    <TextInput label="Phone" value={form.data.phone} onChange={set('phone')} error={form.errors.phone} />
                    <TextInput label="Subject*" value={form.data.subject} onChange={set('subject')} error={form.errors.subject} />
                    <TextArea label="Message*" className="sm:col-span-2" rows={5} value={form.data.message} onChange={set('message')} error={form.errors.message} />
                    <div><button disabled={form.processing} className="btn-primary">Send Message</button></div>
                </form>
                <div className="card space-y-4 p-6 text-sm">
                    <h2 className="text-lg font-semibold">Get in touch</h2>
                    <p className="flex gap-3"><Icon name="phone" className="h-5 w-5 text-accent-500" /> {settings.phone}</p>
                    <p className="flex gap-3"><Icon name="mail" className="h-5 w-5 text-accent-500" /> {settings.email}</p>
                    <p className="flex gap-3"><Icon name="pin" className="h-5 w-5 shrink-0 text-accent-500" /> {settings.address}</p>
                </div>
            </div>
        </div>
    );
}

Contact.layout = (page) => <ShopLayout>{page}</ShopLayout>;
