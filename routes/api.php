<?php

use App\Http\Controllers\McpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// MCP Server endpoints
Route::post('/mcp', [McpController::class, 'handle'])->name('mcp.handle');
Route::post('/mcp/tools', [McpController::class, 'handle'])->name('mcp.tools');
Route::post('/mcp/resources', [McpController::class, 'handle'])->name('mcp.resources');
Route::post('/mcp/prompts', [McpController::class, 'handle'])->name('mcp.prompts');
