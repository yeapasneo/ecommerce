import CrudPage, { yesNo } from '../../Components/CrudPage';

export default function PcComponents({ components, categories }) {
    return (
        <CrudPage
            title="PC Builder Parts"
            singular="Component"
            endpoint="/admin/pc-components"
            rows={components}
            emptyRow={{ name: '', slug: '', category_id: '', is_required: false, section: 'core', sort_order: 0 }}
            toForm={(c) => ({ ...c, category_id: c.category_id ?? '' })}
            columns={[
                { label: 'Component', render: (c) => <span className="font-medium">{c.name}</span> },
                { label: 'Products From', render: (c) => c.category?.name ?? <span className="text-slate-400">All products</span> },
                { label: 'Section', render: (c) => (c.section === 'core' ? 'Core' : 'Peripherals') },
                { label: 'Required', render: (c) => yesNo(c.is_required) },
                { label: 'Order', render: (c) => c.sort_order },
            ]}
            fields={[
                { name: 'name', label: 'Name' },
                { name: 'slug', label: 'Slug', hint: 'Leave blank to generate' },
                { name: 'category_id', label: 'Category', type: 'select', placeholder: '— Any —', options: categories.map((c) => ({ value: c.id, label: c.name })) },
                { name: 'section', label: 'Section', type: 'select', options: [{ value: 'core', label: 'Core Components' }, { value: 'peripherals', label: 'Peripherals & Others' }] },
                { name: 'sort_order', label: 'Sort Order', type: 'number' },
                { name: 'is_required', label: 'Required for a build', type: 'checkbox' },
            ]}
        />
    );
}
