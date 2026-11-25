/**
 * Storefront translations. The server shares the dictionary for the visitor's language
 * (lang/bn.json); switching language reloads the page, so a module-level dictionary is enough.
 * Keys are the English text, so a missing translation simply shows English.
 */
let dict = {};
let current = 'en';

export function setLocale(locale, translations) {
    current = locale || 'en';
    dict = translations || {};
}

export function locale() {
    return current;
}

/** t('Hello :name', { name: 'Rahim' }) */
export function t(text, vars) {
    let out = dict[text] ?? text;
    if (vars) {
        for (const [k, v] of Object.entries(vars)) out = out.replaceAll(`:${k}`, v);
    }
    return out;
}
