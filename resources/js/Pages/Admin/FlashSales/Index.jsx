import { Link, router } from '@inertiajs/react';
import { EmptyState, RowMenu } from '../../../Components/Admin/UI';
import Icon from '../../../Components/Icon';
import AdminLayout from '../../../Layouts/AdminLayout';
import { dateTime } from '../../../lib/format';

const STATE = {
    running: ['Running', 'bg-green-50 text-green-700'],
    scheduled: ['Scheduled', 'bg-blue-50 text-blue-700'],
    ended: ['Ended', 'bg-slate-100 text-slate-600'],
    off: ['Switched off', 'bg-amber-50 text-amber-700'],
};

export default function Index({ sales }) {
    const add = <Link href="/admin/flash-sales/create" className="btn-primary"><Icon name="plus" className="h-4 w-4" /> New flash sale</Link>;
    return (
        <AdminLayout title="Flash Sales" count={sales.length} subtitle="Time-limited prices with a countdown on the storefront" actions={add}>
            <div className="panel overflow-hidden">
                {sales.length === 0 ? (
                    <EmptyState icon="gift" title="No flash sales yet" text="Set special prices on chosen products for a few hours or days." action={add} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-admin min-w-[720px]">
                            <thead><tr><th className="pl-6">Sale</th><th>Starts</th><th>Ends</th><th>Products</th><th>Status</th><th className="w-px" /></tr></thead>
                            <tbody>
                                {sales.map((s) => (
                                    <tr key={s.id}>
                                        <td className="pl-6"><Link href={`/admin/flash-sales/${s.id}/edit`} className="font-medium text-slate-900 hover:text-primary-700">{s.title}</Link></td>
                                        <td className="whitespace-nowrap text-sm">{dateTime(s.starts_at)}</td>
                                        <td className="whitespace-nowrap text-sm">{dateTime(s.ends_at)}</td>
                                        <td>{s.items_count}</td>
                                        <td><span className={`badge rounded-md ring-1 ring-inset ring-current/20 ${STATE[s.state][1]}`}>{STATE[s.state][0]}</span></td>
                                        <td className="pr-6">
                                            <RowMenu items={[
                                                { label: 'Edit', icon: 'edit', href: `/admin/flash-sales/${s.id}/edit` },
                                                { label: 'Delete', icon: 'trash', danger: true, onClick: () => confirm(`Delete “${s.title}”?`) && router.delete(`/admin/flash-sales/${s.id}`) },
                                            ]} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
