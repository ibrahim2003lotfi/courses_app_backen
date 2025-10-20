<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InstructorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InstructorApplicationController extends Controller
{
    /**
     * Submit instructor application
     */
    public function apply(Request $request)
    {
        $user = Auth::user();

        // Check if user already has an application
        $existingApplication = InstructorApplication::where('user_id', $user->id)->first();
        
        if ($existingApplication) {
            return response()->json([
                'message' => 'You already have an application',
                'application' => $existingApplication,
            ], 400);
        }

        // Check if user is already an instructor
        if ($user->hasRole('instructor')) {
            return response()->json([
                'message' => 'You are already an instructor',
            ], 400);
        }

        $validated = $request->validate([
            'documents' => 'required|array',
            'documents.*.type' => 'required|string|in:id,certificate,resume',
            'documents.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'bio' => 'required|string|max:1000',
            'expertise' => 'required|array',
            'expertise.*' => 'string|max:100',
        ]);

        // Upload documents
        $documents = [];
        foreach ($request->file('documents') as $index => $docData) {
            $file = $docData['file'];
            $type = $validated['documents'][$index]['type'];
            
            // Store encrypted
            $path = $file->store('kyc-documents/' . $user->id, 's3');
            
            $documents[] = [
                'type' => $type,
                'url' => $path,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_at' => now(),
            ];
        }

        // Create application
        $application = InstructorApplication::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'documents' => $documents,
            'additional_info' => [
                'bio' => $validated['bio'],
                'expertise' => $validated['expertise'],
            ],
        ]);

        return response()->json([
            'message' => 'Application submitted successfully',
            'application' => $application,
        ], 201);
    }

    /**
     * Get user's application status
     */
    public function myApplication()
    {
        $user = Auth::user();
        $application = InstructorApplication::where('user_id', $user->id)->first();

        if (!$application) {
            return response()->json([
                'message' => 'No application found',
                'can_apply' => !$user->hasRole('instructor'),
            ], 404);
        }

        return response()->json($application);
    }
}