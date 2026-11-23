import Breadcrumbs from '../Components/Breadcrumbs';
import Seo from '../Components/Seo';
import ShopLayout from '../Layouts/ShopLayout';

export default function Page({ page }) {
    return (
        <div className="container-x max-w-4xl py-4">
            <Seo title={page.title} />
            <Breadcrumbs items={[{ name: page.title }]} />
            <article className="card p-6">
                <h1 className="mb-4 text-2xl font-bold">{page.title}</h1>
                <div className="prose-content text-slate-700" dangerouslySetInnerHTML={{ __html: page.content }} />
            </article>
        </div>
    );
}

Page.layout = (page) => <ShopLayout>{page}</ShopLayout>;
