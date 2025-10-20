<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->role($request->input('role'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(20);

        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::with(['profile', 'roles', 'courses', 'enrollments.course'])
            ->findOrFail($id);

        // Get user statistics
        $stats = [
            'total_courses' => $user->courses()->count(),
            'total_students' => $user->courses()->sum('total_students'),
            'total_revenue' => Order::where('user_id', $user->id)
                ->where('status', 'succeeded')
                ->sum('amount'),
            'total_spent' => Order::whereHas('course', function($q) use ($user) {
                    $q->where('instructor_id', $user->id);
                })
                ->where('status', 'succeeded')
                ->sum('amount'),
        ];

        return response()->json([
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|string|in:student,instructor,admin',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->load('roles'),
        ]);
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        // Toggle user active status (you'll need to add 'is_active' column)
        // For now, we'll use deleted_at for soft delete
        if ($user->trashed()) {
            $user->restore();
            $message = 'User activated successfully';
        } else {
            $user->delete();
            $message = 'User deactivated successfully';
        }

        return response()->json(['message' => $message]);
    }
}