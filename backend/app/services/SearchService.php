<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    /**
     * Perform global search across multiple entities
     */
    public function search(string $query, array $filters = [], int $perPage = 10)
    {
        $query = trim($query);
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($query, $filters);
        
        // Cache for 10 minutes
        return Cache::remember($cacheKey, 600, function () use ($query, $filters, $perPage) {
            $searchType = $filters['type'] ?? 'all';
            $results = [];
            
            if ($searchType === 'course' || $searchType === 'all') {
                $results['courses'] = $this->searchCourses($query, $filters, $perPage);
            }
            
            if ($searchType === 'lesson' || $searchType === 'all') {
                $results['lessons'] = $this->searchLessons($query, $filters, $perPage);
            }
            
            if ($searchType === 'instructor' || $searchType === 'all') {
                $results['instructors'] = $this->searchInstructors($query, $perPage);
            }
            
            return $results;
        });
    }

    /**
     * Search courses with advanced PostgreSQL features
     */
    protected function searchCourses(string $query, array $filters, int $perPage)
    {
        $coursesQuery = Course::with(['instructor', 'category']);

        // Apply search if query provided
        if (!empty($query)) {
            $coursesQuery->where(function ($q) use ($query) {
                // Use parameter binding for ILIKE
                $searchPattern = '%' . $query . '%';
                
                $q->where('title', 'ILIKE', $searchPattern)
                  ->orWhere('description', 'ILIKE', $searchPattern);
                
                // Add trigram similarity search for fuzzy matching
                $q->orWhereRaw("similarity(title, ?) > 0.1", [$query]);
            });

            // Order by relevance - fixed version
            $coursesQuery->orderByRaw("
                CASE 
                    WHEN title ILIKE ? THEN 1
                    WHEN title ILIKE ? THEN 2
                    WHEN description ILIKE ? THEN 3
                    ELSE 4
                END,
                similarity(title, ?) DESC,
                total_students DESC
            ", [
                $query,           // Exact match
                '%' . $query . '%',  // Contains match for title
                '%' . $query . '%',  // Contains match for description
                $query            // For similarity
            ]);
        }

        // Apply filters
        $this->applyFilters($coursesQuery, $filters);

        return $coursesQuery->paginate($perPage);
    }

    /**
     * Search lessons (only preview lessons)
     */
    protected function searchLessons(string $query, array $filters, int $perPage)
    {
        $lessonsQuery = Lesson::with(['section.course.instructor', 'section.course.category'])
            ->where('is_preview', true);

        if (!empty($query)) {
            $searchPattern = '%' . $query . '%';
            
            $lessonsQuery->where(function ($q) use ($searchPattern) {
                $q->where('title', 'ILIKE', $searchPattern);
                
                // Include description if not null
                $q->orWhere(function ($subQ) use ($searchPattern) {
                    $subQ->whereNotNull('description')
                         ->where('description', 'ILIKE', $searchPattern);
                });
            });
        }

        // Filter by course level if specified
        if (!empty($filters['level'])) {
            $lessonsQuery->whereHas('section.course', function ($q) use ($filters) {
                $q->where('level', $filters['level']);
            });
        }

        // Filter by category
        if (!empty($filters['category_id'])) {
            $lessonsQuery->whereHas('section.course', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        return $lessonsQuery->paginate($perPage);
    }

    /**
     * Search instructors
     */
    protected function searchInstructors(string $query, int $perPage)
    {
        $searchPattern = '%' . $query . '%';
        
        return User::role('instructor')
            ->with(['profile'])
            ->withCount('courses')
            ->withAvg('courses', 'rating')
            ->where('name', 'ILIKE', $searchPattern)
            ->orderBy('courses_count', 'desc')
            ->orderByRaw('courses_avg_rating DESC NULLS LAST')
            ->paginate($perPage);
    }

    /**
     * Apply filters to course query
     */
    protected function applyFilters($query, array $filters)
    {
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', '>=', $filters['rating']);
        }

        return $query;
    }

    /**
     * Generate cache key
     */
    protected function generateCacheKey(string $query, array $filters): string
    {
        $filterString = http_build_query(array_filter($filters));
        return "search:" . md5($query . ':' . $filterString);
    }

    /**
     * Clear search cache
     */
    public function clearCache(): void
    {
        // If using cache tags
        Cache::tags(['search'])->flush();
    }
}