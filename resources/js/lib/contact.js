/** Links to reach the shop on WhatsApp / Messenger (numbers and page set in Settings → General). */
export const whatsappUrl = (settings, text = '') => {
    const n = String(settings?.whatsapp_number ?? '').replace(/\D/g, '');
    if (!n) return null;
    return `https://wa.me/${n}${text ? `?text=${encodeURIComponent(text)}` : ''}`;
};

export const messengerUrl = (settings, ref = '') => {
    const u = String(settings?.messenger_username ?? '').trim();
    return u ? `https://m.me/${u}${ref ? `?ref=${encodeURIComponent(ref)}` : ''}` : null;
};

export const callUrl = (settings) => (settings?.phone ? `tel:${String(settings.phone).replace(/[^\d+]/g, '')}` : null);
