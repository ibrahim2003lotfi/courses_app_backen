import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

export default function ApplicationsIndex({ applications, stats }) {
    const list = applications?.data ?? [];
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };

    const statCards = [
        { label: 'Pending', value: stats?.pending ?? 0, color: 'bg-yellow-500' },
        { label: 'Approved', value: stats?.approved ?? 0, color: 'bg-green-500' },
        { label: 'Rejected', value: stats?.rejected ?? 0, color: 'bg-red-500' },
        { label: 'Total', value: stats?.total ?? 0, color: 'bg-blue-500' },
    ];

    const approve = (id) => {
        if (!confirm('Approve this instructor application?')) return;

        router.post(`/admin/instructor-applications/${id}/approve`, {
            notes: 'Approved from applications page',
            commission_rate: 20,
        });
    };

    const reject = (id) => {
        if (!confirm('Reject this instructor application?')) return;

        router.post(`/admin/instructor-applications/${id}/reject`, {
            reason: 'Rejected from applications page',
            can_reapply: false,
        });
    };

    return (
        <AdminLayout>
            <Head title="Instructor Applications" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Instructor Applications</h1>
                    <p className="text-gray-600">Review and manage instructor onboarding requests.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {statCards.map((card) => (
                        <div key={card.label} className={`rounded-lg shadow p-4 text-white ${card.color}`}>
                            <p className="text-sm opacity-90">{card.label}</p>
                            <p className="text-2xl font-semibold mt-1">{card.value}</p>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-lg shadow">
                    <div className="px-6 py-4 border-b">
                        <p className="font-semibold text-gray-900">Recent Applications</p>
                        <p className="text-sm text-gray-500">Showing {list.length} records</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Experience</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {list.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-6 text-center text-gray-500">
                                            No applications found.
                                        </td>
                                    </tr>
                                )}
                                {list.map((application) => (
                                    <tr key={application.id}>
                                        <td className="px-6 py-4">
                                            <p className="font-medium text-gray-900">{application.user?.name ?? 'N/A'}</p>
                                            <p className="text-sm text-gray-500">{application.user?.email}</p>
                                        </td>
                                        <td className="px-6 py-4">{application.experience_years ?? 0} years</td>
                                        <td className="px-6 py-4">
                                            {application.created_at ? new Date(application.created_at).toLocaleDateString() : 'N/A'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2 py-1 text-xs rounded-full ${statusColors[application.status] ?? 'bg-gray-100 text-gray-700'}`}>
                                                {application.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            {application.status === 'pending' ? (
                                                <div className="flex space-x-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => approve(application.id)}
                                                        className="px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => reject(application.id)}
                                                        className="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700"
                                                    >
                                                        Reject
                                                    </button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-gray-400">No actions</span>
                                            )}
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

