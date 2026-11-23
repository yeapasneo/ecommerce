import { Link } from '@inertiajs/react';
import Breadcrumbs from '../Components/Breadcrumbs';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';
import { date } from '../lib/format';

export default function Offers({ offers }) {
    return (
        <div className="container-x py-4">
            <Seo title="Offers" />
            <Breadcrumbs items={[{ name: 'Offers' }]} />
            <h1 className="mb-4 text-2xl font-bold">Latest Offers</h1>
            {offers.length === 0 && <div className="card p-10 text-center text-slate-500">No running offers right now. Check back soon!</div>}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {offers.map((o) => (
                    <div key={o.id} className="card overflow-hidden">
                        {o.image_url && <img src={o.image_url} alt={o.title} className="aspect-[8/3] w-full object-cover" />}
                        <div className="p-4">
                            {o.ends_at && <p className="text-xs font-semibold text-accent-600">Ends {date(o.ends_at)}</p>}
                            <h2 className="mt-1 font-semibold">{o.title}</h2>
                            <p className="mt-1 text-sm text-slate-600">{o.description}</p>
                            {o.link && <Link href={o.link} className="btn-primary btn-sm mt-3">View Offer</Link>}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

Offers.layout = (page) => <ShopLayout>{page}</ShopLayout>;
