import Icon from './Icon';
import { dateTime, titleCase } from '../lib/format';

const steps = ['pending', 'processing', 'shipped', 'delivered'];
const COURIERS = { steadfast: 'Steadfast', pathao: 'Pathao' };

export default function OrderTimeline({ order }) {
    const cancelled = order.status === 'cancelled';
    const current = steps.indexOf(order.status);
    return (
        <div>
            {cancelled ? (
                <p className="rounded-md bg-red-50 p-3 text-sm font-semibold text-red-700">This order has been cancelled.</p>
            ) : (
                <ol className="grid grid-cols-4 gap-2">
                    {steps.map((s, i) => (
                        <li key={s} className="text-center">
                            <span className={`mx-auto grid h-9 w-9 place-items-center rounded-full ${i <= current ? 'bg-green-600 text-white' : 'bg-slate-200 text-slate-400'}`}>
                                <Icon name={i <= current ? 'check' : 'box'} className="h-4 w-4" />
                            </span>
                            <span className={`mt-1 block text-xs ${i <= current ? 'font-semibold text-slate-800' : 'text-slate-400'}`}>{titleCase(s)}</span>
                        </li>
                    ))}
                </ol>
            )}
            {order.courier_consignment_id && order.status !== 'cancelled' && (
                <div className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md bg-blue-50 p-3 text-sm text-blue-900">
                    <Icon name="truck" className="h-4 w-4 shrink-0" />
                    <span>
                        With <b>{COURIERS[order.courier] ?? titleCase(order.courier)}</b>
                        {order.courier_status && <> · {titleCase(order.courier_status)}</>}
                        {' · '}Consignment <span className="font-mono">{order.courier_tracking_code || order.courier_consignment_id}</span>
                    </span>
                    {order.courier_tracking_url && (
                        <a href={order.courier_tracking_url} target="_blank" rel="noreferrer" className="ml-auto inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline">
                            Track parcel <Icon name="external" className="h-3.5 w-3.5" />
                        </a>
                    )}
                </div>
            )}
            {order.histories?.length > 0 && (
                <ul className="mt-5 space-y-2 border-l-2 border-slate-200 pl-4 text-sm">
                    {order.histories.map((h) => (
                        <li key={h.id}>
                            <span className="font-medium">{titleCase(h.status)}</span>
                            <span className="ml-2 text-xs text-slate-400">{dateTime(h.created_at)}</span>
                            {h.comment && <p className="text-slate-600">{h.comment}</p>}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
