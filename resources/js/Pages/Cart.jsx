import { Link, router, useForm } from '@inertiajs/react';
import Breadcrumbs from '../Components/Breadcrumbs';
import Icon from '../Components/Icon';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/format';
import { t } from '../lib/i18n';

export function CouponBox({ coupon }) {
    const form = useForm({ code: '' });
    if (coupon) {
        return (
            <div className="flex items-center justify-between rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">
                <span>{t('Coupon')} <b>{coupon.code}</b> {t('applied')}</span>
                <button onClick={() => router.delete('/cart/coupon/remove', { preserveScroll: true })} className="text-xs text-red-600 hover:underline">{t('Remove')}</button>
            </div>
        );
    }
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post('/cart/coupon', { preserveScroll: true }); }}>
            <div className="flex gap-2">
                <input className="input" placeholder={t('Promo / Coupon code')} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} aria-label={t('Coupon code')} />
                <button disabled={form.processing || !form.data.code} className="btn-primary">{t('Apply')}</button>
            </div>
            {form.errors.code && <p className="mt-1 text-xs text-red-600">{form.errors.code}</p>}
        </form>
    );
}

export default function Cart({ cart }) {
    const update = (item, quantity) => router.patch(`/cart/${item.id}`, { quantity }, { preserveScroll: true });

    return (
        <div className="container-x py-4">
            <Seo title={t('Shopping Cart')} />
            <Breadcrumbs items={[{ name: 'Shopping Cart' }]} />
            <h1 className="mb-4 text-2xl font-bold">{t('Shopping Cart')}</h1>

            {cart.items.length === 0 ? (
                <div className="card p-12 text-center">
                    <Icon name="cart" className="mx-auto mb-3 h-12 w-12 text-slate-300" />
                    <p className="text-slate-500">{t('Your shopping cart is empty!')}</p>
                    <Link href="/" className="btn-primary mt-4">{t('Continue Shopping')}</Link>
                </div>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <div className="card overflow-x-auto">
                        <table className="table-x min-w-[560px]">
                            <thead>
                                <tr><th>{t('Product')}</th><th className="w-36">{t('Quantity')}</th><th className="text-right">{t('Unit Price')}</th><th className="text-right">{t('Total')}</th><th /></tr>
                            </thead>
                            <tbody>
                                {cart.items.map((item) => (
                                    <tr key={item.id}>
                                        <td>
                                            <div className="flex items-center gap-3">
                                                <img src={item.product.image_url} alt="" className="h-14 w-14 rounded object-cover" />
                                                <div>
                                                    <Link href={`/product/${item.product.slug}`} className="font-medium hover:text-accent-600">{item.product.name}</Link>
                                                    {item.variant && <div className="text-xs font-medium text-brand-600">{item.variant.name}</div>}
                                                    {!item.product.purchasable && <p className="text-xs text-red-600">Not available — please remove</p>}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex w-28 items-center rounded-md border border-slate-300">
                                                <button onClick={() => update(item, item.quantity - 1)} className="px-2 py-1" aria-label={t('Decrease')}><Icon name="minus" className="h-3.5 w-3.5" /></button>
                                                <span className="flex-1 text-center">{item.quantity}</span>
                                                <button onClick={() => update(item, item.quantity + 1)} className="px-2 py-1" aria-label={t('Increase')}><Icon name="plus" className="h-3.5 w-3.5" /></button>
                                            </div>
                                        </td>
                                        <td className="text-right">{money(item.product.price)}</td>
                                        <td className="text-right font-semibold">{money(item.total)}</td>
                                        <td className="text-right">
                                            <button onClick={() => router.delete(`/cart/${item.id}`, { preserveScroll: true })} className="text-slate-400 hover:text-red-600" aria-label={t('Remove')}><Icon name="trash" className="h-4 w-4" /></button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="space-y-4">
                        <div className="card p-4"><CouponBox coupon={cart.coupon} /></div>
                        <div className="card p-4 text-sm">
                            <div className="flex justify-between py-1"><span>{t('Sub-Total')}</span><span>{money(cart.subtotal)}</span></div>
                            {cart.discount > 0 && <div className="flex justify-between py-1 text-green-700"><span>{t('Discount')}</span><span>-{money(cart.discount)}</span></div>}
                            <div className="mt-2 flex justify-between border-t border-slate-200 pt-2 text-base font-bold"><span>{t('Total')}</span><span className="text-accent-600">{money(cart.subtotal - cart.discount)}</span></div>
                            <p className="mt-1 text-xs text-slate-500">{t('Delivery charge is calculated at checkout.')}</p>
                            <Link href="/checkout" className="btn-accent mt-4 w-full">{t('Confirm Order')}</Link>
                            <Link href="/" className="btn-outline mt-2 w-full">{t('Continue Shopping')}</Link>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

Cart.layout = (page) => <ShopLayout>{page}</ShopLayout>;
