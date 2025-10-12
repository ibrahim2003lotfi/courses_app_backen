<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /**
     * 🟢 Instructor creates a new course.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'level' => 'in:beginner,intermediate,advanced',
            'category_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $slug = Str::slug($validated['title']);
        $count = Course::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        $course = Course::create([
            'instructor_id' => auth('sanctum')->id(),
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? '',
            'price' => $validated['price'] ?? 0,
            'level' => $validated['level'] ?? 'beginner',
        ]);

        return response()->json([
            'message' => 'Course created successfully',
            'course' => $course,
        ], 201);
    }

    /**
     * 🟡 Instructor views their own courses (no pagination).
     */
    public function index()
    {
        $user = auth('sanctum')->user();

        $courses = Course::where('instructor_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'instructor' => $user->name,
            'total_courses' => $courses->count(),
            'courses' => $courses,
        ]);
    }

    /**
     * 🔵 Public courses listing (with pagination)
     */
    /**
 * 🔵 Public courses listing (with pagination, search, and filters)
 */
public function publicIndex(Request $request)
{
    // 🔍 البحث بالكلمة المفتاحية (مثلاً: Laravel)
    $search = $request->query('search');

    // 🎚️ الفلاتر
    $level = $request->query('level'); // beginner, intermediate, advanced
    $minPrice = $request->query('min_price');
    $maxPrice = $request->query('max_price');
    $categoryId = $request->query('category_id');

    // 📄 Pagination params
    $perPage = (int) $request->query('per_page', 5);
    $page = (int) $request->query('page', 1);

    // 🧠 بناء الاستعلام
    $query = Course::query();

    // 🔍 بحث حسب العنوان أو الوصف
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'ILIKE', "%{$search}%")
              ->orWhere('description', 'ILIKE', "%{$search}%");
        });
    }

    // 🎚️ فلترة حسب المستوى
    if ($level) {
        $query->where('level', $level);
    }

    // 💰 فلترة حسب السعر
    if ($minPrice) {
        $query->where('price', '>=', $minPrice);
    }
    if ($maxPrice) {
        $query->where('price', '<=', $maxPrice);
    }

    // 🏷️ فلترة حسب الفئة (category)
    if ($categoryId) {
        $query->where('category_id', $categoryId);
    }

    // 🕒 ترتيب حسب الأحدث
    $query->orderBy('created_at', 'desc');

    // 📄 تنفيذ pagination
    $courses = $query->paginate($perPage, ['*'], 'page', $page);

    return response()->json($courses);
}


/**
 * 🟢 عرض تفاصيل كورس واحد باستخدام الـ slug
 */
public function show($slug)
{
    // 🔍 البحث عن الكورس حسب الـ slug
    $course = \App\Models\Course::with(['instructor', 'category'])
        ->where('slug', $slug)
        ->first();

    // ❌ في حال لم يتم العثور عليه
    if (!$course) {
        return response()->json(['message' => 'Course not found'], 404);
    }

    return response()->json([
        'message' => 'Course details retrieved successfully',
        'course' => $course,
    ]);
}


    /**
     * 🟠 Instructor updates a course.
     */
    public function update(Request $request, $id)
    {
        $course = Course::where('id', $id)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or not authorized'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'price' => 'sometimes|numeric|min:0',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'category_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully',
            'course' => $course,
        ]);
    }

    /**
     * 🔴 Instructor deletes a course.
     */
    public function destroy($id)
    {
        $user = auth('sanctum')->user();

        $course = Course::where('id', $id)
            ->where('instructor_id', $user->id)
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized'], 404);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }
}
