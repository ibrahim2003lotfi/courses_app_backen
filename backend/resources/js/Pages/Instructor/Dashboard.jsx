import { Head, usePage } from '@inertiajs/react';

export default function InstructorDashboard() {
    const { auth, stats, recentCourses } = usePage().props;
    const user = auth?.user;

    return (
        <div className="min-h-screen bg-gray-100 py-10">
            <Head title="Instructor Dashboard" />

            <div className="max-w-4xl mx-auto bg-white shadow rounded-lg p-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-4">
                    Welcome, {user?.name} (Instructor)
                </h1>
                <p className="text-gray-600 mb-6">
                    Here is a quick overview of your instructor activity.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <div className="bg-gray-50 rounded-lg p-4">
                        <p className="text-sm text-gray-600">Total Courses</p>
                        <p className="text-2xl font-bold text-gray-900">
                            {stats?.totalCourses ?? 0}
                        </p>
                    </div>
                    <div className="bg-gray-50 rounded-lg p-4">
                        <p className="text-sm text-gray-600">Total Students</p>
                        <p className="text-2xl font-bold text-gray-900">
                            {stats?.totalStudents ?? 0}
                        </p>
                    </div>
                </div>

                <div>
                    <h2 className="text-lg font-semibold text-gray-900 mb-3">
                        Recent Courses
                    </h2>
                    {recentCourses && recentCourses.length > 0 ? (
                        <ul className="divide-y divide-gray-200">
                            {recentCourses.map((course) => (
                                <li key={course.id} className="py-3 flex justify-between">
                                    <div>
                                        <p className="font-medium text-gray-900">
                                            {course.title}
                                        </p>
                                        <p className="text-sm text-gray-500">
                                            {new Date(course.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm text-gray-600">
                                            Students
                                        </p>
                                        <p className="text-lg font-semibold text-gray-900">
                                            {course.enrollments_count ?? 0}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-gray-500">
                            You don&apos;t have any courses yet. Start by creating your first course.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

