import Seo from './Seo';

export default function AuthCard({ title, subtitle, children }) {
    return (
        <div className="container-x flex justify-center py-10">
            <Seo title={title} />
            <div className="card w-full max-w-md p-6 sm:p-8">
                <h1 className="text-center text-2xl font-bold">{title}</h1>
                {subtitle && <p className="mt-1 text-center text-sm text-slate-500">{subtitle}</p>}
                <div className="mt-6">{children}</div>
            </div>
        </div>
    );
}
