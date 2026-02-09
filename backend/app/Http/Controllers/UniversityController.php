<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UniversityController extends Controller
{
    /**
     * 🔵 List all universities (for the universities screen in the app)
     */
    public function index(Request $request)
    {
        try {
            // Cache for 5 minutes to reduce database load
            $universities = \Cache::remember('universities_list', 300, function () {
                return University::query()
                    ->withCount('faculties')
                    ->orderBy('name')
                    ->get();
            });

            return response()->json([
                'data' => $universities,
            ]);
        } catch (\Exception $e) {
            Log::error('UniversityController::index - Error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to fetch universities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔵 Show a single university with its faculties
     */
    public function show(University $university)
    {
        try {
            $university->load('faculties');

            return response()->json([
                'data' => $university,
            ]);
        } catch (\Exception $e) {
            Log::error('UniversityController::show - Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch university',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔵 Get faculties for a given university
     */
    public function faculties(University $university)
    {
        try {
            // Cache faculties per university for 5 minutes
            $cacheKey = 'university_' . $university->id . '_faculties';
            $faculties = \Cache::remember($cacheKey, 300, function () use ($university) {
                return $university->faculties()
                    ->orderBy('name')
                    ->get();
            });

            return response()->json([
                'data' => $faculties,
            ]);
        } catch (\Exception $e) {
            Log::error('UniversityController::faculties - Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch faculties',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔵 Get university courses for a given faculty
     *
     * This powers: choose university → faculty → see courses.
     */
    public function coursesByFaculty(University $university, Faculty $faculty)
    {
        try {
            if ($faculty->university_id !== $university->id) {
                return response()->json([
                    'message' => 'Faculty does not belong to this university',
                ], 422);
            }

            $courses = $faculty->courses()
                ->with(['instructor'])
                ->where('is_university_course', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $courses,
            ]);
        } catch (\Exception $e) {
            Log::error('UniversityController::coursesByFaculty - Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch courses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
















