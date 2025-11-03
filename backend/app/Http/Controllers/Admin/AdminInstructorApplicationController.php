<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminInstructorApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = InstructorApplication::with(['user.profile']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by user name or email
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $applications = $query->paginate(15)->withQueryString();

        // Get statistics
        $stats = [
            'pending' => InstructorApplication::where('status', 'pending')->count(),
            'approved' => InstructorApplication::where('status', 'approved')->count(),
            'rejected' => InstructorApplication::where('status', 'rejected')->count(),
            'total' => InstructorApplication::count(),
        ];

        return Inertia::render('Admin/Applications/Index', [
            'applications' => $applications,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search', 'from_date', 'to_date']),
        ]);
    }

    public function show($id)
    {
        $application = InstructorApplication::with([
            'user.profile',
            'user.enrollments.course',
            'reviewer'
        ])->findOrFail($id);

        // Generate signed URLs for documents
        $documents = [];
        if ($application->documents) {
            foreach ($application->documents as $key => $doc) {
                if (isset($doc['path'])) {
                    $documents[$key] = [
                        'name' => $doc['name'] ?? $key,
                        'type' => $doc['type'] ?? 'document',
                        'size' => $doc['size'] ?? null,
                        'uploaded_at' => $doc['uploaded_at'] ?? null,
                        'url' => Storage::disk('s3')->temporaryUrl(
                            $doc['path'],
                            now()->addMinutes(30)
                        ),
                    ];
                }
            }
        }

        // Get user's learning history
        $learningHistory = $application->user->enrollments()
            ->with('course')
            ->latest()
            ->limit(5)
            ->get();

        // Check if user has any completed courses (for qualification assessment)
        $completedCourses = $application->user->enrollments()
            ->whereNotNull('completed_at')
            ->count();

        return Inertia::render('Admin/Applications/Show', [
            'application' => $application,
            'documents' => $documents,
            'learningHistory' => $learningHistory,
            'completedCourses' => $completedCourses,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $application = InstructorApplication::with('user')->findOrFail($id);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Only pending applications can be approved');
        }

         // GET THE AUTHENTICATED USER HERE
    $adminUser = $request->user(); // or Auth::user()

    $validated = $request->validate([
        'notes' => 'nullable|string|max:500',
        'commission_rate' => 'nullable|numeric|min:0|max:100',
    ]);

        DB::transaction(function () use ($application, $validated, $adminUser) {
            // Update application status
            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $adminUser ? $adminUser->id : null, 
                'review_notes' => $validated['notes'] ?? null,
                'additional_info' => array_merge($application->additional_info ?? [], [
                    'commission_rate' => $validated['commission_rate'] ?? 20, // Default 20%
                    'approved_at' => now()->toISOString(),
                ]),
            ]);

            // Update user role to instructor
            $user = $application->user;
$user->roles()->sync([2]); // 2 = instructor role ID
$user->update(['role' => 'instructor']);

            // Create instructor profile if needed
            if (!$application->user->instructorProfile) {
                $application->user->instructorProfile()->create([
                    'bio' => $application->additional_info['bio'] ?? '',
                    'expertise' => $application->additional_info['expertise'] ?? [],
                    'commission_rate' => $validated['commission_rate'] ?? 20,
                    'is_verified' => true,
                ]);
            }

            // TODO: Send approval email notification
            // Mail::to($application->user)->send(new InstructorApproved($application));
        });

        return redirect()->route('admin.applications.index')
            ->with('success', 'Application approved successfully. The user is now an instructor.');
    }

    public function reject(Request $request, $id)
{
    $application = InstructorApplication::with('user')->findOrFail($id);

    if ($application->status !== 'pending') {
        return back()->with('error', 'Only pending applications can be rejected');
    }

    // GET THE AUTHENTICATED USER HERE
    $adminUser = $request->user(); // or Auth::user()
    
    // If you want to be extra safe (though middleware should prevent this)
    if (!$adminUser) {
        return redirect()->route('login')->with('error', 'Authentication required');
    }

    $validated = $request->validate([
        'reason' => 'required|string|max:500',
        'can_reapply' => 'boolean',
        'reapply_after_days' => 'nullable|integer|min:1|max:365',
    ]);

    $application->update([
        'status' => 'rejected',
        'reviewed_at' => now(),
        'reviewed_by' => $adminUser->id, // Now using the defined variable
        'review_notes' => $validated['reason'],
        'additional_info' => array_merge($application->additional_info ?? [], [
            'can_reapply' => $validated['can_reapply'] ?? false,
            'reapply_after' => $validated['can_reapply'] 
                ? now()->addDays($validated['reapply_after_days'] ?? 30)->toISOString()
                : null,
            'rejected_at' => now()->toISOString(),
        ]),
    ]);
    // TODO: Send rejection email notification
    // Mail::to($application->user)->send(new InstructorRejected($application));

    return redirect()->route('admin.applications.index')
        ->with('success', 'Application rejected');
}

    public function bulkAction(Request $request)
{
    // GET THE AUTHENTICATED USER HERE
    $adminUser = $request->user(); // or Auth::user()
    
    // If you want to be extra safe (though middleware should prevent this)
    if (!$adminUser) {
        return redirect()->route('login')->with('error', 'Authentication required');
    }

    $validated = $request->validate([
        'action' => 'required|in:approve,reject',
        'application_ids' => 'required|array',
        'application_ids.*' => 'exists:instructor_applications,id',
        'notes' => 'nullable|string|max:500',
    ]);

    $applications = InstructorApplication::whereIn('id', $validated['application_ids'])
        ->where('status', 'pending')
        ->get();

    if ($applications->isEmpty()) {
        return back()->with('error', 'No pending applications found');
    }

    DB::transaction(function () use ($applications, $validated, $adminUser) {
        foreach ($applications as $application) {
            if ($validated['action'] === 'approve') {
                $application->update([
                    'status' => 'approved',
                    'reviewed_at' => now(),
                    'reviewed_by' => $adminUser->id, // Now using the defined variable
                    'review_notes' => $validated['notes'] ?? 'Bulk approved',
                ]);
                $application->user->syncRoles(['instructor']);
            } else {
                $application->update([
                    'status' => 'rejected',
                    'reviewed_at' => now(),
                    'reviewed_by' => $adminUser->id, // Now using the defined variable
                    'review_notes' => $validated['notes'] ?? 'Bulk rejected',
                ]);
            }
        }
    });

    $message = $validated['action'] === 'approve' 
        ? 'Applications approved successfully'
        : 'Applications rejected';

    return redirect()->route('admin.applications.index')
        ->with('success', $message);
}

    public function downloadDocument($applicationId, $documentKey)
    {
        $application = InstructorApplication::findOrFail($applicationId);
        
        if (!isset($application->documents[$documentKey])) {
            abort(404, 'Document not found');
        }

        $document = $application->documents[$documentKey];
        
        return Storage::disk('s3')->download(
            $document['path'],
            $document['name'] ?? 'document.pdf'
        );
    }

    public function statistics()
    {
        $stats = [
            'by_status' => InstructorApplication::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get(),
            
            'by_month' => InstructorApplication::select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                    DB::raw('count(*) as total'),
                    DB::raw("sum(case when status = 'approved' then 1 else 0 end) as approved"),
                    DB::raw("sum(case when status = 'rejected' then 1 else 0 end) as rejected")
                )
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            
            'average_review_time' => InstructorApplication::whereNotNull('reviewed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as hours')
                ->first()->hours,
            
            'approval_rate' => InstructorApplication::where('status', '!=', 'pending')->count() > 0
                ? (InstructorApplication::where('status', 'approved')->count() / 
                   InstructorApplication::where('status', '!=', 'pending')->count()) * 100
                : 0,
        ];

        return response()->json($stats);
    }
}