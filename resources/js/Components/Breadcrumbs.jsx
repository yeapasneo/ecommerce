import { Link } from '@inertiajs/react';
import Icon from './Icon';

export default function Breadcrumbs({ items = [] }) {
    return (
        <nav className="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500">
            <Link href="/" className="hover:text-accent-600">Home</Link>
            {items.map((b, i) => (
                <span key={i} className="flex items-center gap-1">
                    <Icon name="chevronRight" className="h-3.5 w-3.5" />
                    {b.slug || b.url ? (
                        <Link href={b.url ?? `/category/${b.slug}`} className={i === items.length - 1 ? 'text-slate-800' : 'hover:text-accent-600'}>{b.name}</Link>
                    ) : (
                        <span className="text-slate-800">{b.name}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}
