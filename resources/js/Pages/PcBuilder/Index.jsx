import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import Modal from '../../Components/Modal';
import Seo from '../../Components/Seo';
import ShopLayout from '../../Layouts/ShopLayout';
import { money } from '../../lib/format';

const icons = { cpu: 'cpu', 'cpu-cooler': 'fan', motherboard: 'board', ram: 'memory', storage: 'storage', 'graphics-card': 'gpu', 'power-supply': 'power', casing: 'case', monitor: 'monitor', keyboard: 'keyboard', mouse: 'mouse', headphone: 'headphone' };

function Row({ c }) {
    return (
        <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center">
            <div className="flex flex-1 items-center gap-4">
                <span className="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-md bg-slate-100 text-slate-500">
                    {c.product ? <img src={c.product.image_url} alt="" className="h-full w-full object-cover" /> : <Icon name={icons[c.slug] ?? 'box'} className="h-7 w-7" />}
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold">
                        {c.name} {c.is_required && <span className="ml-1 rounded bg-slate-200 px-1.5 text-[10px] font-medium uppercase text-slate-600">Required</span>}
                    </p>
                    {c.product ? (
                        <Link href={`/product/${c.product.slug}`} className="line-clamp-1 text-sm text-slate-600 hover:text-accent-600">{c.product.name}</Link>
                    ) : (
                        <div className="mt-1 h-2 w-40 rounded bg-slate-100" />
                    )}
                </div>
            </div>
            <div className="flex items-center gap-3 sm:justify-end">
                {c.product && <span className="font-semibold text-slate-800">{money(c.product.price)}</span>}
                {c.product ? (
                    <>
                        <Link href={`/pc-builder/choose/${c.slug}`} className="btn-outline btn-sm" aria-label="Change"><Icon name="refresh" className="h-4 w-4" /></Link>
                        <button onClick={() => router.delete(`/pc-builder/remove/${c.slug}`, { preserveScroll: true })} className="btn-outline btn-sm hover:text-red-600" aria-label="Remove"><Icon name="x" className="h-4 w-4" /></button>
                    </>
                ) : (
                    <Link href={`/pc-builder/choose/${c.slug}`} className="btn-outline btn-sm border-brand-500 text-brand-600">Choose</Link>
                )}
            </div>
        </div>
    );
}

export default function PcBuilder({ components, total, savedPcs }) {
    const { auth } = usePage().props;
    const [saveOpen, setSaveOpen] = useState(false);
    const form = useForm({ name: '' });
    const core = components.filter((c) => c.section === 'core');
    const peripherals = components.filter((c) => c.section === 'peripherals');
    const count = components.filter((c) => c.product).length;

    return (
        <div className="container-x max-w-5xl py-6">
            <Seo title="PC Builder - Build Your Own Computer" />
            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-brand-900 p-4 text-white">
                    <div className="mr-auto">
                        <h1 className="text-xl font-bold">PC Builder</h1>
                        <p className="text-sm text-slate-300">Build your own computer, piece by piece</p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-bold text-accent-400">{money(total)}</p>
                        <p className="text-xs text-slate-300">{count} Items</p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2 border-b border-slate-200 p-3">
                    <button onClick={() => router.post('/pc-builder/add-to-cart')} disabled={!count} className="btn-accent btn-sm"><Icon name="cart" className="h-4 w-4" /> Add to Cart</button>
                    <button onClick={() => (auth.user ? setSaveOpen(true) : router.visit('/login'))} disabled={!count} className="btn-outline btn-sm">Save PC</button>
                    <button onClick={() => window.print()} className="btn-outline btn-sm"><Icon name="print" className="h-4 w-4" /> Print</button>
                    <button onClick={() => router.delete('/pc-builder/clear')} disabled={!count} className="btn-outline btn-sm hover:text-red-600">Clear</button>
                </div>
                <div className="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Core Components</div>
                {core.map((c) => <Row key={c.slug} c={c} />)}
                <div className="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Peripherals & Others</div>
                {peripherals.map((c) => <Row key={c.slug} c={c} />)}
            </div>

            {savedPcs.length > 0 && (
                <div className="card mt-6 p-4">
                    <h2 className="mb-3 font-semibold">Your Saved Builds</h2>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {savedPcs.map((s) => (
                            <li key={s.id} className="flex items-center justify-between gap-3 py-2">
                                <span>{s.name} <span className="text-slate-500">— {money(s.total)}</span></span>
                                <span className="flex gap-2">
                                    <button onClick={() => router.post(`/pc-builder/load/${s.id}`)} className="btn-outline btn-sm">Load</button>
                                    <button onClick={() => router.delete(`/pc-builder/saved/${s.id}`, { preserveScroll: true })} className="btn-outline btn-sm hover:text-red-600">Delete</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Modal open={saveOpen} onClose={() => setSaveOpen(false)} title="Save this PC build">
                <form onSubmit={(e) => { e.preventDefault(); form.post('/pc-builder/save', { onSuccess: () => { setSaveOpen(false); form.reset(); } }); }}>
                    <input className="input" placeholder="e.g. My Gaming Rig" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                    {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                    <button disabled={form.processing} className="btn-primary mt-3 w-full">Save</button>
                </form>
            </Modal>
        </div>
    );
}

PcBuilder.layout = (page) => <ShopLayout>{page}</ShopLayout>;
