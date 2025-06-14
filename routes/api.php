<?php

use App\Http\Controllers\McpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// MCP Server endpoints
Route::post('/mcp', [McpController::class, 'handle'])->name('mcp.handle');

// Health check endpoint
Route::get('/mcp/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'MCP Server',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
    ]);
})->name('mcp.health');
