/**
 * Sends page views and e-commerce events to whichever trackers the store has configured
 * (GA4 via gtag, Google Tag Manager via dataLayer, Meta Pixel via fbq). Scripts are
 * injected by resources/views/app.blade.php, so every call is a no-op when they're absent.
 */

const PIXEL_EVENTS = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase' };

const sent = new WeakSet();
let lastUrl = null;

function pixelParams(params) {
    const items = params.items ?? [];
    return {
        value: params.value,
        currency: params.currency ?? 'BDT',
        content_type: 'product',
        content_ids: items.map((i) => i.item_id),
        contents: items.map((i) => ({ id: i.item_id, quantity: i.quantity ?? 1, item_price: i.price })),
        num_items: items.reduce((n, i) => n + (i.quantity ?? 1), 0),
    };
}

export function track(event, params = {}) {
    if (typeof window === 'undefined') return;
    if (window.gtag) window.gtag('event', event, params);
    if (window.dataLayer) {
        window.dataLayer.push({ ecommerce: null }); // GTM: clear the previous ecommerce object
        window.dataLayer.push({ event, ecommerce: params });
    }
    if (window.fbq && PIXEL_EVENTS[event]) window.fbq('track', PIXEL_EVENTS[event], pixelParams(params));
}

export function pageView(url) {
    if (typeof window === 'undefined' || url === lastUrl) return;
    lastUrl = url;
    const location = window.location.origin + url;
    if (window.gtag) window.gtag('event', 'page_view', { page_location: location, page_title: document.title });
    if (window.dataLayer) window.dataLayer.push({ event: 'page_view', page_location: location, page_title: document.title });
    if (window.fbq) window.fbq('track', 'PageView');
}

/** Called after every Inertia page load. */
export function handlePage(page) {
    if (!page || page.url.startsWith('/admin')) return;
    // Wait a tick so <Head> has updated document.title.
    setTimeout(() => pageView(page.url), 0);
    const events = page.props.analytics;
    if (Array.isArray(events) && events.length && !sent.has(events)) {
        sent.add(events); // partial reloads keep the same array; don't resend it
        events.forEach((e) => setTimeout(() => track(e.event, e.params), 0));
    }
}
