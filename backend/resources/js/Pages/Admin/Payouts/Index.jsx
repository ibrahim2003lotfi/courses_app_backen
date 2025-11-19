import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

export default function PayoutsIndex({ payouts, stats, instructors }) {
    const list = payouts?.data ?? [];
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
    };

    const statCards = [
        { label: 'Pending Amount', value: `$${Number(stats?.total_pending ?? 0).toFixed(2)}` },
        { label: 'Completed Amount', value: `$${Number(stats?.total_completed ?? 0).toFixed(2)}` },
        { label: 'Pending Requests', value: stats?.pending_count ?? 0 },
        { label: 'Paid This Month', value: `$${Number(stats?.this_month ?? 0).toFixed(2)}` },
    ];

    return (
        <AdminLayout>
            <Head title="Payouts" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Payouts</h1>
                    <p className="text-gray-600">Track instructor earnings and payout statuses.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {statCards.map((card) => (
                        <div key={card.label} className="bg-white rounded-lg shadow p-4">
                            <p className="text-sm text-gray-500">{card.label}</p>
                            <p className="text-2xl font-semibold text-gray-900 mt-1">{card.value}</p>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-lg shadow">
                    <div className="px-6 py-4 border-b">
                        <p className="font-semibold text-gray-900">Recent Payouts</p>
                        <p className="text-sm text-gray-500">Showing {list.length} records</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instructor</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {list.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-6 text-center text-gray-500">
                                            No payouts found.
                                        </td>
                                    </tr>
                                )}
                                {list.map((payout) => (
                                    <tr key={payout.id}>
                                        <td className="px-6 py-4">
                                            <p className="font-medium text-gray-900">{payout.instructor?.name ?? 'N/A'}</p>
                                            <p className="text-sm text-gray-500">{payout.instructor?.email}</p>
                                        </td>
                                        <td className="px-6 py-4">${Number(payout.amount ?? 0).toFixed(2)}</td>
                                        <td className="px-6 py-4 capitalize">{payout.method ?? 'manual'}</td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2 py-1 text-xs rounded-full ${statusColors[payout.status] ?? 'bg-gray-100 text-gray-700'}`}>
                                                {payout.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            {payout.created_at ? new Date(payout.created_at).toLocaleDateString() : 'N/A'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

