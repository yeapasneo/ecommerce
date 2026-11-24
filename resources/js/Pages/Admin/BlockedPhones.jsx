import CrudPage from '../../Components/CrudPage';
import { dateTime } from '../../lib/format';

export default function BlockedPhones({ phones }) {
    return (
        <CrudPage
            title="Blocked Numbers"
            singular="Number"
            description="Customers marked as fraud. Checkout blocks them as set in Settings → Fraud check."
            endpoint="/admin/blocked-phones"
            rows={phones.map((p) => ({ ...p, name: p.phone }))}
            emptyRow={{ phone: '', reason: '' }}
            columns={[
                { label: 'Number', render: (p) => <span className="font-mono font-medium">{p.phone}</span> },
                { label: 'Reason', render: (p) => <span className="text-slate-600">{p.reason || '—'}</span> },
                { label: 'Added', render: (p) => <span className="text-xs text-slate-500">{dateTime(p.created_at)}{p.creator && ` · ${p.creator.name}`}</span> },
            ]}
            fields={[
                { name: 'phone', label: 'Mobile number', hint: 'e.g. 01712345678' },
                { name: 'reason', label: 'Reason', type: 'textarea', rows: 2, full: true, hint: 'e.g. Refused 3 parcels' },
            ]}
        />
    );
}
