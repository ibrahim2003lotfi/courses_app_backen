/* global route */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import { Search, Edit, Trash2, Eye } from 'lucide-react';

export default function UsersIndex({ users, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [role, setRole] = useState(filters.role || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/admin/users', { search, role }, { preserveState: true });
    };

    const deleteUser = (id) => {
        if (confirm('Are you sure you want to delete this user?')) {
            router.delete(route('admin.users.destroy', { user: id }), {}, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AdminLayout>
            <Head title="Users" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold text-gray-900">Users</h1>
                    <Link
                        href="/admin/users/create"
                        className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        Add User
                    </Link>
                </div>

                {/* Filters */}
                <form onSubmit={handleSearch} className="bg-white rounded-lg shadow p-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={20} />
                            <input
                                type="text"
                                placeholder="Search users..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10 w-full rounded-md border-gray-300"
                            />
                        </div>
                        <select
                            value={role}
                            onChange={(e) => setRole(e.target.value)}
                            className="rounded-md border-gray-300"
                        >
                            <option value="">All Roles</option>
                            <option value="student">Student</option>
                            <option value="instructor">Instructor</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-900"
                        >
                            Filter
                        </button>
                    </div>
                </form>

                {/* Users Table */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-6 py-4">{user.name}</td>
                                    <td className="px-6 py-4">{user.email}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs rounded-full ${
                                            user.roles[0]?.name === 'admin' ? 'bg-red-100 text-red-800' :
                                            user.roles[0]?.name === 'instructor' ? 'bg-green-100 text-green-800' :
                                            'bg-blue-100 text-blue-800'
                                        }`}>
                                            {user.roles[0]?.name || 'student'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">{new Date(user.created_at).toLocaleDateString()}</td>
                                    <td className="px-6 py-4">
                                        <div className="flex space-x-2">
                                            <Link
                                                href={`/admin/users/${user.id}`}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                <Eye size={18} />
                                            </Link>
                                            <Link
                                                href={`/admin/users/${user.id}/edit`}
                                                className="text-yellow-600 hover:text-yellow-900"
                                            >
                                                <Edit size={18} />
                                            </Link>
                                            <button
                                                onClick={() => deleteUser(user.id)}
                                                className="text-red-600 hover:text-red-900"
                                            >
                                                <Trash2 size={18} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    <div className="px-6 py-4 border-t">
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-gray-700">
                                Showing {users.from} to {users.to} of {users.total} results
                            </div>
                            <div className="flex space-x-2">
                                {users.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`px-3 py-1 rounded ${
                                            link.active ? 'bg-blue-600 text-white' : 
                                            link.url ? 'bg-gray-200 hover:bg-gray-300' : 
                                            'bg-gray-100 text-gray-400 cursor-not-allowed'
                                        }`}
                                        preserveScroll
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}