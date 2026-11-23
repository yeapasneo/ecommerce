import CrudPage, { activeBadge } from '../../Components/CrudPage';
import { date, money } from '../../lib/format';

export default function Coupons({ coupons }) {
    return (
        <CrudPage
            title="Coupons"
            singular="Coupon"
            endpoint="/admin/coupons"
            rows={coupons}
            emptyRow={{ code: '', type: 'fixed', value: '', min_order: 0, max_discount: '', usage_limit: '', starts_at: '', expires_at: '', is_active: true }}
            toForm={(c) => ({ ...c, starts_at: c.starts_at?.slice(0, 10) ?? '', expires_at: c.expires_at?.slice(0, 10) ?? '', max_discount: c.max_discount ?? '', usage_limit: c.usage_limit ?? '' })}
            columns={[
                { label: 'Code', render: (c) => <span className="font-mono font-semibold">{c.code}</span> },
                { label: 'Discount', render: (c) => (c.type === 'percent' ? `${c.value}%` : money(c.value)) },
                { label: 'Min Order', render: (c) => money(c.min_order) },
                { label: 'Used', render: (c) => `${c.used}${c.usage_limit ? ` / ${c.usage_limit}` : ''}` },
                { label: 'Valid', render: (c) => <span className="text-xs">{c.starts_at ? date(c.starts_at) : 'Any'} → {c.expires_at ? date(c.expires_at) : 'No expiry'}</span> },
                { label: 'Status', render: (c) => activeBadge(c.is_active) },
            ]}
            fields={[
                { name: 'code', label: 'Code' },
                { name: 'type', label: 'Type', type: 'select', options: [{ value: 'fixed', label: 'Fixed amount (৳)' }, { value: 'percent', label: 'Percentage (%)' }] },
                { name: 'value', label: 'Value', type: 'number', step: '0.01' },
                { name: 'min_order', label: 'Minimum Order (৳)', type: 'number' },
                { name: 'max_discount', label: 'Max Discount (৳)', type: 'number', hint: 'For percentage coupons' },
                { name: 'usage_limit', label: 'Usage Limit', type: 'number', hint: 'Blank = unlimited' },
                { name: 'starts_at', label: 'Starts', type: 'date' },
                { name: 'expires_at', label: 'Expires', type: 'date' },
                { name: 'is_active', label: 'Active', type: 'checkbox' },
            ]}
        />
    );
}
