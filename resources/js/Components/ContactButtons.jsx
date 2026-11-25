import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { messengerUrl, whatsappUrl } from '../lib/contact';
import { t } from '../lib/i18n';

const WA = 'M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.5c-.1.2-.3.3-.1.6.2.3.8 1.4 1.8 2.2 1.3 1.1 2.3 1.5 2.6 1.6.3.2.5.1.7-.1l.9-1.1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.6.3.1.2.1.7-.1 1.4z';
const MS = 'M12 2C6.4 2 2 6.1 2 11.3c0 2.9 1.4 5.5 3.7 7.2V22l3.4-1.9c.9.3 1.9.4 2.9.4 5.6 0 10-4.1 10-9.3S17.6 2 12 2zm1 12.5-2.6-2.7-5 2.7 5.5-5.8 2.6 2.7 4.9-2.7-5.4 5.8z';

/** Floating WhatsApp / Messenger buttons (above the AI chat button when it is on). */
export default function ContactButtons() {
    const { settings, assistant } = usePage().props;
    const [chatOpen, setChatOpen] = useState(false);
    useEffect(() => {
        const on = (e) => setChatOpen(!!e.detail);
        window.addEventListener('assistant:open', on);
        return () => window.removeEventListener('assistant:open', on);
    }, []);
    const wa = whatsappUrl(settings, t('Hi, I have a question about a product.'));
    const ms = messengerUrl(settings);
    if ((!wa && !ms) || chatOpen) return null;
    return (
        <div className={`no-print fixed right-5 z-40 flex flex-col gap-2 ${assistant ? 'bottom-20' : 'bottom-5'}`}>
            {wa && (
                <a href={wa} target="_blank" rel="noopener noreferrer" className="grid h-12 w-12 place-items-center rounded-full bg-[#25D366] text-white shadow-lg transition hover:scale-105" aria-label={t('Chat on WhatsApp')} title={t('Chat on WhatsApp')}>
                    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor" aria-hidden="true"><path d={WA} /></svg>
                </a>
            )}
            {ms && (
                <a href={ms} target="_blank" rel="noopener noreferrer" className="grid h-12 w-12 place-items-center rounded-full bg-[#0084FF] text-white shadow-lg transition hover:scale-105" aria-label={t('Chat on Messenger')} title={t('Chat on Messenger')}>
                    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor" aria-hidden="true"><path d={MS} /></svg>
                </a>
            )}
        </div>
    );
}
