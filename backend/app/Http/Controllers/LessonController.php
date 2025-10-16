<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Section;
use App\Models\Lesson;

class LessonController extends Controller
{
    /**
     * 🟢 عرض جميع الدروس في قسم معين
     */
    
public function index($courseId, $sectionId)
{
    $course = Course::where('id', $courseId)
        ->where('instructor_id', auth('sanctum')->id())
        ->first();

    if (!$course) {
        return response()->json(['message' => 'Course not found or unauthorized'], 404);
    }

    $section = Section::where('id', $sectionId)
        ->where('course_id', $courseId)
        ->first();

    if (!$section) {
        return response()->json(['message' => 'Section not found'], 404);
    }

    $lessons = Lesson::where('section_id', $sectionId)
        ->orderBy('position')
        ->get(['id', 'title', 'description', 'duration_seconds', 'is_preview', 'position']);

    return response()->json([
        'course' => $course->title,
        'section' => $section->title,
        'lessons' => $lessons,
    ]);
}

    /**
     * 🟢 إنشاء درس جديد
     */
    public function store(Request $request, $courseId, $sectionId)
    {
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $section = Section::where('id', $sectionId)
            ->where('course_id', $courseId)
            ->first();

        if (!$section) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            's3_key' => 'nullable|string', // يمكن رفعه لاحقًا
            'duration_seconds' => 'nullable|integer|min:1',
            'is_preview' => 'nullable|boolean',
            'position' => 'nullable|integer|min:1',
        ]);

        // حساب الـ position إذا لم يُحدد
        if (!isset($validated['position'])) {
            $maxPosition = Lesson::where('section_id', $sectionId)->max('position');
            $validated['position'] = $maxPosition ? $maxPosition + 1 : 1;
        }

        $lesson = Lesson::create([
            'section_id' => $sectionId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            's3_key' => $validated['s3_key'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'is_preview' => $validated['is_preview'] ?? false,
            'position' => $validated['position'],
        ]);

        return response()->json([
            'message' => 'Lesson created successfully',
            'lesson' => $lesson,
        ], 201);
    }

    /**
     * 🟡 تحديث درس
     */
    public function update(Request $request, $courseId, $sectionId, $lessonId)
    {
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $lesson = Lesson::where('id', $lessonId)
            ->where('section_id', $sectionId)
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            's3_key' => 'sometimes|string|nullable',
            'duration_seconds' => 'sometimes|integer|min:1|nullable',
            'is_preview' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:1',
        ]);

        $lesson->update($validated);

        return response()->json([
            'message' => 'Lesson updated successfully',
            'lesson' => $lesson,
        ]);
    }

    /**
     * 🔴 حذف درس
     */
    public function destroy($courseId, $sectionId, $lessonId)
    {
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $lesson = Lesson::where('id', $lessonId)
            ->where('section_id', $sectionId)
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        $lesson->delete();

        // إعادة ترتيب الدروس
        $this->reorderLessons($sectionId);

        return response()->json(['message' => 'Lesson deleted successfully']);
    }

    /**
     * 🔄 إعادة ترتيب الدروس
     */
    public function reorder(Request $request, $courseId, $sectionId)
    {
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $validated = $request->validate([
            'lessons' => 'required|array',
            'lessons.*.id' => 'required|uuid|exists:lessons,id',
            'lessons.*.position' => 'required|integer|min:1',
        ]);

        foreach ($validated['lessons'] as $item) {
            Lesson::where('id', $item['id'])
                ->where('section_id', $sectionId)
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Lessons reordered successfully']);
    }

    /**
     * Helper: إعادة ترتيب تلقائي
     */
    private function reorderLessons($sectionId)
    {
        $lessons = Lesson::where('section_id', $sectionId)
            ->orderBy('position')
            ->get();

        foreach ($lessons as $index => $lesson) {
            $lesson->update(['position' => $index + 1]);
        }
    }
}
