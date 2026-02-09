<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    /**
     * 🟢 Instructor creates a new course.
     */
    public function store(Request $request)
    {
        try {
            // ✅ Check authentication FIRST before any processing
            $user = auth('sanctum')->user();
            
            if (!$user) {
                Log::warning('Course creation failed: No authenticated user');
                return response()->json([
                    'success' => false,
                    'error' => 'no_user',
                    'message' => 'يجب تسجيل الدخول أولاً لإنشاء دورة'
                ], 401);
            }
            
            Log::info('Course creation started', ['user_id' => $user->id]);

            // Validation
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'level' => 'in:beginner,intermediate,advanced',
                'category_id' => 'nullable|uuid|exists:categories,id',
                'category_name' => 'nullable|string|max:255',
                'thumbnail_image' => 'nullable|image|max:10240', // 10MB max
                'lessons_json' => 'nullable|string', // JSON array of lessons metadata
                'type' => 'nullable|string|in:regular,university', // to differentiate course types
                'university_id' => 'nullable|uuid|exists:universities,id',
                'university_name' => 'nullable|string|max:255',
                'faculty_id' => 'nullable|uuid|exists:faculties,id',
                'faculty_name' => 'nullable|string|max:255',
                'is_university_course' => 'nullable|boolean',
            ]);

            Log::info('Course validation passed', ['validated' => $validated]);

            // Handle category - create if category_name provided but no category_id
            $categoryId = $validated['category_id'] ?? null;
            if (!$categoryId && !empty($validated['category_name'])) {
                $category = \App\Models\Category::firstOrCreate(
                    ['name' => $validated['category_name']],
                    [
                        'slug' => Str::slug($validated['category_name']),
                        'description' => 'Category: ' . $validated['category_name'],
                    ]
                );
                $categoryId = $category->id;
            }

            // Generate slug
            $slug = Str::slug($validated['title']);
            $count = Course::where('slug', 'LIKE', "{$slug}%")->count();
            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }

            // Handle thumbnail image upload
            $courseImageUrl = null;
            if ($request->hasFile('thumbnail_image')) {
                try {
                    Log::info('Processing thumbnail image');
                    
                    // Check if file is valid
                    $file = $request->file('thumbnail_image');
                    if (!$file->isValid()) {
                        Log::error('Invalid thumbnail image uploaded');
                        return response()->json([
                            'success' => false,
                            'error' => 'invalid_file',
                            'message' => 'الملف المرفوع غير صالح'
                        ], 422);
                    }
                    
                    // Ensure storage directory exists
                    $storagePath = storage_path('app/public/courses/thumbnails');
                    if (!file_exists($storagePath)) {
                        mkdir($storagePath, 0755, true);
                    }
                    
                    $path = $file->store('courses/thumbnails', 'public');
                    $courseImageUrl = url('storage/' . $path);
                    Log::info('Thumbnail stored', ['path' => $path, 'url' => $courseImageUrl]);
                } catch (\Exception $e) {
                    Log::error('Failed to store thumbnail: ' . $e->getMessage());
                    // Continue without image - don't fail the entire request
                    $courseImageUrl = null;
                }
            }

            // Determine if this is a university course
            $isUniversityCourse = ($validated['type'] ?? 'regular') === 'university' || 
                                  !empty($validated['university_id']) ||
                                  !empty($validated['university_name']) ||
                                  ($validated['is_university_course'] ?? false);

            // Handle university/faculty - use IDs if provided, otherwise create from names
            $universityId = $validated['university_id'] ?? null;
            $facultyId = $validated['faculty_id'] ?? null;
            
            if ($isUniversityCourse) {
                Log::info('Processing university course data');
                
                // If university_id not provided but university_name is, create/find university
                if (!$universityId && !empty($validated['university_name'])) {
                    $university = \App\Models\University::firstOrCreate(
                        ['name' => $validated['university_name']],
                        ['slug' => Str::slug($validated['university_name'])]
                    );
                    $universityId = $university->id;
                }
                
                // If faculty_id not provided but faculty_name is, create/find faculty
                if (!$facultyId && !empty($validated['faculty_name']) && $universityId) {
                    $faculty = \App\Models\Faculty::firstOrCreate(
                        [
                            'university_id' => $universityId,
                            'name' => $validated['faculty_name']
                        ],
                        ['slug' => Str::slug($validated['faculty_name'])]
                    );
                    $facultyId = $faculty->id;
                }
                
                Log::info('University course IDs', ['university_id' => $universityId, 'faculty_id' => $facultyId]);
            }

            // Parse lessons from JSON
            $lessons = [];
            $totalDuration = 0;
            if (!empty($validated['lessons_json'])) {
                $lessons = json_decode($validated['lessons_json'], true) ?? [];
                Log::info('Parsed lessons', ['count' => count($lessons)]);
            }

            // Auto-calculate hours and lessons count
            $lessonsCount = count($lessons);
            $hoursCount = ceil($lessonsCount * 0.5); // Estimate 30 min per lesson average

            Log::info('Creating course record');
            $course = Course::create([
                'instructor_id' => $user->id,  // ✅ Use authenticated user
                'category_id' => $categoryId,
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'] ?? '',
                'price' => $validated['price'] ?? 0,
                'level' => $validated['level'] ?? 'beginner',
                'course_image_url' => $courseImageUrl,
                'is_university_course' => $isUniversityCourse,
                'university_id' => $universityId,
                'faculty_id' => $facultyId,
                'duration_hours' => $hoursCount,
                'lessons_count' => $lessonsCount,
            ]);

            Log::info('Course created', ['course_id' => $course->id]);

            // Create sections and lessons if provided
            if (!empty($lessons)) {
                Log::info('Creating sections and lessons');
                // Create a default section
                $section = \App\Models\Section::create([
                    'course_id' => $course->id,
                    'title' => 'محتوى الدورة',
                    'position' => 1,
                ]);

                foreach ($lessons as $index => $lessonData) {
                    \App\Models\Lesson::create([
                        'section_id' => $section->id,
                        'title' => $lessonData['title'] ?? 'Lesson ' . ($index + 1),
                        'description' => $lessonData['description'] ?? '',
                        'position' => $index + 1,
                        'is_preview' => false,
                    ]);
                }
                Log::info('Sections and lessons created');
            }

            return response()->json([
                'success' => true,
                'message' => $isUniversityCourse ? 'University course created successfully' : 'Course created successfully',
                'course' => $course->fresh(['category', 'university', 'faculty']),
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Course creation validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Course creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error creating course: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🟢 Instructor creates a new university course.
     */
    public function storeUniversityCourse(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'level' => 'nullable|in:beginner,intermediate,advanced',
            'university_id' => 'nullable|uuid|exists:universities,id',
            'faculty_id' => 'nullable|uuid|exists:faculties,id',
            'course_image' => 'nullable|image|max:5120',
            'instructor_image' => 'nullable|image|max:5120',
        ]);

        $slug = Str::slug($validated['title']);
        $count = Course::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        $courseImageUrl = null;
        if ($request->hasFile('course_image')) {
            $path = $request->file('course_image')->store('courses/images', 'public');
            $courseImageUrl = url('storage/' . $path);
        }

        $instructorImageUrl = null;
        if ($request->hasFile('instructor_image')) {
            $path = $request->file('instructor_image')->store('courses/instructors', 'public');
            $instructorImageUrl = url('storage/' . $path);
        }

        $course = Course::create([
            'instructor_id' => auth('sanctum')->id(),
            'category_id' => null,
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? '',
            'price' => $validated['price'] ?? 0,
            'level' => $validated['level'] ?? 'beginner',
            'is_university_course' => true,
            'university_id' => $validated['university_id'] ?? null,
            'faculty_id' => $validated['faculty_id'] ?? null,
            'course_image_url' => $courseImageUrl,
            'instructor_image_url' => $instructorImageUrl,
        ]);

        return response()->json([
            'message' => 'University course created successfully',
            'course' => $course,
        ], 201);
    }
    /**
     * 🟡 Instructor views their own courses (no pagination).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'no_user',
                'message' => 'يجب تسجيل الدخول أولاً',
                'courses' => [],
            ], 401);
        }

        // Fetch all courses for this instructor (both regular and university courses)
        $courses = Course::where('instructor_id', $user->id)
            ->with(['category', 'university', 'faculty'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
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

    // 📄 تنفيذ pagination مع تحميل العلاقات
    $courses = $query->with(['category', 'instructor'])->paginate($perPage, ['*'], 'page', $page);

    return response()->json($courses);
}


/**
 * 🟢 عرض تفاصيل كورس واحد باستخدام الـ slug
 */
// في CourseController في دالة show
public function show($slug)
{
    // Try to find by slug first, then by ID if slug fails
    $course = Course::with([
        'instructor', 
        'category', 
        'sections' => function($query) {
            $query->orderBy('position');
        },
        'sections.lessons' => function($query) {
            $query->orderBy('position');
        }
    ])
    ->where('slug', $slug)
    ->first();

    // If not found by slug, try by ID
    if (!$course) {
        $course = Course::with([
            'instructor', 
            'category', 
            'sections' => function($query) {
                $query->orderBy('position');
            },
            'sections.lessons' => function($query) {
                $query->orderBy('position');
            }
        ])
        ->where('id', $slug)
        ->first();
    }

    if (!$course) {
        return response()->json(['message' => 'Course not found'], 404);
    }

    return response()->json([
        'message' => 'Course details retrieved successfully',
        'course' => $course,
        'rating_info' => $course->getRatingInfo(),
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

    /**
     * 🎥 Upload video for a lesson.
     */
    public function uploadLessonVideo(Request $request, $courseId, $lessonId)
    {
        try {
            $user = auth('sanctum')->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'no_user',
                    'message' => 'يجب تسجيل الدخول أولاً'
                ], 401);
            }

            // Verify course belongs to instructor
            $course = Course::where('id', $courseId)
                ->where('instructor_id', $user->id)
                ->first();

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'الدورة غير موجودة أو ليست لك'
                ], 404);
            }

            // Verify lesson belongs to course
            $lesson = \App\Models\Lesson::where('id', $lessonId)
                ->whereHas('section', function($query) use ($courseId) {
                    $query->where('course_id', $courseId);
                })
                ->first();

            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'error' => 'lesson_not_found',
                    'message' => 'الدرس غير موجود'
                ], 404);
            }

            // Validate video file
            $validated = $request->validate([
                'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:524288', // 512MB max
            ]);

            if (!$request->hasFile('video') || !$request->file('video')->isValid()) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_file',
                    'message' => 'ملف الفيديو غير صالح'
                ], 422);
            }

            // Ensure storage directory exists
            $storagePath = storage_path('app/public/courses/videos');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Store video file
            $file = $request->file('video');
            $path = $file->store('courses/videos', 'public');
            $videoUrl = url('storage/' . $path);

            // Update lesson with video URL
            $lesson->update([
                'video_url' => $videoUrl,
                'duration' => $request->input('duration', 0),
            ]);

            Log::info('Lesson video uploaded', [
                'user_id' => $user->id,
                'course_id' => $courseId,
                'lesson_id' => $lessonId,
                'path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الفيديو بنجاح',
                'video_url' => $videoUrl,
                'lesson' => $lesson,
            ]);

        } catch (\Exception $e) {
            Log::error('Video upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'upload_failed',
                'message' => 'فشل رفع الفيديو: ' . $e->getMessage()
            ], 500);
        }
    }
}
