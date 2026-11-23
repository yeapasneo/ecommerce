import CrudPage, { activeBadge } from '../../Components/CrudPage';

export default function Pages({ pages }) {
    return (
        <CrudPage
            title="Pages"
            singular="Page"
            endpoint="/admin/pages"
            rows={pages}
            wide
            emptyRow={{ title: '', slug: '', content: '', is_active: true }}
            columns={[
                { label: 'Title', render: (p) => <span className="font-medium">{p.title}</span> },
                { label: 'URL', render: (p) => <a href={`/page/${p.slug}`} target="_blank" rel="noreferrer" className="text-xs text-brand-600 hover:underline">/page/{p.slug}</a> },
                { label: 'Status', render: (p) => activeBadge(p.is_active) },
            ]}
            fields={[
                { name: 'title', label: 'Title' },
                { name: 'slug', label: 'Slug', hint: 'Leave blank to generate' },
                { name: 'content', label: 'Content (HTML)', type: 'textarea', full: true, rows: 12 },
                { name: 'is_active', label: 'Published', type: 'checkbox' },
            ]}
        />
    );
}
