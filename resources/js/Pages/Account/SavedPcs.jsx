import { Link, router } from '@inertiajs/react';
import AccountLayout from '../../Layouts/AccountLayout';
import { date, money } from '../../lib/format';

export default function SavedPcs({ savedPcs }) {
    return (
        <AccountLayout title="Saved PCs">
            {savedPcs.length === 0 ? (
                <div className="card p-10 text-center text-sm text-slate-500">
                    No saved builds yet. <Link href="/pc-builder" className="text-accent-600 hover:underline">Start building a PC</Link>.
                </div>
            ) : (
                <div className="card overflow-x-auto">
                    <table className="table-x">
                        <thead><tr><th>Name</th><th>Saved</th><th className="text-right">Total</th><th /></tr></thead>
                        <tbody>
                            {savedPcs.map((s) => (
                                <tr key={s.id}>
                                    <td className="font-medium">{s.name}</td>
                                    <td>{date(s.created_at)}</td>
                                    <td className="text-right">{money(s.total)}</td>
                                    <td className="space-x-2 text-right">
                                        <button onClick={() => router.post(`/pc-builder/load/${s.id}`)} className="btn-outline btn-sm">Open</button>
                                        <button onClick={() => router.delete(`/pc-builder/saved/${s.id}`, { preserveScroll: true })} className="btn-outline btn-sm text-red-600">Delete</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AccountLayout>
    );
}
