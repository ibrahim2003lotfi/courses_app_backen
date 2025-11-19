import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <div className="min-h-screen bg-gray-100 py-10">
            <Head title="Dashboard" />

            <div className="max-w-4xl mx-auto bg-white shadow rounded-lg p-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-4">
                    Welcome to MyCourses
                </h1>
                <p className="text-gray-600">
                    You have successfully logged in. Once your account is fully set up,
                    you will see personalized content here. If you should have access
                    to the admin panel, please ensure your account has the `admin` role
                    or contact support.
                </p>
            </div>
        </div>
    );
}

