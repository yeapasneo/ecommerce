import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { handlePage } from './lib/analytics';
import { setLocale } from './lib/i18n';

createInertiaApp({
    title: (title) => title,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        setLocale(props.initialPage.props.locale, props.initialPage.props.translations);
        createRoot(el).render(<App {...props} />);
        handlePage(props.initialPage);
        router.on('navigate', (event) => handlePage(event.detail.page));
        // Form posts that redirect back to the same URL don't fire "navigate".
        router.on('success', (event) => handlePage(event.detail.page));
    },
    progress: { color: '#f97316' },
});
