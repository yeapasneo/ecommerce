import CrudPage, { activeBadge } from '../../Components/CrudPage';

export default function Brands({ brands }) {
    return (
        <CrudPage
            title="Brands"
            singular="Brand"
            endpoint="/admin/brands"
            rows={brands}
            emptyRow={{ name: '', slug: '', logo: null, is_active: true , meta_title: '', meta_description: '', meta_keywords: '' }}
            columns={[
                { label: 'Logo', render: (b) => (b.logo_url ? <img src={b.logo_url} alt="" className="h-8" /> : <span className="text-slate-300">—</span>) },
                { label: 'Name', render: (b) => <span className="font-medium">{b.name}</span> },
                { label: 'Slug', render: (b) => b.slug },
                { label: 'Products', render: (b) => b.products_count },
                { label: 'Status', render: (b) => activeBadge(b.is_active) },
            ]}
            fields={[
                { name: 'name', label: 'Name' },
                { name: 'slug', label: 'Slug', hint: 'Leave blank to generate' },
                { name: 'logo', label: 'Logo', type: 'file', full: true },
                { name: 'is_active', label: 'Active', type: 'checkbox' },
                { name: '_seo', label: 'Search Engine (SEO)', type: 'heading', hint: 'Optional. Blank fields are filled in automatically.' },
                { name: 'meta_title', label: 'Meta Title', full: true, hint: 'About 50–60 characters' },
                { name: 'meta_description', label: 'Meta Description', type: 'textarea', rows: 2, full: true, hint: 'About 120–160 characters' },
                { name: 'meta_keywords', label: 'Keywords', full: true, hint: 'Comma separated' },
            ]}
        />
    );
}
