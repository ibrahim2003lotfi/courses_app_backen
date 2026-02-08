import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { User, BookOpen, ShoppingCart, DollarSign } from 'lucide-react';

export default function ShowUser({ user, stats }) {
    const safeStats = stats || {
        totalCourses: 0,
        totalEnrollments: 0,
        totalSpent: 0
    };

    const statCards = [
        { title: 'Total Courses', value: safeStats.totalCourses, icon: BookOpen, color: 'bg-blue-500' },
        { title: 'Total Enrollments', value: safeStats.totalEnrollments, icon: ShoppingCart, color: 'bg-green-500' },
        { title: 'Total Spent', value: `$${Number(safeStats.totalSpent || 0).toFixed(2)}`, icon: DollarSign, color: 'bg-yellow-500' },
    ];

    return (
        <AdminLayout>
            <Head title={`User: ${user?.name || 'N/A'}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">User Details</h1>
                        <p className="text-gray-600">{user?.email || 'N/A'}</p>
                    </div>
                    <div className="flex space-x-3">
                        <Link
                            href={`/admin/users/${user?.id}/edit`}
                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            Edit User
                        </Link>
                        <Link
                            href="/admin/users"
                            className="px-4 py-2 border rounded-md hover:bg-gray-50"
                        >
                            Back to Users
                        </Link>
                    </div>
                </div>

                {/* User Info Card */}
                <div className="bg-white rounded-lg shadow p-6">
                    <div className="flex items-start space-x-4">
                        <div className="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center">
                            <User size={32} className="text-white" />
                        </div>
                        <div className="flex-1">
                            <h2 className="text-xl font-semibold text-gray-900">{user?.name || 'N/A'}</h2>
                            <p className="text-gray-600">{user?.email || 'N/A'}</p>
                            <p className="text-sm text-gray-500 mt-1">
                                Joined: {user?.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                            </p>
                            <div className="mt-3">
                                <span className={`px-3 py-1 text-xs rounded-full ${
                                    user?.roles?.[0]?.name === 'admin' ? 'bg-red-100 text-red-800' :
                                    user?.roles?.[0]?.name === 'instructor' ? 'bg-green-100 text-green-800' :
                                    'bg-blue-100 text-blue-800'
                                }`}>
                                    {user?.roles?.[0]?.name || 'student'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {statCards.map((stat, index) => (
                        <div key={index} className="bg-white rounded-lg shadow p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">{stat.title}</p>
                                    <p className="text-2xl font-bold text-gray-900 mt-1">{stat.value}</p>
                                </div>
                                <div className={`${stat.color} p-3 rounded-full text-white`}>
                                    <stat.icon size={24} />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Courses Section */}
                {user?.courses && user.courses.length > 0 && (
                    <div className="bg-white rounded-lg shadow">
                        <div className="px-6 py-4 border-b">
                            <h2 className="text-lg font-semibold text-gray-900">Courses Created</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {user.courses.map((course) => (
                                        <tr key={course.id}>
                                            <td className="px-6 py-4">{course.title}</td>
                                            <td className="px-6 py-4">${Number(course.price || 0).toFixed(2)}</td>
                                            <td className="px-6 py-4">
                                                {course.created_at ? new Date(course.created_at).toLocaleDateString() : 'N/A'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Enrollments Section */}
                {user?.enrollments && user.enrollments.length > 0 && (
                    <div className="bg-white rounded-lg shadow">
                        <div className="px-6 py-4 border-b">
                            <h2 className="text-lg font-semibold text-gray-900">Enrollments</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enrolled</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {user.enrollments.map((enrollment) => (
                                        <tr key={enrollment.id}>
                                            <td className="px-6 py-4">{enrollment.course?.title || 'N/A'}</td>
                                            <td className="px-6 py-4">
                                                {enrollment.created_at ? new Date(enrollment.created_at).toLocaleDateString() : 'N/A'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2 py-1 text-xs rounded-full ${
                                                    enrollment.refunded_at ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
                                                }`}>
                                                    {enrollment.refunded_at ? 'Refunded' : 'Active'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}











