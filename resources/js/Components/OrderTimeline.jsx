import Icon from './Icon';
import { dateTime, titleCase } from '../lib/format';

const steps = ['pending', 'processing', 'shipped', 'delivered'];

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
