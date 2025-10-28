import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Users, BookOpen, DollarSign, FileCheck } from 'lucide-react';

export default function Dashboard({ auth, stats, recentOrders, revenueByMonth, topCourses }) {
    // Add safety checks for all props
    const safeStats = stats || {
        totalUsers: 0,
        totalCourses: 0,
        totalRevenue: 0,
        pendingApplications: 0
    };

    const safeRecentOrders = recentOrders || [];
    const safeRevenueByMonth = revenueByMonth || [];
    const safeTopCourses = topCourses || [];

    const statCards = [
        { title: 'Total Users', value: safeStats.totalUsers, icon: Users, color: 'bg-blue-500' },
        { title: 'Total Courses', value: safeStats.totalCourses, icon: BookOpen, color: 'bg-green-500' },
        { title: 'Total Revenue', value: `$${safeStats.totalRevenue || 0}`, icon: DollarSign, color: 'bg-yellow-500' },
        { title: 'Pending Applications', value: safeStats.pendingApplications, icon: FileCheck, color: 'bg-purple-500' },
    ];

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {statCards.map((stat, index) => (
                        <div key={index} className="bg-white rounded-lg shadow p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">{stat.title}</p>
                                    <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                                </div>
                                <div className={`${stat.color} p-3 rounded-full text-white`}>
                                    <stat.icon size={24} />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Revenue Chart */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-lg font-semibold mb-4">Revenue by Month</h2>
                        {safeRevenueByMonth.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={safeRevenueByMonth}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" />
                                    <YAxis />
                                    <Tooltip />
                                    <Bar dataKey="revenue" fill="#3B82F6" />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="h-300 flex items-center justify-center text-gray-500">
                                No revenue data available
                            </div>
                        )}
                    </div>

                    {/* Top Courses */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-lg font-semibold mb-4">Top Courses</h2>
                        {safeTopCourses.length > 0 ? (
                            <div className="space-y-3">
                                {safeTopCourses.map((course) => (
                                    <div key={course.id} className="flex items-center justify-between">
                                        <div>
                                            <p className="font-medium">{course.title}</p>
                                            <p className="text-sm text-gray-600">{course.enrollments_count} students</p>
                                        </div>
                                        <span className="text-lg font-bold">${course.price || 0}</span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-gray-500">No course data available</p>
                        )}
                    </div>
                </div>

                {/* Recent Orders */}
                <div className="bg-white rounded-lg shadow">
                    <div className="px-6 py-4 border-b">
                        <h2 className="text-lg font-semibold">Recent Orders</h2>
                    </div>
                    {safeRecentOrders.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {safeRecentOrders.map((order) => (
                                        <tr key={order.id}>
                                            <td className="px-6 py-4">{order.user?.name || 'N/A'}</td>
                                            <td className="px-6 py-4">{order.course?.title || 'N/A'}</td>
                                            <td className="px-6 py-4">${order.amount || 0}</td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2 py-1 text-xs rounded-full ${
                                                    order.status === 'succeeded' ? 'bg-green-100 text-green-800' :
                                                    order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                    'bg-red-100 text-red-800'
                                                }`}>
                                                    {order.status || 'unknown'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                {order.created_at ? new Date(order.created_at).toLocaleDateString() : 'N/A'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-6 text-center text-gray-500">
                            No recent orders
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}