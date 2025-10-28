import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import { RefreshCw, Eye } from 'lucide-react';

export default function OrdersIndex({ orders, filters }) {
    const [status, setStatus] = useState(filters.status || '');

    const handleFilter = () => {
        router.get('/admin/orders', { status }, { preserveState: true });
    };

    const processRefund = (orderId) => {
        if (confirm('Are you sure you want to process this refund?')) {
            router.post(`/admin/orders/${orderId}/refund`, {
                amount: prompt('Enter refund amount:'),
                reason: prompt('Enter refund reason:')
            });
        }
    };

    return (
        <AdminLayout>
            <Head title="Orders" />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold text-gray-900">Orders</h1>

                {/* Filters */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex gap-4">
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="rounded-md border-gray-300"
                        >
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="succeeded">Succeeded</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                            <option value="refund_requested">Refund Requested</option>
                        </select>
                        <button
                            onClick={handleFilter}
                            className="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-900"
                        >
                            Filter
                        </button>
                    </div>
                </div>

                {/* Orders Table */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {orders.data.map((order) => (
                                <tr key={order.id}>
                                    <td className="px-6 py-4 text-sm">{order.id.substring(0, 8)}...</td>
                                    <td className="px-6 py-4">{order.user.name}</td>
                                    <td className="px-6 py-4">{order.course.title}</td>
                                    <td className="px-6 py-4">${order.amount}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs rounded-full ${
                                            order.status === 'succeeded' ? 'bg-green-100 text-green-800' :
                                            order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                            order.status === 'refunded' ? 'bg-blue-100 text-blue-800' :
                                            order.status === 'refund_requested' ? 'bg-orange-100 text-orange-800' :
                                            'bg-red-100 text-red-800'
                                        }`}>
                                            {order.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">{new Date(order.created_at).toLocaleDateString()}</td>
                                    <td className="px-6 py-4">
                                        <div className="flex space-x-2">
                                            <Link
                                                href={`/admin/orders/${order.id}`}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                <Eye size={18} />
                                            </Link>
                                            {(order.status === 'succeeded' || order.status === 'refund_requested') && (
                                                <button
                                                    onClick={() => processRefund(order.id)}
                                                    className="text-orange-600 hover:text-orange-900"
                                                >
                                                    <RefreshCw size={18} />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}