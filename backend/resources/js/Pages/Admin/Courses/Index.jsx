import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

export default function CoursesIndex({ courses, stats, categories, filters }) {
    const list = courses?.data ?? [];
    const pageInfo = courses ?? {};

    const statCards = [
        { label: 'Total Courses', value: stats?.total_courses ?? 0 },
        { label: 'Published', value: stats?.published ?? 0 },
        { label: 'Unpublished', value: stats?.unpublished ?? 0 },
        { label: 'Total Revenue', value: `$${Number(stats?.total_revenue ?? 0).toFixed(2)}` },
    ];

    return (
        <AdminLayout>
            <Head title="Courses" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Courses</h1>
                    <p className="text-gray-600">Manage all courses on the platform.</p>
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
                    <div className="px-6 py-4 border-b flex items-center justify-between">
                        <div>
                            <p className="font-semibold text-gray-900">Course List</p>
                            <p className="text-sm text-gray-500">Showing {list.length} of {pageInfo.total ?? list.length}</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instructor</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Enrollments</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {list.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-6 text-center text-gray-500">
                                            No courses found.
                                        </td>
                                    </tr>
                                )}
                                {list.map((course) => (
                                    <tr key={course.id}>
                                        <td className="px-6 py-4">
                                            <p className="font-medium text-gray-900">{course.title}</p>
                                            <p className="text-sm text-gray-500">{course.level}</p>
                                        </td>
                                        <td className="px-6 py-4">{course.instructor?.name ?? 'N/A'}</td>
                                        <td className="px-6 py-4">{course.category?.name ?? 'N/A'}</td>
                                        <td className="px-6 py-4 text-right">${Number(course.price ?? 0).toFixed(2)}</td>
                                        <td className="px-6 py-4 text-right">{course.enrollments_count ?? 0}</td>
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

