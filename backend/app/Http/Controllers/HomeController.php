<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Get home page data
     */
    public function index(Request $request)
    {
        Log::info('🏠🏠 CLEAN HOME CONTROLLER - Starting index method');
        
        try {
            Log::info('🏠 About to return response from clean controller');
            
            return response()->json([
                'message' => 'Clean HomeController works!',
                'categories' => [],
                'sections' => [],
                'best_instructors' => [],
                'is_authenticated' => false,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('🏠🏠 CLEAN HOME CONTROLLER ERROR: ' . $e->getMessage());
            Log::error('🏠🏠 ERROR TRACE: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Error in clean HomeController',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}