import { Link } from '@inertiajs/react';
import Breadcrumbs from '../../Components/Breadcrumbs';
import Seo from '../../Components/Seo';
import ShopLayout from '../../Layouts/ShopLayout';

export default function Brands({ brands }) {
    return (
        <div className="container-x py-4">
            <Seo title="All Brands" />
            <Breadcrumbs items={[{ name: 'Brands' }]} />
            <h1 className="mb-4 text-2xl font-bold">All Brands</h1>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                {brands.map((b) => (
                    <Link key={b.id} href={`/brand/${b.slug}`} className="card flex flex-col items-center justify-center gap-1 p-5 text-center hover:shadow-md">
                        {b.logo_url ? <img src={b.logo_url} alt={b.name} className="mb-1 max-h-10" /> : <span className="font-bold text-slate-700">{b.name}</span>}
                        <span className="text-xs text-slate-500">{b.products_count} products</span>
                    </Link>
                ))}
            </div>
        </div>
    );
}

Brands.layout = (page) => <ShopLayout>{page}</ShopLayout>;
