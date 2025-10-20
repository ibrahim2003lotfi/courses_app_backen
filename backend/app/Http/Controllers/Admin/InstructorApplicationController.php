<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class InstructorApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = InstructorApplication::with('user');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sort
        $query->orderBy('created_at', 'desc');

        $applications = $query->paginate(20);

        return response()->json($applications);
    }

    public function show($id)
    {
        $application = InstructorApplication::with(['user.profile'])
            ->findOrFail($id);

        // Decrypt document URLs if encrypted
        $documents = $application->documents;
        if ($documents) {
            // Assuming documents are stored as JSON with encrypted URLs
            foreach ($documents as $key => $doc) {
                if (isset($doc['url'])) {
                    // Generate temporary signed URL for document viewing
                    $documents[$key]['signed_url'] = Storage::disk('s3')->temporaryUrl(
                        $doc['url'],
                        now()->addMinutes(30)
                    );
                }
            }
        }

        return response()->json([
            'application' => $application,
            'documents' => $documents,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $application = InstructorApplication::with('user')->findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application has already been processed'
            ], 400);
        }

        // Get the authenticated admin user
        $adminUser = Auth::user();
        if (!$adminUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        DB::transaction(function () use ($application, $request, $adminUser) {
            // Update application status
            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $adminUser->id, // Now guaranteed to have a value
                'review_notes' => $request->input('notes'),
            ]);

            // Update user role to instructor
            $application->user->syncRoles(['instructor']);

            // TODO: Send approval email to user
        });

        return response()->json([
            'message' => 'Application approved successfully',
            'application' => $application->fresh('user'),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $application = InstructorApplication::with('user')->findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application has already been processed'
            ], 400);
        }

        // Get the authenticated admin user
        $adminUser = Auth::user();
        if (!$adminUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $application->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $adminUser->id, // Now guaranteed to have a value
            'review_notes' => $request->input('reason'),
        ]);

        // TODO: Send rejection email to user

        return response()->json([
            'message' => 'Application rejected',
            'application' => $application->fresh('user'),
        ]);
    }
}