<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\University;
use Illuminate\Http\Request;

class UniversityController extends Controller
{
    /**
     * 🔵 List all universities (for the universities screen in the app)
     */
    public function index(Request $request)
    {
        $universities = University::query()
            ->withCount('faculties')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $universities,
        ]);
    }

    /**
     * 🔵 Show a single university with its faculties
     */
    public function show(University $university)
    {
        $university->load('faculties');

        return response()->json([
            'data' => $university,
        ]);
    }

    /**
     * 🔵 Get faculties for a given university
     */
    public function faculties(University $university)
    {
        $faculties = $university->faculties()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $faculties,
        ]);
    }

    /**
     * 🔵 Get university courses for a given faculty
     *
     * This powers: choose university → faculty → see courses.
     */
    public function coursesByFaculty(University $university, Faculty $faculty)
    {
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
    }
}













