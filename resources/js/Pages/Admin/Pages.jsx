import CrudPage, { activeBadge } from '../../Components/CrudPage';

export default function Pages({ pages }) {
    return (
        <CrudPage
            title="Pages"
            singular="Page"
            endpoint="/admin/pages"
            rows={pages}
            wide
            emptyRow={{ title: '', slug: '', content: '', is_active: true , meta_title: '', meta_description: '', meta_keywords: '' }}
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
                { name: '_seo', label: 'Search Engine (SEO)', type: 'heading', hint: 'Optional. Blank fields are filled in automatically.' },
                { name: 'meta_title', label: 'Meta Title', full: true, hint: 'About 50–60 characters' },
                { name: 'meta_description', label: 'Meta Description', type: 'textarea', rows: 2, full: true, hint: 'About 120–160 characters' },
                { name: 'meta_keywords', label: 'Keywords', full: true, hint: 'Comma separated' },
            ]}
        />
    );
}
