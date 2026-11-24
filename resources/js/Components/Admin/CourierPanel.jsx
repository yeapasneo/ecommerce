import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { TextArea, TextInput } from '../Field';
import Icon from '../Icon';
import Modal from '../Modal';
import { dateTime, money, titleCase } from '../../lib/format';
import { Panel } from './UI';

const COURIER_TONE = { steadfast: 'bg-emerald-50 text-emerald-700', pathao: 'bg-red-50 text-red-600' };

const statusTone = (s) =>
    ['delivered', 'partial_delivered', 'partial_delivery'].includes(s)
        ? 'bg-green-50 text-green-700'
        : ['cancelled', 'return', 'returned', 'delivery_failed', 'pickup_cancelled', 'pickup_failed'].includes(s)
          ? 'bg-red-50 text-red-700'
          : ['hold', 'on_hold'].includes(s)
            ? 'bg-amber-50 text-amber-700'
            : 'bg-blue-50 text-blue-700';

async function getJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(body.message || 'Request failed');
    return body;
}

function LocationSelect({ id, label, value, onChange, items, loading, error, placeholder, optional }) {
    return (
        <div>
            <label className="label" htmlFor={id}>{label}{!optional && '*'}</label>
            <select id={id} className="input" value={value ?? ''} onChange={(e) => onChange(e.target.value)} disabled={loading || !items}>
                <option value="">{loading ? 'Loading…' : placeholder}</option>
                {(items ?? []).map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function BookingForm({ order, courier, label, defaults, onDone }) {
    const form = useForm({
        courier,
        recipient_name: `${order.first_name} ${order.last_name ?? ''}`.trim(),
        recipient_phone: order.phone,
        recipient_address: `${order.address}, ${order.city}`,
        cod_amount: order.payment_status === 'paid' ? 0 : order.total,
        note: order.notes ?? '',
        city_id: '',
        zone_id: '',
        area_id: '',
        weight: defaults.weight,
        delivery_type: defaults.delivery_type,
    });
    const [lists, setLists] = useState({ cities: null, zones: null, areas: null });
    const [loading, setLoading] = useState('');
    const [loadError, setLoadError] = useState('');
    const isPathao = courier === 'pathao';
    const e = form.errors;

    const load = (kind, query) => {
        setLoading(kind);
        setLoadError('');
        return getJson(`/admin/couriers/pathao/locations${query}`)
            .then((items) => { setLists((l) => ({ ...l, [kind]: items })); return items; })
            .catch((err) => setLoadError(err.message))
            .finally(() => setLoading(''));
    };

    // Cities on open, pre-selecting the order's city when the names match.
    useEffect(() => {
        if (!isPathao) return;
        load('cities', '').then((cities) => {
            const match = cities?.find((c) => c.name.toLowerCase() === String(order.city).toLowerCase().trim());
            if (match) form.setData('city_id', String(match.id));
        });
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!isPathao) return;
        setLists((l) => ({ ...l, zones: null, areas: null }));
        form.setData((d) => ({ ...d, zone_id: '', area_id: '' }));
        if (form.data.city_id) load('zones', `?city=${form.data.city_id}`);
    }, [form.data.city_id]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!isPathao) return;
        setLists((l) => ({ ...l, areas: null }));
        form.setData('area_id', '');
        if (form.data.zone_id) load('areas', `?zone=${form.data.zone_id}`);
    }, [form.data.zone_id]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = (ev) => {
        ev.preventDefault();
        form.post(`/admin/orders/${order.id}/courier`, { preserveScroll: true, onSuccess: () => onDone() });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <TextInput label="Recipient name*" value={form.data.recipient_name} onChange={(ev) => form.setData('recipient_name', ev.target.value)} error={e.recipient_name} />
            <TextInput label="Mobile*" value={form.data.recipient_phone} onChange={(ev) => form.setData('recipient_phone', ev.target.value)} error={e.recipient_phone} />
            <TextArea label="Delivery address*" className="sm:col-span-2" rows={2} value={form.data.recipient_address} onChange={(ev) => form.setData('recipient_address', ev.target.value)} error={e.recipient_address} />
            {isPathao && (
                <>
                    {loadError && <p className="rounded-lg bg-red-50 p-2 text-sm text-red-700 sm:col-span-2">{loadError}</p>}
                    <LocationSelect id="pathao-city" label="City" value={form.data.city_id} onChange={(v) => form.setData('city_id', v)} items={lists.cities} loading={loading === 'cities'} error={e.city_id} placeholder="Choose city" />
                    <LocationSelect id="pathao-zone" label="Zone" value={form.data.zone_id} onChange={(v) => form.setData('zone_id', v)} items={lists.zones} loading={loading === 'zones'} error={e.zone_id} placeholder={form.data.city_id ? 'Choose zone' : 'Choose a city first'} />
                    <LocationSelect id="pathao-area" label="Area" optional value={form.data.area_id} onChange={(v) => form.setData('area_id', v)} items={lists.areas} loading={loading === 'areas'} error={e.area_id} placeholder={form.data.zone_id ? 'Any area (optional)' : 'Choose a zone first'} />
                    <TextInput label="Weight (kg)" type="number" step="0.1" min="0.5" max="10" value={form.data.weight} onChange={(ev) => form.setData('weight', ev.target.value)} error={e.weight} />
                    <div className="sm:col-span-2">
                        <span className="label">Delivery type</span>
                        <div className="flex gap-2">
                            {[[48, 'Normal delivery'], [12, 'On-demand (same day)']].map(([v, l]) => (
                                <label key={v} className={`flex flex-1 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm ${Number(form.data.delivery_type) === v ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-slate-200'}`}>
                                    <input type="radio" name="delivery_type" className="accent-primary-600" checked={Number(form.data.delivery_type) === v} onChange={() => form.setData('delivery_type', v)} /> {l}
                                </label>
                            ))}
                        </div>
                    </div>
                </>
            )}
            <div>
                <TextInput label="Cash to collect (৳)*" type="number" step="1" min="0" value={form.data.cod_amount} onChange={(ev) => form.setData('cod_amount', ev.target.value)} error={e.cod_amount} />
                <p className="mt-1 text-xs text-slate-500">{order.payment_status === 'paid' ? 'Order is paid online, so nothing to collect.' : `Order total ${money(order.total)}, unpaid.`}</p>
            </div>
            <TextInput label="Note for rider" value={form.data.note} onChange={(ev) => form.setData('note', ev.target.value)} error={e.note} />
            <div className="-mx-6 -mb-6 mt-2 flex justify-end gap-2 border-t border-slate-100 px-6 py-4 sm:col-span-2">
                <button type="button" onClick={onDone} className="btn-cancel rounded-lg"><Icon name="x" className="h-4 w-4" /> Cancel</button>
                <button disabled={form.processing} className="btn-primary"><Icon name="truck" className="h-4 w-4" /> {form.processing ? `Booking with ${label}…` : `Book with ${label}`}</button>
            </div>
        </form>
    );
}

export default function CourierPanel({ order, couriers, defaults }) {
    const [booking, setBooking] = useState(null);
    const [refreshing, setRefreshing] = useState(false);
    const shipped = !!order.courier_consignment_id;
    const names = { steadfast: 'Steadfast', pathao: 'Pathao' };

    return (
        <Panel title="Courier" icon="truck">
            {shipped ? (
                <div className="space-y-4">
                    <div className="flex items-center gap-3">
                        <span className={`grid h-10 w-10 place-items-center rounded-xl text-sm font-black ${COURIER_TONE[order.courier] ?? 'bg-slate-100'}`}>{(names[order.courier] ?? order.courier).slice(0, 2)}</span>
                        <div className="mr-auto min-w-0">
                            <p className="font-semibold text-slate-900">{names[order.courier] ?? titleCase(order.courier)}</p>
                            <p className="text-xs text-slate-500">Booked {dateTime(order.courier_sent_at)}</p>
                        </div>
                        <span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${statusTone(order.courier_status)}`}>{titleCase(order.courier_status ?? 'unknown')}</span>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-2 rounded-xl border border-slate-200 p-4 text-sm">
                        <dt className="text-slate-500">Consignment</dt><dd className="text-right font-mono">{order.courier_consignment_id}</dd>
                        {order.courier_tracking_code && order.courier_tracking_code !== order.courier_consignment_id && (<><dt className="text-slate-500">Tracking code</dt><dd className="text-right font-mono">{order.courier_tracking_code}</dd></>)}
                        <dt className="text-slate-500">Cash to collect</dt><dd className="text-right">{money(order.courier_cod)}</dd>
                        {order.courier_fee != null && (<><dt className="text-slate-500">Delivery fee</dt><dd className="text-right">{money(order.courier_fee)}</dd></>)}
                        <dt className="text-slate-500">Last update</dt><dd className="text-right">{dateTime(order.courier_status_at)}</dd>
                    </dl>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" disabled={refreshing} onClick={() => router.post(`/admin/orders/${order.id}/courier/refresh`, {}, { preserveScroll: true, onStart: () => setRefreshing(true), onFinish: () => setRefreshing(false) })} className="btn-outline">
                            <Icon name="refresh" className="h-4 w-4" /> {refreshing ? 'Checking…' : 'Refresh status'}
                        </button>
                        {order.courier_tracking_url && <a href={order.courier_tracking_url} target="_blank" rel="noreferrer" className="btn-outline"><Icon name="external" className="h-4 w-4" /> Track parcel</a>}
                    </div>
                </div>
            ) : Object.keys(couriers).length === 0 ? (
                <p className="text-sm text-slate-500">No courier is set up yet. Add Steadfast or Pathao in <Link href="/admin/settings#couriers" className="font-medium text-primary-700 hover:underline">Settings → Couriers</Link>.</p>
            ) : order.status === 'cancelled' ? (
                <p className="text-sm text-slate-500">Cancelled orders can't be shipped.</p>
            ) : (
                <div className="space-y-3">
                    <p className="text-sm text-slate-500">Book a pickup. The order moves to Shipped and the courier's updates appear in the status history.</p>
                    <div className="flex flex-wrap gap-2">
                        {Object.entries(couriers).map(([key, label]) => (
                            <button key={key} type="button" onClick={() => setBooking(key)} className="btn-primary"><Icon name="truck" className="h-4 w-4" /> Send to {label}</button>
                        ))}
                    </div>
                </div>
            )}

            <Modal open={booking !== null} onClose={() => setBooking(null)} title={booking ? `Send to ${couriers[booking]}` : ''} wide>
                {booking && <BookingForm key={booking} order={order} courier={booking} label={couriers[booking]} defaults={defaults} onDone={() => setBooking(null)} />}
            </Modal>
        </Panel>
    );
}
