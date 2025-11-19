import { Head } from '@inertiajs/react';

export default function StudentDashboard() {
    return (
        <div className="min-h-screen bg-gray-100 py-10">
            <Head title="Student Dashboard" />

            <div className="max-w-4xl mx-auto bg-white shadow rounded-lg p-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-4">
                    Student Dashboard
                </h1>
                <p className="text-gray-600">
                    Course recommendations and progress tracking will appear here.
                </p>
            </div>
        </div>
    );
}

