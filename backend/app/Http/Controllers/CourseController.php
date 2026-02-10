<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    error_log(">>> PUBLIC INDEX START");
    
    try {
        ini_set('memory_limit', '256M');
        
        // 🔍 البحث بالكلمة المفتاحية (مثلاً: Laravel)
        $search = $request->query('search');
        error_log(">>> Search: $search");

        // 🎚️ الفلاتر
        $level = $request->query('level'); // beginner, intermediate, advanced
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $categoryId = $request->query('category_id');
        error_log(">>> CategoryId: $categoryId");

        // 📄 Pagination params
        $perPage = (int) $request->query('per_page', 5);
        $page = (int) $request->query('page', 1);

        // Use DB query instead of Eloquent with relationships
        $query = DB::table('courses')
            ->leftJoin('users', 'courses.instructor_id', '=', 'users.id')
            ->leftJoin('categories', 'courses.category_id', '=', 'categories.id')
            ->select([
                'courses.*',
                'users.name as instructor_name',
                'users.email as instructor_email',
                'categories.name as category_name',
                'categories.slug as category_slug'
            ]);

        // 🔍 بحث حسب العنوان أو الوصف
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('courses.title', 'ILIKE', "%{$search}%")
                  ->orWhere('courses.description', 'ILIKE', "%{$search}%");
            });
        }

        // 🎚️ فلترة حسب المستوى
        if ($level) {
            $query->where('courses.level', $level);
        }

        // 💰 فلترة حسب السعر
        if ($minPrice) {
            $query->where('courses.price', '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where('courses.price', '<=', $maxPrice);
        }

        // 🏷️ فلترة حسب الفئة (category)
        if ($categoryId) {
            // Validate UUID format before querying
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $categoryId)) {
                $query->where('courses.category_id', $categoryId);
            } else {
                error_log(">>> Invalid category_id format, ignoring filter");
            }
        }

        // 🕒 ترتيب حسب الأحدث
        $query->orderBy('courses.created_at', 'desc');

        error_log(">>> Executing query...");
        
        // 📄 تنفيذ pagination
        $courses = $query->paginate($perPage, ['*'], 'page', $page);
        
        // Transform results to include instructor and category objects
        $transformedData = collect($courses->items())->map(function($course) {
            $courseArr = (array) $course;
            if ($course->instructor_name) {
                $courseArr['instructor'] = [
                    'id' => $course->instructor_id,
                    'name' => $course->instructor_name,
                    'email' => $course->instructor_email
                ];
            }
            if ($course->category_name) {
                $courseArr['category'] = [
                    'id' => $course->category_id,
                    'name' => $course->category_name,
                    'slug' => $course->category_slug
                ];
            }
            return $courseArr;
        });
        
        error_log(">>> PUBLIC INDEX SUCCESS: " . count($courses->items()) . " courses");

        return response()->json([
            'data' => $transformedData,
            'current_page' => $courses->currentPage(),
            'last_page' => $courses->lastPage(),
            'per_page' => $courses->perPage(),
            'total' => $courses->total(),
        ]);
    } catch (\Exception $e) {
        error_log('>>> PUBLIC INDEX ERROR: ' . $e->getMessage());
        error_log('>>> TRACE: ' . $e->getTraceAsString());
        return response()->json([
            'message' => 'Error fetching courses',
            'error' => $e->getMessage()
        ], 500);
    }
}


/**
 * 🟢 عرض تفاصيل كورس واحد باستخدام الـ slug - SIMPLIFIED to prevent memory crashes
 */
public function show($slug)
{
    ini_set('memory_limit', '256M');
    error_log(">>> COURSE SHOW START: slug=$slug");
    
    try {
        // Check if input looks like a UUID
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $slug);
        error_log(">>> isUuid: " . ($isUuid ? 'yes' : 'no'));
        
        // Try to find course by slug first
        error_log(">>> Searching by slug...");
        $courseData = DB::table('courses')
            ->leftJoin('users', 'courses.instructor_id', '=', 'users.id')
            ->leftJoin('categories', 'courses.category_id', '=', 'categories.id')
            ->select([
                'courses.*',
                'users.name as instructor_name',
                'users.email as instructor_email',
                'categories.name as category_name',
                'categories.slug as category_slug'
            ])
            ->where('courses.slug', $slug)
            ->first();
        
        error_log(">>> Found by slug: " . ($courseData ? 'yes' : 'no'));
        
        // If not found by slug and input looks like UUID, try by ID
        if (!$courseData && $isUuid) {
            error_log(">>> Searching by UUID...");
            $courseData = DB::table('courses')
                ->leftJoin('users', 'courses.instructor_id', '=', 'users.id')
                ->leftJoin('categories', 'courses.category_id', '=', 'categories.id')
                ->select([
                    'courses.*',
                    'users.name as instructor_name',
                    'users.email as instructor_email',
                    'categories.name as category_name',
                    'categories.slug as category_slug'
                ])
                ->where('courses.id', $slug)
                ->first();
            error_log(">>> Found by UUID: " . ($courseData ? 'yes' : 'no'));
        }
        
        if (!$courseData) {
            error_log(">>> Course not found");
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        error_log(">>> Course found: " . $courseData->title);
        
        // Convert to array
        $course = (array) $courseData;
        $courseId = $course['id'];
        error_log(">>> Course ID: $courseId");
        
        // Get sections - use chunking to avoid memory issues
        error_log(">>> Fetching sections...");
        $sectionRows = DB::table('sections')
            ->where('course_id', $courseId)
            ->orderBy('position')
            ->get();
        
        error_log(">>> Found " . count($sectionRows) . " sections");
        
        $sections = [];
        foreach ($sectionRows as $section) {
            $sectionArr = (array) $section;
            $sectionId = $section->id;
            
            // Get lessons for this section
            $lessonRows = DB::table('lessons')
                ->where('section_id', $sectionId)
                ->orderBy('position')
                ->get();
            
            $sectionArr['lessons'] = $lessonRows->map(function($l) { 
                return (array) $l; 
            })->toArray();
            
            $sections[] = $sectionArr;
        }
        
        $course['sections'] = $sections;
        error_log(">>> Sections processed");
        
        // Build instructor
        if ($course['instructor_name']) {
            $course['instructor'] = [
                'id' => $course['instructor_id'],
                'name' => $course['instructor_name'],
                'email' => $course['instructor_email']
            ];
        }
        
        // Build category
        if ($course['category_name']) {
            $course['category'] = [
                'id' => $course['category_id'],
                'name' => $course['category_name'],
                'slug' => $course['category_slug']
            ];
        }
        
        error_log(">>> Getting rating info...");
        $ratingInfo = $this->getCourseRatingInfo($courseId);
        error_log(">>> Rating info done");
        
        error_log(">>> COURSE SHOW SUCCESS");
        return response()->json([
            'message' => 'Course details retrieved successfully',
            'course' => $course,
            'rating_info' => $ratingInfo,
        ]);
        
    } catch (\Exception $e) {
        error_log('>>> COURSE SHOW ERROR: ' . $e->getMessage());
        error_log('>>> TRACE: ' . $e->getTraceAsString());
        return response()->json([
            'message' => 'Error fetching course details',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Helper method to get course rating info using direct DB queries
 */
private function getCourseRatingInfo($courseId)
{
    $stats = DB::table('reviews')
        ->where('course_id', $courseId)
        ->select(
            DB::raw('COUNT(*) as total_ratings'),
            DB::raw('AVG(rating) as average_rating'),
            DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star'),
            DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star'),
            DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star'),
            DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star'),
            DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star')
        )
        ->first();
    
    if (!$stats || $stats->total_ratings == 0) {
        return [
            'average_rating' => 0,
            'total_ratings' => 0,
            'distribution' => [0, 0, 0, 0, 0]
        ];
    }
    
    return [
        'average_rating' => round($stats->average_rating, 1),
        'total_ratings' => (int) $stats->total_ratings,
        'distribution' => [
            (int) $stats->five_star,
            (int) $stats->four_star,
            (int) $stats->three_star,
            (int) $stats->two_star,
            (int) $stats->one_star
        ]
    ];
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
