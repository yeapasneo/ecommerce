import CrudPage, { activeBadge, yesNo } from '../../Components/CrudPage';

export default function Categories({ categories }) {
    const byId = Object.fromEntries(categories.map((c) => [c.id, c]));
    const pathOf = (c) => {
        const parts = [];
        let n = c;
        while (n) {
            parts.unshift(n.name);
            n = byId[n.parent_id];
        }
        return parts.join(' › ');
    };
    const sorted = [...categories].sort((a, b) => pathOf(a).localeCompare(pathOf(b)));

    return (
        <CrudPage
            title="Categories"
            singular="Category"
            endpoint="/admin/categories"
            rows={sorted}
            wide
            emptyRow={{ name: '', slug: '', parent_id: '', icon: '', description: '', filter_attributes: '', sort_order: 0, is_active: true, is_featured: false , meta_title: '', meta_description: '', meta_keywords: '' }}
            toForm={(c) => ({ ...c, parent_id: c.parent_id ?? '', filter_attributes: (c.filter_attributes ?? []).join(', ') })}
            columns={[
                { label: 'Category', render: (c) => <span className="font-medium">{pathOf(c)}</span> },
                { label: 'Slug', render: (c) => c.slug },
                { label: 'Products', render: (c) => c.products_count },
                { label: 'Filters', render: (c) => <span className="text-xs text-slate-500">{(c.filter_attributes ?? []).join(', ')}</span> },
                { label: 'Featured', render: (c) => yesNo(c.is_featured) },
                { label: 'Status', render: (c) => activeBadge(c.is_active) },
            ]}
            fields={[
                { name: 'name', label: 'Name' },
                { name: 'slug', label: 'Slug', hint: 'Leave blank to generate' },
                { name: 'parent_id', label: 'Parent Category', type: 'select', placeholder: '— None (top level) —', options: sorted.map((c) => ({ value: c.id, label: pathOf(c) })) },
                { name: 'icon', label: 'Icon', hint: 'e.g. laptop, cpu, monitor, phone, keyboard' },
                { name: 'filter_attributes', label: 'Filter Attributes', full: true, hint: 'Comma separated spec names shown as filters, e.g. RAM, Storage, Processor' },
                { name: 'description', label: 'Description', type: 'textarea', full: true, rows: 3 },
                { name: 'sort_order', label: 'Sort Order', type: 'number' },
                { name: 'is_active', label: 'Active', type: 'checkbox' },
                { name: 'is_featured', label: 'Show on homepage (featured)', type: 'checkbox' },
                { name: '_seo', label: 'Search Engine (SEO)', type: 'heading', hint: 'Optional. Blank fields are filled in automatically.' },
                { name: 'meta_title', label: 'Meta Title', full: true, hint: 'About 50–60 characters' },
                { name: 'meta_description', label: 'Meta Description', type: 'textarea', rows: 2, full: true, hint: 'About 120–160 characters' },
                { name: 'meta_keywords', label: 'Keywords', full: true, hint: 'Comma separated' },
            ]}
        />
    );
}
