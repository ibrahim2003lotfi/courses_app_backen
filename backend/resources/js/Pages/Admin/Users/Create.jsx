import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateUser({ availableRoles }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        role: 'student',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AdminLayout>
            <Head title="Add User" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Add User</h1>
                        <p className="text-gray-600">Create a new account manually.</p>
                    </div>
                    <Link
                        href={route('admin.users.index')}
                        className="text-blue-600 hover:text-blue-800"
                    >
                        &larr; Back to users
                    </Link>
                </div>

                <div className="bg-white rounded-lg shadow p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="mt-1 w-full border rounded-md px-3 py-2"
                            />
                            {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-1 w-full border rounded-md px-3 py-2"
                            />
                            {errors.email && <p className="text-sm text-red-600 mt-1">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Role</label>
                            <select
                                value={data.role}
                                onChange={(e) => setData('role', e.target.value)}
                                className="mt-1 w-full border rounded-md px-3 py-2"
                            >
                                {availableRoles.map((role) => (
                                    <option key={role} value={role}>
                                        {role.charAt(0).toUpperCase() + role.slice(1)}
                                    </option>
                                ))}
                            </select>
                            {errors.role && <p className="text-sm text-red-600 mt-1">{errors.role}</p>}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Password</label>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="mt-1 w-full border rounded-md px-3 py-2"
                                />
                                {errors.password && <p className="text-sm text-red-600 mt-1">{errors.password}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Confirm Password</label>
                                <input
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    className="mt-1 w-full border rounded-md px-3 py-2"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end space-x-3 pt-4">
                            <Link
                                href={route('admin.users.index')}
                                className="px-4 py-2 rounded-md border"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}

