import { Head, usePage } from '@inertiajs/react';

export default function Seo({ title, description }) {
    const { settings } = usePage().props;
    const site = settings?.site_name ?? 'TechNest';
    return (
        <Head>
            <title>{title ? `${title} | ${site}` : `${site} - ${settings?.tagline ?? ''}`}</title>
            {description && <meta name="description" content={description} />}
        </Head>
    );
}
