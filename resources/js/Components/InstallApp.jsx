import { useEffect, useState } from 'react';
import { t } from '../lib/i18n';
import Icon from './Icon';

/** "Install app" button, shown when the browser offers to install the shop (Android Chrome, desktop Chrome/Edge). */
export default function InstallApp({ className = '' }) {
    const [prompt, setPrompt] = useState(null);
    useEffect(() => {
        const save = (e) => { e.preventDefault(); setPrompt(e); };
        const done = () => setPrompt(null);
        window.addEventListener('beforeinstallprompt', save);
        window.addEventListener('appinstalled', done);
        return () => { window.removeEventListener('beforeinstallprompt', save); window.removeEventListener('appinstalled', done); };
    }, []);
    if (!prompt) return null;
    return (
        <button type="button" onClick={() => { prompt.prompt(); prompt.userChoice.finally(() => setPrompt(null)); }} className={`inline-flex items-center gap-2 rounded-lg bg-accent-500 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-600 ${className}`}>
            <Icon name="download" className="h-4 w-4" /> {t('Install our app')}
        </button>
    );
}
