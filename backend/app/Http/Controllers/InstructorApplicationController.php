<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstructorApplicationRequest;
use App\Models\InstructorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstructorApplicationController extends Controller
{
    /**
     * Submit instructor application
     */
    public function apply(InstructorApplicationRequest $request)
    {
        try {
            $user = Auth::user();

            // Check if user already has a pending application
            $existingApplication = InstructorApplication::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();
            
            if ($existingApplication) {
                if ($existingApplication->status === 'approved') {
                    return response()->json([
                        'success' => false,
                        'message' => 'أنت بالفعل مدرس معتمد',
                        'application' => $existingApplication,
                    ], 400);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'لديك طلب قيد المراجعة بالفعل',
                    'application' => $existingApplication,
                ], 400);
            }

            // Check if user is already an instructor
            if ($user->hasRole('instructor')) {
                return response()->json([
                    'success' => false,
                    'message' => 'أنت بالفعل مدرس',
                ], 400);
            }

            // Handle certificate uploads
            $certificates = [];
            if ($request->hasFile('certificates')) {
                foreach ($request->file('certificates') as $file) {
                    $path = $file->store(
                        'instructor-certificates/' . $user->id,
                        'public' // or 's3' for production
                    );
                    
                    $certificates[] = [
                        'id' => (string) Str::uuid(),
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'uploaded_at' => now()->toISOString(),
                    ];
                }
            }

            // Parse department to extract main category and subcategory
            $departmentParts = explode(' - ', $request->department);
            $mainDepartment = $departmentParts[0] ?? $request->department;
            $specialization = $departmentParts[1] ?? null;

            // Create application
            $application = InstructorApplication::create([
                'user_id' => $user->id,
                'education_level' => $request->education_level,
                'department' => $mainDepartment,
                'specialization' => $specialization ?? $request->specialization,
                'years_of_experience' => $request->years_of_experience,
                'experience_description' => $request->experience_description,
                'linkedin_url' => $request->linkedin_url,
                'portfolio_url' => $request->portfolio_url,
                'certificates' => $certificates,
                'agreed_to_terms' => true,
                'terms_agreed_at' => now(),
                'status' => 'pending',
                'additional_info' => [
                    'submitted_from' => 'mobile_app',
                    'app_version' => $request->header('X-App-Version', 'unknown'),
                    'device_info' => $request->header('X-Device-Info', 'unknown'),
                ],
            ]);

            Log::info('Instructor application submitted', [
                'user_id' => $user->id,
                'application_id' => $application->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلبك بنجاح! سيتم مراجعته خلال 2-3 أيام عمل.',
                'application' => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'status_label' => $application->status_label,
                    'created_at' => $application->created_at->toISOString(),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Instructor application failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الطلب. يرجى المحاولة مرة أخرى.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get user's application status
     */
    public function myApplication()
    {
        $user = Auth::user();
        
        $application = InstructorApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$application) {
            return response()->json([
                'success' => true,
                'has_application' => false,
                'can_apply' => !$user->hasRole('instructor'),
                'message' => 'لم تقدم أي طلب بعد',
            ]);
        }

        // Generate signed URLs for certificates
        $certificates = [];
        if ($application->certificates) {
            foreach ($application->certificates as $cert) {
                $certificates[] = [
                    'id' => $cert['id'] ?? null,
                    'name' => $cert['name'] ?? 'document',
                    'size' => $cert['size'] ?? 0,
                    'url' => isset($cert['path']) 
                        ? Storage::disk('public')->url($cert['path'])
                        : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'has_application' => true,
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'status_label' => $application->status_label,
                'status_color' => $application->status_color,
                'education_level' => $application->education_level,
                'department' => $application->department,
                'specialization' => $application->specialization,
                'years_of_experience' => $application->years_of_experience,
                'experience_description' => $application->experience_description,
                'linkedin_url' => $application->linkedin_url,
                'portfolio_url' => $application->portfolio_url,
                'certificates' => $certificates,
                'review_notes' => $application->review_notes,
                'reviewed_at' => $application->reviewed_at?->toISOString(),
                'created_at' => $application->created_at->toISOString(),
                'updated_at' => $application->updated_at->toISOString(),
            ],
            'can_reapply' => $application->status === 'rejected' && 
                ($application->additional_info['can_reapply'] ?? true),
        ]);
    }

    /**
     * Cancel pending application
     */
    public function cancel()
    {
        $user = Auth::user();
        
        $application = InstructorApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد طلب قيد المراجعة للإلغاء',
            ], 404);
        }

        // Delete uploaded certificates
        if ($application->certificates) {
            foreach ($application->certificates as $cert) {
                if (isset($cert['path'])) {
                    Storage::disk('public')->delete($cert['path']);
                }
            }
        }

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الطلب بنجاح',
        ]);
    }

    /**
     * Reapply after rejection
     */
    public function reapply(InstructorApplicationRequest $request)
    {
        $user = Auth::user();
        
        $previousApplication = InstructorApplication::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->latest()
            ->first();

        if (!$previousApplication) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك إعادة التقديم',
            ], 400);
        }

        // Check if can reapply
        $canReapply = $previousApplication->additional_info['can_reapply'] ?? true;
        $reapplyAfter = $previousApplication->additional_info['reapply_after'] ?? null;

        if (!$canReapply) {
            return response()->json([
                'success' => false,
                'message' => 'غير مسموح بإعادة التقديم',
            ], 400);
        }

        if ($reapplyAfter && now()->lt($reapplyAfter)) {
            return response()->json([
                'success' => false,
                'message' => 'يمكنك إعادة التقديم بعد ' . \Carbon\Carbon::parse($reapplyAfter)->diffForHumans(),
            ], 400);
        }

        // Delete old application
        $previousApplication->delete();

        // Submit new application
        return $this->apply($request);
    }
}