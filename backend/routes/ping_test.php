<?php

// Ultra-minimal test - returns immediately
Route::post('/ping-video', function () {
    error_log(">>> PING-VIDEO: Route hit!");
    return response()->json(['success' => true, 'message' => 'pong']);
});
