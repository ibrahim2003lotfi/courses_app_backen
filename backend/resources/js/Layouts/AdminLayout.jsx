import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
    Home, Users, BookOpen, DollarSign, 
    Menu, X, LogOut, ChevronDown,
    BarChart3, Settings, Bell
} from 'lucide-react';

export default function AdminLayout({ children }) {
    const { auth, flash } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [dropdownOpen, setDropdownOpen] = useState(false);

    // Safe access with fallbacks - don't block rendering
    const user = auth?.user || {};
    const userName = user?.name || 'Admin';
    const userEmail = user?.email || '';

    const navigation = [
        { name: 'Dashboard', href: '/admin', icon: Home },
        { name: 'Users', href: '/admin/users', icon: Users },
        { name: 'Courses', href: '/admin/courses', icon: BookOpen },
        { name: 'Orders', href: '/admin/orders', icon: DollarSign },
        { name: 'Payouts', href: '/admin/payouts', icon: BarChart3 },
        { name: 'Applications', href: '/admin/instructor-applications', icon: Settings },
    ];

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Mobile sidebar toggle */}
            <div className="lg:hidden fixed top-4 left-4 z-50">
                <button
                    onClick={() => setSidebarOpen(!sidebarOpen)}
                    className="p-2 rounded-md bg-white shadow-lg"
                >
                    {sidebarOpen ? <X size={24} /> : <Menu size={24} />}
                </button>
            </div>

            {/* Sidebar */}
            <div className={`fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 transform transition-transform lg:translate-x-0 ${
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            }`}>
                <div className="flex items-center justify-center h-16 bg-gray-800">
                    <span className="text-white text-xl font-semibold">Admin Panel</span>
                </div>

                <nav className="mt-8">
                    {navigation.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
                        >
                            <item.icon className="mr-3" size={20} />
                            <span>{item.name}</span>
                        </Link>
                    ))}
                </nav>

                <div className="absolute bottom-0 w-full p-4">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex items-center w-full px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
                    >
                        <LogOut className="mr-3" size={20} />
                        <span>Logout</span>
                    </Link>
                </div>
            </div>

            {/* Main content */}
            <div className="lg:ml-64">
                {/* Top bar */}
                <header className="bg-white shadow-sm">
                    <div className="flex items-center justify-between px-6 py-4">
                        <h2 className="text-xl font-semibold text-gray-800">
                            Welcome back, {userName}
                        </h2>
                        <div className="flex items-center space-x-4">
                            <button className="relative p-2 text-gray-600 hover:text-gray-900">
                                <Bell size={20} />
                                <span className="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                            </button>
                            <div className="flex items-center space-x-2">
                                <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <span className="text-xs font-medium text-white">
                                        {userName.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <span className="text-sm font-medium">{userEmail}</span>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mx-6 mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mx-6 mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        {flash.error}
                    </div>
                )}

                {/* Page content */}
                <main className="p-6">
                    {children}
                </main>
            </div>
        </div>
    );
}