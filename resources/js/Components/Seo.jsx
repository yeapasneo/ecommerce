import { Head, usePage } from '@inertiajs/react';
import { createElement } from 'react';

/**
 * Renders the head tags the server built for this page (App\Support\Seo), keyed with
 * head-key so they replace the server-rendered copies instead of duplicating them.
 * `title` is only used when the controller didn't set one (e.g. admin and account pages).
 */
export default function Seo({ title }) {
    const { seo, settings } = usePage().props;
    const site = settings?.site_name ?? 'TechNest';

    if (!seo) {
        return (
            <Head>
                <title>{title ? `${title} | ${site}` : site}</title>
                <meta head-key="robots" name="robots" content="noindex, nofollow" />
            </Head>
        );
    }

    const fullTitle = seo.explicit || !title ? seo.title : `${title} | ${site}`;
    return (
        <Head>
            <title>{fullTitle}</title>
            {seo.tags.map((t) =>
                t.tag === 'script'
                    ? createElement('script', { key: t.key, 'head-key': t.key, ...t.attrs, dangerouslySetInnerHTML: { __html: t.html } })
                    : createElement(t.tag, { key: t.key, 'head-key': t.key, ...t.attrs }),
            )}
        </Head>
    );
}
