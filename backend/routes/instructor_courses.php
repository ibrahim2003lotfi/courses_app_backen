<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Category;
use App\Models\University;
use App\Models\Faculty;
use App\Models\Section;
use App\Models\Lesson;

// ⚪ قائمة دورات المدرّس (من الـ Cache فقط، بدون DB)
Route::get('/instructor/my-courses', function () {
    $courses = Cache::get('instructor_courses', []); // associative array: id => course

    return response()->json([
        'success' => true,
        'instructor' => 'Test',
        'total_courses' => count($courses),
        'courses' => array_values($courses), // frontend expects list
    ]);
});

// 🟢 إنشاء دورة للمدرّس (تخزين في Cache فقط، بدون DB)
Route::post('/instructor/courses', function () {
    $request = request();

    // حمّل ما هو موجود أصلاً من الكاش
    $courses = Cache::get('instructor_courses', []);

    // أنشئ ID بسيط وفريد نسبياً
    $courseId = uniqid('course_', true);

    $title = (string) $request->input('title', 'New Course');
    $description = $request->input('description', '');
    $price = (float) ($request->input('price', 0));
    $level = (string) $request->input('level', 'beginner');
    $categoryName = $request->input('category_name');

    // slug بسيط بدون استخدام Str
    $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', str_replace(' ', '-', $title))));
    if ($baseSlug === '') {
        $baseSlug = 'course';
    }
    $slug = $baseSlug . '-' . time();

    $nowIso = now()->toIso8601String();

    $course = [
        'id' => $courseId,
        'title' => $title,
        'slug' => $slug,
        'description' => $description,
        'price' => $price,
        'level' => $level,
        'course_image_url' => null, // ما في رفع صورة حقيقي هنا
        'total_students' => 0,
        'rating' => 0,
        'created_at' => $nowIso,
        'instructor' => [
            'id' => 'cache_instructor',
            'name' => 'Instructor',
        ],
        'category' => $categoryName ? [
            'id' => 'cache_category',
            'name' => $categoryName,
        ] : null,
    ];

    // خزّن في الكاش
    $courses[$courseId] = $course;
    Cache::put('instructor_courses', $courses, 60 * 60 * 24); // 24 ساعة

    return response()->json([
        'success' => true,
        'message' => 'تم حفظ الدورة بنجاح!',
        'course' => $course,
    ]);
});

// 🔵 نسخة جديدة تعتمد على قاعدة البيانات مباشرة (بدون auth middleware)
Route::get('/instructor/my-courses-db', function (Request $request) {
    try {
        // Get instructor_id from query parameter or fall back to first user
        $instructorId = $request->query('instructor_id');
        
        $query = Course::with([
            'instructor:id,name',
            'category:id,name,slug',
        ]);
        
        // If instructor_id provided, filter by it
        if ($instructorId) {
            $query->where('instructor_id', $instructorId);
        }
        
        $courses = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'instructor' => 'DB Instructor',
            'total_courses' => $courses->count(),
            'courses' => $courses,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'database_error',
            'message' => 'حدث خطأ أثناء تحميل الدورات: ' . $e->getMessage(),
            'courses' => [],
        ], 500);
    }
});

// 🟢 إنشاء دورة وتخزينها في قاعدة البيانات مباشرة (بدون auth:sanctum)
Route::post('/instructor/courses-db', function (Request $request) {
    try {
        // Get instructor_id from request or fall back to first user
        $instructorId = $request->input('instructor_id');
        
        if (!$instructorId) {
            // نختار أول مستخدم كمدرّس افتراضي حتى لا نعتمد على Sanctum
            $instructorId = \App\Models\User::query()->value('id');
        }
        
        if (!$instructorId) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد مستخدمون في قاعدة البيانات لاستخدامهم كمدرّس',
            ], 500);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|string|max:50',  // Relaxed: accept string price
            'level' => 'nullable|string|max:50',
            'category_id' => 'nullable|string|max:255',
            'category_name' => 'nullable|string|max:255',
            'thumbnail_image' => 'nullable|file|max:10240',
            'lessons_json' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'university_id' => 'nullable|string|max:255',
            'university_name' => 'nullable|string|max:255',
            'faculty_id' => 'nullable|string|max:255',
            'faculty_name' => 'nullable|string|max:255',
            'is_university_course' => 'nullable|string|max:10',  // Accept string "true"/"false"
        ]);

        Log::info('CREATE COURSE - Received data: ' . json_encode($request->all()));

        // التعامل مع التصنيف
        $categoryId = $validated['category_id'] ?? null;
        if (!$categoryId && !empty($validated['category_name'])) {
            $category = Category::firstOrCreate(
                ['name' => $validated['category_name']],
                [
                    'slug' => Str::slug($validated['category_name']),
                    'description' => 'Category: ' . $validated['category_name'],
                ]
            );
            $categoryId = $category->id;
        }

        // إنشاء slug
        $slug = Str::slug($validated['title']);
        $count = Course::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        // رفع صورة الكورس إن وجدت
        $courseImageUrl = null;
        if ($request->hasFile('thumbnail_image')) {
            try {
                $file = $request->file('thumbnail_image');
                if ($file->isValid()) {
                    $path = $file->store('courses/thumbnails', 'public');
                    $courseImageUrl = url('storage/' . $path);
                }
            } catch (\Exception $e) {
                Log::error('Failed to store thumbnail (DB endpoint): ' . $e->getMessage());
            }
        }

        // هل هو كورس جامعي؟
        $isUniversityCourse = ($validated['type'] ?? 'regular') === 'university'
            || !empty($validated['university_name']);

        $universityId = null;
        $facultyId = null;

        if ($isUniversityCourse && !empty($validated['university_name'])) {
            $university = University::firstOrCreate(
                ['name' => $validated['university_name']],
                ['slug' => Str::slug($validated['university_name'])]
            );
            $universityId = $university->id;

            if (!empty($validated['faculty_name'])) {
                $faculty = Faculty::firstOrCreate(
                    [
                        'university_id' => $universityId,
                        'name' => $validated['faculty_name'],
                    ],
                    ['slug' => Str::slug($validated['faculty_name'])]
                );
                $facultyId = $faculty->id;
            }
        }

        // الدروس (metadata فقط، بدون ملفات الفيديو)
        $lessons = [];
        if (!empty($validated['lessons_json'])) {
            $lessons = json_decode($validated['lessons_json'], true) ?? [];
        }

        $lessonsCount = count($lessons);

        // ملاحظة: بعض الأعمدة مثل is_university_course, university_id, faculty_id,
        // duration_hours, lessons_count قد لا تكون موجودة في جدول courses حالياً.
        // لذلك نكتب في الأعمدة الأساسية فقط حتى لا يحدث خطأ SQL.

        // Use provided IDs if available, otherwise use the ones from name lookup
        $universityId = $validated['university_id'] ?? $universityId;
        $facultyId = $validated['faculty_id'] ?? $facultyId;

        $course = Course::create([
            'instructor_id' => $instructorId,
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
            'duration_hours' => $lessonsCount > 0 ? ceil($lessonsCount * 0.5) : 0,
            'lessons_count' => $lessonsCount,
        ]);

        // إنشاء Section + Lessons إن وجدت بيانات دروس
        $createdSection = null;
        $createdLessons = [];
        if (!empty($lessons)) {
            $createdSection = Section::create([
                'course_id' => $course->id,
                'title' => 'محتوى الدورة',
                'position' => 1,
            ]);

            foreach ($lessons as $index => $lessonData) {
                $createdLessons[] = Lesson::create([
                    'section_id' => $createdSection->id,
                    'title' => $lessonData['title'] ?? ('Lesson ' . ($index + 1)),
                    'description' => $lessonData['description'] ?? '',
                    'position' => $index + 1,
                    'is_preview' => false,
                ]);
            }
        }

        // نبني استجابة متوافقة مع Flutter (تتضمن sections/lessons IDs لرفع الفيديو)
        $responseCourse = $course->fresh(['category']);

        $sectionsPayload = [];
        if ($createdSection) {
            $sectionsPayload[] = [
                'id' => $createdSection->id,
                'title' => $createdSection->title,
                'lessons' => array_map(function ($lesson) {
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                    ];
                }, $createdLessons),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully (DB)',
            'course' => [
                'id' => $responseCourse->id,
                'title' => $responseCourse->title,
                'slug' => $responseCourse->slug,
                'description' => $responseCourse->description,
                'price' => $responseCourse->price,
                'level' => $responseCourse->level,
                'course_image_url' => $responseCourse->course_image_url,
                'category' => $responseCourse->category ? [
                    'id' => $responseCourse->category->id,
                    'name' => $responseCourse->category->name,
                ] : null,
                'sections' => $sectionsPayload,
            ],
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('COURSE CREATE VALIDATION FAILED: ' . json_encode($e->errors()));
        Log::error('Request data: ' . json_encode($request->all()));
        return response()->json([
            'success' => false,
            'message' => 'Validation failed: ' . json_encode($e->errors()),
            'errors' => $e->errors(),
            'received_data' => $request->all(),
        ], 422);
    } catch (\Exception $e) {
        Log::error('DB course creation failed: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error creating course (DB): ' . $e->getMessage(),
        ], 500);
    }
});

// 🗑️ حذف دورة من قاعدة البيانات (بدون auth:sanctum) - آمن حتى لو لم توجد الدورة
Route::delete('/instructor/courses-db/{id}', function ($id) {
    try {
        $course = Course::where('id', $id)->first();

        if ($course) {
            $course->delete();
        }

        // نجعل الحذف idempotent: حتى لو لم توجد الدورة نرجع success
        return response()->json([
            'success' => true,
            'message' => 'Course deleted (if it existed)',
        ]);
    } catch (\Exception $e) {
        Log::error('DB course delete failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error deleting course (DB): ' . $e->getMessage(),
        ], 500);
    }
});

// ✏️ تعديل دورة في قاعدة البيانات (بدون auth:sanctum)
Route::put('/instructor/courses-db/{id}', function (Request $request, $id) {
    try {
        $course = Course::where('id', $id)->first();

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'category_name' => 'nullable|string|max:255',
        ]);

        // التعامل مع التصنيف إذا أُرسل category_name بدون category_id
        $categoryId = $validated['category_id'] ?? $course->category_id;
        if (empty($validated['category_id']) && !empty($validated['category_name'])) {
            $category = Category::firstOrCreate(
                ['name' => $validated['category_name']],
                [
                    'slug' => Str::slug($validated['category_name']),
                    'description' => 'Category: ' . $validated['category_name'],
                ]
            );
            $categoryId = $category->id;
        }

        $updateData = [];
        if (array_key_exists('title', $validated)) {
            $updateData['title'] = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'] ?? '';
        }
        if (array_key_exists('price', $validated)) {
            $updateData['price'] = $validated['price'];
        }
        if (array_key_exists('level', $validated)) {
            $updateData['level'] = $validated['level'];
        }
        $updateData['category_id'] = $categoryId;

        $course->update($updateData);

        $fresh = $course->fresh(['category']);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully (DB)',
            'course' => [
                'id' => $fresh->id,
                'title' => $fresh->title,
                'slug' => $fresh->slug,
                'description' => $fresh->description,
                'price' => $fresh->price,
                'level' => $fresh->level,
                'course_image_url' => $fresh->course_image_url,
                'category' => $fresh->category ? [
                    'id' => $fresh->category->id,
                    'name' => $fresh->category->name,
                ] : null,
            ],
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        Log::error('DB course update failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error updating course (DB): ' . $e->getMessage(),
        ], 500);
    }
});

// 🎥 مسار مبسّط لرفع الفيديو تم نقله إلى VideoUploadController في api.php
// Route::post('/instructor/courses/{courseId}/lessons/{lessonId}/video', function ($courseId, $lessonId) {
//     $request = request();
// 
//     if (!$request->hasFile('video')) {
//         return response()->json([
//             'success' => false,
//             'error' => 'no_file',
//             'message' => 'لم يتم إرسال ملف الفيديو',
//         ], 422);
//     }
// 
//     try {
//         $file = $request->file('video');
//         if (!$file->isValid()) {
//             return response()->json([
//                 'success' => false,
//                 'error' => 'invalid_file',
//                 'message' => 'ملف الفيديو غير صالح',
//             ], 422);
//         }
// 
//         // Store video and update lesson
//         $path = $file->store('courses/videos', 'public');
//         $videoUrl = url('storage/' . $path);
// 
//         // Update lesson with video path and status
//         $lesson = Lesson::where('id', $lessonId)->first();
//         error_log(">>> VIDEO UPLOAD: Looking for lesson $lessonId");
//         error_log(">>> VIDEO UPLOAD: Lesson found: " . ($lesson ? 'YES' : 'NO'));
//         
//         if ($lesson) {
//             error_log(">>> VIDEO UPLOAD: Updating lesson with path: public/$path");
//             $updateResult = $lesson->update([
//                 'video_path' => 'public/' . $path,
//                 'status' => 'compressed',
//                 'video_size' => $file->getSize(),
//             ]);
//             error_log(">>> VIDEO UPLOAD: Update result: " . ($updateResult ? 'SUCCESS' : 'FAILED'));
//         }
// 
//         return response()->json([
//             'success' => true,
//             'message' => 'تم رفع الفيديو بنجاح',
//             'video_url' => $videoUrl,
//             'lesson_id' => $lessonId,
//         ]);
//     } catch (\Exception $e) {
//         Log::error('Simple video upload failed: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'error' => 'upload_failed',
//             'message' => 'فشل رفع الفيديو: ' . $e->getMessage(),
//         ], 500);
//     }
// });
