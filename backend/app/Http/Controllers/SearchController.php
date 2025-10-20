<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Main search endpoint
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required_without:category_id|nullable|string|max:255',
            'type' => 'nullable|in:course,lesson,instructor,all',
            'level' => 'nullable|in:beginner,intermediate,advanced',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'rating' => 'nullable|numeric|min:1|max:5',
            'per_page' => 'nullable|integer|min:5|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = $validated['q'] ?? '';
        $perPage = $validated['per_page'] ?? 10;
        
        // Extract filters
        $filters = array_filter($validated, function($key) {
            return !in_array($key, ['q', 'per_page', 'page']);
        }, ARRAY_FILTER_USE_KEY);

        $results = $this->searchService->search($query, $filters, $perPage);

        return response()->json([
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'metadata' => [
                'total_courses' => isset($results['courses']) ? $results['courses']->total() : 0,
                'total_lessons' => isset($results['lessons']) ? $results['lessons']->total() : 0,
                'total_instructors' => isset($results['instructors']) ? $results['instructors']->total() : 0,
            ]
        ]);
    }

    /**
     * Search suggestions for autocomplete
     */
    public function suggestions(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:50',
            'limit' => 'nullable|integer|min:1|max:10'
        ]);

        $query = $validated['q'];
        $limit = $validated['limit'] ?? 5;

        $suggestions = [
            'courses' => \App\Models\Course::select('id', 'title', 'slug')
                ->where('title', 'ILIKE', "{$query}%")
                ->orderBy('total_students', 'desc')
                ->limit($limit)
                ->get(),
            
            'categories' => \App\Models\Category::select('id', 'name', 'slug')
                ->where('name', 'ILIKE', "{$query}%")
                ->limit(3)
                ->get(),
            
            'instructors' => \App\Models\User::role('instructor')
                ->select('id', 'name')
                ->where('name', 'ILIKE', "{$query}%")
                ->limit(3)
                ->get()
        ];

        return response()->json($suggestions);
    }
}