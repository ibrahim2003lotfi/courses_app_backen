<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

Route::get('/redis-test', function () {
    try {
        // Test connection first
        Redis::ping();
        
        // Save a value in Redis
        Redis::set('test-key', 'Hello Laravel Redis!');
        
        // Set expiration (optional)
        Redis::expire('test-key', 60); // 60 seconds
        
        // Retrieve the value
        $value = Redis::get('test-key');
        
        // Get some Redis info
        $info = Redis::info();
        
        return response()->json([
            'success' => true,
            'value' => $value,
            'redis_version' => $info['redis_version'] ?? 'unknown',
            'connected_clients' => $info['connected_clients'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'solution' => 'Make sure Redis server is running on port 6379'
        ], 500);
    }
});