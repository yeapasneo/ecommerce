import { Link } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import AccountLayout from '../../Layouts/AccountLayout';
import { date, money } from '../../lib/format';
import { t } from '../../lib/i18n';

export default function Points({ balance, value, earnPer, minRedeem, maxPercent, loyalty, history, types, referral }) {
    const [copied, setCopied] = useState(false);
    const copy = () => navigator.clipboard?.writeText(referral.link).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2000); });
    const share = `${t('Join me at this shop and get bonus points on your first order:')} ${referral?.link}`;

    return (
        <AccountLayout title={t('Points & Referrals')}>
            {loyalty && (
                <div className="card mb-4 flex flex-wrap items-center gap-6 p-5">
                    <div>
                        <p className="text-sm text-slate-500">{t('Your points')}</p>
                        <p className="text-3xl font-bold text-brand-700">{balance.toLocaleString()}</p>
                        <p className="text-sm text-slate-600">= {money(balance * value)} {t('off your next order')}</p>
                    </div>
                    <ul className="min-w-0 flex-1 space-y-1 text-sm text-slate-600">
                        <li>• {t('Earn 1 point for every')} {money(earnPer)} {t('you spend, once the order is delivered.')}</li>
                        <li>• {t('Use them at checkout from')} {minRedeem} {t('points, for up to')} {maxPercent}% {t('of the items.')}</li>
                        <li>• 1 {t('point')} = {money(value)}.</li>
                    </ul>
                </div>
            )}

            {referral && (
                <div className="card mb-4 p-5">
                    <h2 className="font-semibold">{t('Invite friends')}</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {t('Share your link. When a friend signs up with it and their first order is delivered, they get')} <b>{referral.welcome} {t('points')}</b> {t('and you get')} <b>{referral.bonus} {t('points')}</b>.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <input readOnly value={referral.link} className="input min-w-0 flex-1 font-mono text-sm" aria-label={t('Your referral link')} onFocus={(e) => e.target.select()} />
                        <button type="button" onClick={copy} className="btn-primary"><Icon name={copied ? 'check' : 'file'} className="h-4 w-4" /> {copied ? t('Copied') : t('Copy')}</button>
                        <a href={`https://wa.me/?text=${encodeURIComponent(share)}`} target="_blank" rel="noopener noreferrer" className="btn-outline">WhatsApp</a>
                        <a href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referral.link)}`} target="_blank" rel="noopener noreferrer" className="btn-outline">Facebook</a>
                    </div>
                    <p className="mt-2 text-xs text-slate-500">{t('Your code')}: <b className="font-mono">{referral.code}</b> · {referral.invited} {t('friend(s) joined')}</p>
                </div>
            )}

            {loyalty && (
                <div className="card overflow-x-auto">
                    <table className="table-x">
                        <thead><tr><th>{t('Date')}</th><th>{t('Details')}</th><th className="text-right">{t('Points')}</th><th className="text-right">{t('Balance')}</th></tr></thead>
                        <tbody>
                            {history.length === 0 && <tr><td colSpan={4} className="py-6 text-center text-slate-500">{t('No points yet. You earn points when your orders are delivered.')}</td></tr>}
                            {history.map((h) => (
                                <tr key={h.id}>
                                    <td className="whitespace-nowrap">{date(h.created_at)}</td>
                                    <td>{t(types[h.type] ?? h.type)}{h.order && <> · <Link href={`/account/orders/${h.order.order_number}`} className="text-accent-600 hover:underline">#{h.order.order_number}</Link></>}{h.note && !h.order && <span className="text-slate-500"> · {h.note}</span>}</td>
                                    <td className={`text-right font-semibold ${h.points > 0 ? 'text-green-700' : 'text-red-600'}`}>{h.points > 0 ? `+${h.points}` : h.points}</td>
                                    <td className="text-right">{h.balance_after}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AccountLayout>
    );
}
