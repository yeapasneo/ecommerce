import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Icon';

/** "Draft with AI": asks the server for a reply draft and hands it to onDraft. Hidden until AI is set up. */
export default function AiDraftButton({ url, onDraft, label = 'Draft with AI', className = 'btn-outline h-9 px-3' }) {
    const { aiReady } = usePage().props;
    const [state, setState] = useState(null);
    if (!aiReady) return null;

    const run = async () => {
        setState({ loading: true });
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.reply) return setState({ error: body.message || 'The AI could not write a reply. Try again.' });
            onDraft(body.reply);
            setState(null);
        } catch {
            setState({ error: 'Could not reach the server.' });
        }
    };

    return (
        <>
            <button type="button" onClick={run} disabled={state?.loading} className={className} title="Writes a draft for you to check before sending">
                <Icon name="sparkle" className="h-4 w-4" /> {state?.loading ? 'Writing…' : label}
            </button>
            {state?.error && <span className="basis-full text-xs text-red-600" role="alert">{state.error}</span>}
        </>
    );
}
