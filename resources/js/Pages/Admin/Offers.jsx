import CrudPage, { activeBadge } from '../../Components/CrudPage';
import { date } from '../../lib/format';

export default function Offers({ offers }) {
    return (
        <CrudPage
            title="Offers"
            singular="Offer"
            endpoint="/admin/offers"
            rows={offers}
            emptyRow={{ title: '', description: '', link: '', starts_at: '', ends_at: '', is_active: true, image: null }}
            columns={[
                { label: 'Image', render: (o) => (o.image_url ? <img src={o.image_url} alt="" className="h-12 w-28 rounded object-cover" /> : null) },
                { label: 'Title', render: (o) => <span className="font-medium">{o.title}</span> },
                { label: 'Period', render: (o) => <span className="text-xs">{date(o.starts_at) || 'Now'} → {date(o.ends_at) || 'Open'}</span> },
                { label: 'Status', render: (o) => activeBadge(o.is_active) },
            ]}
            fields={[
                { name: 'title', label: 'Title', full: true },
                { name: 'description', label: 'Description', type: 'textarea', full: true, rows: 3 },
                { name: 'link', label: 'Link' },
                { name: 'image', label: 'Image', type: 'file' },
                { name: 'starts_at', label: 'Starts', type: 'date' },
                { name: 'ends_at', label: 'Ends', type: 'date' },
                { name: 'is_active', label: 'Active', type: 'checkbox' },
            ]}
        />
    );
}
