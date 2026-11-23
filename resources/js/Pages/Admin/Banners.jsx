import CrudPage, { activeBadge } from '../../Components/CrudPage';

export default function Banners({ banners }) {
    return (
        <CrudPage
            title="Banners & Sliders"
            singular="Banner"
            endpoint="/admin/banners"
            rows={banners}
            emptyRow={{ title: '', subtitle: '', link: '', position: 'slider', bg_color: '#081621', sort_order: 0, is_active: true, image: null }}
            columns={[
                { label: 'Image', render: (b) => (b.image_url ? <img src={b.image_url} alt="" className="h-12 w-28 rounded object-cover" /> : <span className="inline-block h-12 w-28 rounded" style={{ background: b.bg_color }} />) },
                { label: 'Title', render: (b) => <span className="font-medium">{b.title}</span> },
                { label: 'Position', render: (b) => (b.position === 'slider' ? 'Home slider' : 'Side banner') },
                { label: 'Link', render: (b) => <span className="text-xs">{b.link}</span> },
                { label: 'Order', render: (b) => b.sort_order },
                { label: 'Status', render: (b) => activeBadge(b.is_active) },
            ]}
            fields={[
                { name: 'title', label: 'Title' },
                { name: 'subtitle', label: 'Subtitle' },
                { name: 'link', label: 'Link', hint: 'e.g. /category/laptop' },
                { name: 'position', label: 'Position', type: 'select', options: [{ value: 'slider', label: 'Home slider (1200×450)' }, { value: 'side', label: 'Side banner (600×300)' }] },
                { name: 'image', label: 'Image', type: 'file' },
                { name: 'bg_color', label: 'Background Colour', type: 'color' },
                { name: 'sort_order', label: 'Sort Order', type: 'number' },
                { name: 'is_active', label: 'Active', type: 'checkbox' },
            ]}
        />
    );
}
