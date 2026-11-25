import { router } from '@inertiajs/react';
import ProductCard from '../../Components/ProductCard';
import AccountLayout from '../../Layouts/AccountLayout';
import { t } from '../../lib/i18n';

export default function Wishlist({ items }) {
    return (
        <AccountLayout title={t('My Wishlist')}>
            {items.length === 0 && <div className="card p-10 text-center text-sm text-slate-500">{t('Your wishlist is empty.')}</div>}
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                {items.map((p) => (
                    <div key={p.id} className="relative">
                        <ProductCard product={p} />
                        <button onClick={() => router.post('/account/wishlist', { product_id: p.id }, { preserveScroll: true })} className="absolute right-2 top-2 rounded-full bg-white px-2 py-1 text-xs text-red-600 shadow">{t('Remove')}</button>
                    </div>
                ))}
            </div>
        </AccountLayout>
    );
}
