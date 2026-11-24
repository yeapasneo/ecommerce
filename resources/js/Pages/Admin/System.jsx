import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '../../Components/Admin/UI';
import Icon from '../../Components/Icon';
import AdminLayout from '../../Layouts/AdminLayout';

const STATE = {
    ok: ['check', 'bg-green-100 text-green-700', 'OK'],
    warn: ['info', 'bg-amber-100 text-amber-700', 'Check'],
    fail: ['x', 'bg-red-100 text-red-700', 'Fix'],
};

export default function System({ checks, backups }) {
    const [busy, setBusy] = useState(null);
    const run = (url, key) => router.post(url, {}, { preserveScroll: true, onStart: () => setBusy(key), onFinish: () => setBusy(null) });
    const groups = [...new Set(checks.map((c) => c.group))];
    const counts = { ok: 0, warn: 0, fail: 0 };
    checks.forEach((c) => { counts[c.state]++; });
    const hasFailed = checks.some((c) => c.label === 'Failed jobs' && c.state !== 'ok');

    return (
        <AdminLayout
            title="System Status"
            subtitle="Go-live checklist for this server"
            actions={
                <>
                    {hasFailed && <button type="button" onClick={() => run('/admin/system/retry-failed', 'retry')} disabled={busy} className="btn-outline"><Icon name="refresh" className="h-4 w-4" /> Retry failed jobs</button>}
                    <button type="button" onClick={() => run('/admin/system/backup', 'backup')} disabled={busy} className="btn-primary"><Icon name="file" className="h-4 w-4" /> {busy === 'backup' ? 'Backing up…' : 'Back up now'}</button>
                </>
            }
        >
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                {[['fail', 'Need fixing'], ['warn', 'Worth checking'], ['ok', 'Ready']].map(([k, l]) => (
                    <div key={k} className="panel flex items-center gap-4 p-5">
                        <span className={`grid h-11 w-11 place-items-center rounded-xl ${STATE[k][1]}`}><Icon name={STATE[k][0]} /></span>
                        <div><p className="text-sm text-slate-500">{l}</p><p className="text-2xl font-bold text-slate-900">{counts[k]}</p></div>
                    </div>
                ))}
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-[1fr_380px]">
                <div className="space-y-6">
                    {groups.map((g) => (
                        <Panel key={g} title={g} bodyClassName="pb-2">
                            <ul className="divide-y divide-slate-100 border-t border-slate-100">
                                {checks.filter((c) => c.group === g).map((c) => {
                                    const [icon, tone, word] = STATE[c.state];
                                    return (
                                        <li key={c.label} className="flex gap-4 px-6 py-4">
                                            <span className={`mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full ${tone}`} aria-label={word}><Icon name={icon} className="h-3.5 w-3.5" strokeWidth={2.5} /></span>
                                            <div className="min-w-0">
                                                <p className="font-medium text-slate-900">{c.label}</p>
                                                <p className="text-sm text-slate-600">{c.detail}</p>
                                                {c.state !== 'ok' && c.fix && <p className="mt-1 break-words text-xs text-slate-500"><span className="font-medium text-slate-700">How to fix:</span> {c.fix}</p>}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </Panel>
                    ))}
                </div>
                <Panel title="Backups" icon="file">
                    {backups.length === 0 ? (
                        <p className="text-sm text-slate-500">No backups yet. They run every night at 02:30, or use "Back up now".</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {backups.map((b) => (
                                <li key={b.name} className="flex items-center gap-3 py-2.5 text-sm">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-mono text-xs text-slate-800">{b.name}</p>
                                        <p className="text-xs text-slate-500">{b.size} · {b.ago}</p>
                                    </div>
                                    <a href={`/admin/system/backups/${encodeURIComponent(b.name)}`} className="icon-btn h-8 w-8" aria-label={`Download ${b.name}`}><Icon name="external" className="h-3.5 w-3.5" /></a>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-3 text-xs text-slate-500">Restore with: <code>pg_restore --clean -d DBNAME file.dump</code>. Keep copies somewhere other than this server.</p>
                </Panel>
            </div>
        </AdminLayout>
    );
}
