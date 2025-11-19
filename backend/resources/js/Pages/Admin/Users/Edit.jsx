import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditUser({ user, availableRoles }) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name || '',
        email: user.email || '',
        role: user.role || user.roles?.[0]?.name || 'student',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.users.update', { user: user.id }));
    };

    return (
        <AdminLayout>
            <Head title={`Edit ${user.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Edit User</h1>
                        <p className="text-gray-600">{user.email}</p>
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
                            <label className="block text-sm font-medium text-gray-700">
                                Name
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="mt-1 w-full border rounded-md px-3 py-2"
                            />
                            {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">
                                Email
                            </label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-1 w-full border rounded-md px-3 py-2"
                            />
                            {errors.email && <p className="text-sm text-red-600 mt-1">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">
                                Role
                            </label>
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
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}

