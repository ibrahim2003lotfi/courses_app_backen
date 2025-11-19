<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Order;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->role) {
            $query->role($request->role);
        }

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function show($id)
    {
        $user = User::with(['profile', 'roles', 'courses', 'enrollments.course'])
            ->findOrFail($id);

        $stats = [
            'totalCourses' => $user->courses()->count(),
            'totalEnrollments' => $user->enrollments()->count(),
            'totalSpent' => Order::where('user_id', $user->id)
                ->where('status', 'succeeded')
                ->sum('amount'),
        ];

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'availableRoles' => ['admin', 'instructor', 'student'],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'availableRoles' => ['admin', 'instructor', 'student'],
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|string|in:student,instructor,admin',
        ]);

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return redirect()->back()->with('success', 'User updated successfully');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:student,instructor,admin',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'is_verified' => true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully');
    }
}