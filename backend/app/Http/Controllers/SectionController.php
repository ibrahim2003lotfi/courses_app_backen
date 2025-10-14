<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Section;

class SectionController extends Controller
{
    /**
     * 🟢 عرض جميع الأقسام لكورس معين
     */
    public function index($courseId)
    {
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $sections = Section::where('course_id', $courseId)
            ->orderBy('position')
            ->with('lessons')
            ->get();

        return response()->json([
            'course' => $course->title,
            'sections' => $sections,
        ]);
    }

    /**
     * 🟢 إنشاء قسم جديد
     */
    public function store(Request $request, $courseId)
    {
        // التحقق من أن المدرّس يملك هذا الكورس
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'position' => 'nullable|integer|min:1',
        ]);

        // إذا لم يتم تحديد position، ضعه في آخر القائمة
        if (!isset($validated['position'])) {
            $maxPosition = Section::where('course_id', $courseId)->max('position');
            $validated['position'] = $maxPosition ? $maxPosition + 1 : 1;
        }

        $section = Section::create([
            'course_id' => $courseId,
            'title' => $validated['title'],
            'position' => $validated['position'],
        ]);

        return response()->json([
            'message' => 'Section created successfully',
            'section' => $section,
        ], 201);
    }

    /**
     * 🟡 تحديث قسم
     */
    public function update(Request $request, $courseId, $sectionId)
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
            'title' => 'sometimes|string|max:255',
            'position' => 'sometimes|integer|min:1',
        ]);

        $section->update($validated);

        return response()->json([
            'message' => 'Section updated successfully',
            'section' => $section,
        ]);
    }

    /**
     * 🔴 حذف قسم
     */
    public function destroy($courseId, $sectionId)
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

        if ($section->lessons()->count() > 0) {
        return response()->json([
            'message' => 'Cannot delete section with lessons. Please delete lessons first.'
        ], 400);
    }

        // حذف القسم سيحذف جميع الدروس تلقائياً (cascade)
        $section->delete();

        // إعادة ترتيب المواضع
        $this->reorderSections($courseId);

        return response()->json(['message' => 'Section deleted successfully']);
    }

    /**
     * 🔄 إعادة ترتيب الأقسام
     */
    public function reorder(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|uuid|exists:sections,id',
            'sections.*.position' => 'required|integer|min:1',
        ]);

        foreach ($validated['sections'] as $item) {
            Section::where('id', $item['id'])
                ->where('course_id', $courseId)
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Sections reordered successfully']);
    }

    /**
     * Helper: إعادة ترتيب تلقائي بعد الحذف
     */
    private function reorderSections($courseId)
    {
        $sections = Section::where('course_id', $courseId)
            ->orderBy('position')
            ->get();

        foreach ($sections as $index => $section) {
            $section->update(['position' => $index + 1]);
        }
    }
}