import { Head } from '@inertiajs/react';

export default function InstructorDashboard() {
    return (
        <div className="min-h-screen bg-gray-100 py-10">
            <Head title="Instructor Dashboard" />

            <div className="max-w-4xl mx-auto bg-white shadow rounded-lg p-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-4">
                    Instructor Dashboard
                </h1>
                <p className="text-gray-600">
                    Manage your courses and track student engagement from here.
                </p>
            </div>
        </div>
    );
}

