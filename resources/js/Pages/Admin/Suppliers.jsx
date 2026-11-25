import CrudPage, { activeBadge } from '../../Components/CrudPage';
import { money } from '../../lib/format';

export default function Suppliers({ suppliers }) {
    return (
        <CrudPage
            title="Suppliers"
            singular="Supplier"
            endpoint="/admin/suppliers"
            rows={suppliers}
            description="Who you buy stock from. Pick a supplier when creating a purchase order."
            emptyRow={{ name: '', contact_person: '', phone: '', email: '', address: '', note: '', is_active: true }}
            columns={[
                { label: 'Name', render: (s) => <span className="font-medium">{s.name}</span> },
                { label: 'Contact', render: (s) => [s.contact_person, s.phone].filter(Boolean).join(' · ') || '—' },
                { label: 'Purchase orders', render: (s) => s.purchase_orders_count },
                { label: 'Bought', render: (s) => money(s.purchased ?? 0) },
                { label: 'Status', render: (s) => activeBadge(s.is_active) },
            ]}
            fields={[
                { name: 'name', label: 'Name' },
                { name: 'contact_person', label: 'Contact person' },
                { name: 'phone', label: 'Phone' },
                { name: 'email', label: 'Email' },
                { name: 'address', label: 'Address', full: true },
                { name: 'note', label: 'Note', type: 'textarea', rows: 2, full: true },
                { name: 'is_active', label: 'Active', type: 'checkbox' },
            ]}
        />
    );
}
