import { Link } from '@inertiajs/react';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { t } from '../lib/i18n';

const messages = {
    403: ['Forbidden', 'Sorry, you are not allowed to access this page.'],
    404: ['Page Not Found', 'Sorry, the page you are looking for could not be found.'],
    500: ['Server Error', 'Whoops, something went wrong on our end.'],
    503: ['Service Unavailable', 'We are doing some maintenance. Please check back soon.'],
};

export default function Error({ status }) {
    const [title, text] = messages[status] ?? messages[500];
    return (
        <div className="container-x py-16 text-center">
            <Seo title={title} />
            <p className="text-6xl font-black text-brand-700">{status}</p>
            <h1 className="mt-2 text-2xl font-bold">{title}</h1>
            <p className="mt-2 text-slate-500">{text}</p>
            <Link href="/" className="btn-primary mt-6">{t('Back to Home')}</Link>
        </div>
    );
}

Error.layout = (page) => <ShopLayout>{page}</ShopLayout>;
