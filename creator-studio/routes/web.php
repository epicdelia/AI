<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\ScriptStreamController;
use App\Http\Controllers\Api\ToolkitController;
use App\Http\Controllers\Api\TranscriptionController;
use App\Http\Controllers\Api\YouTubeController;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// ==========================================
// API ROUTES (Zero Blade, 100% JSON)
// ==========================================

Route::prefix('api')->group(function () {
    // Auth
    Route::get('auth/status', [ApiController::class, 'authStatus']);
    Route::post('auth/setup', [ApiController::class, 'setup']);
    Route::post('auth/login', [ApiController::class, 'login']);
    Route::post('auth/logout', [ApiController::class, 'logout']);

    // Authenticated API endpoints
    Route::middleware('auth')->group(function () {
        // Dashboard
        Route::get('dashboard', [ApiController::class, 'dashboard']);

        // Channels & Breakouts
        Route::get('channels', [ApiController::class, 'getChannels']);
        Route::post('channels', [ApiController::class, 'storeChannels']);
        Route::patch('channels/{channel}/toggle', [ApiController::class, 'toggleChannel']);
        Route::delete('channels/{channel}', [ApiController::class, 'deleteChannel']);
        Route::post('breakouts/scan', [ApiController::class, 'scanBreakouts']);
        Route::delete('breakouts/{outlier}', [ApiController::class, 'deleteOutlier']);

        // Ideas
        Route::get('ideas', [ApiController::class, 'getIdeas']);
        Route::post('ideas/generate', [ApiController::class, 'generateIdeas']);
        Route::post('ideas', [ApiController::class, 'storeIdea']);
        Route::patch('ideas/{idea}', [ApiController::class, 'updateIdea']);
        Route::delete('ideas/{idea}', [ApiController::class, 'deleteIdea']);

        // Scripts & Review Workflow
        Route::get('scripts', [ApiController::class, 'getScripts']);
        Route::post('scripts', [ApiController::class, 'createScript']);
        Route::get('scripts/{script}', [ApiController::class, 'getScript']);
        Route::patch('scripts/{script}', [ApiController::class, 'updateScript']);
        Route::delete('scripts/{script}', [ApiController::class, 'deleteScript']);
        Route::post('scripts/{script}/review', [ApiController::class, 'reviewScript']);
        Route::post('scripts/{script}/substack', [ApiController::class, 'substackScript']);
        Route::post('scripts/{script}/edit-brief', [ApiController::class, 'editBriefScript']);
        Route::post('scripts/lint', [ApiController::class, 'lintText']);

        // Goals & Weekly Mentor
        Route::get('goals', [ApiController::class, 'getGoals']);
        Route::post('goals/plan', [ApiController::class, 'planGoals']);
        Route::patch('goals/{week}', [ApiController::class, 'updateGoal']);
        Route::post('goals/checkin', [ApiController::class, 'checkinGoals']);

        // Flow Dictation
        Route::get('dictate/token', [ApiController::class, 'dictationToken']);
        Route::post('dictate/polish', [ApiController::class, 'polishDictation']);
        Route::get('notes', [ApiController::class, 'getNotes']);
        Route::post('notes', [ApiController::class, 'saveNote']);
        Route::post('notes/{note}/to-idea', [ApiController::class, 'noteToIdea']);
        Route::delete('notes/{note}', [ApiController::class, 'deleteNote']);

        // Settings & Diagnostics
        Route::get('settings', [ApiController::class, 'getSettings']);
        Route::put('settings', [ApiController::class, 'updateSettings']);
        Route::post('settings/test', [ApiController::class, 'testConnections']);
        Route::get('usage', [ApiController::class, 'usage']);

        // Toolkit (writing templates) & her own templates
        Route::get('tools', [ToolkitController::class, 'index']);
        Route::post('tools/run', [ToolkitController::class, 'run']);
        Route::post('tools/favorite', [ToolkitController::class, 'toggleFavorite']);
        Route::post('tools/custom', [ToolkitController::class, 'storeCustom']);
        Route::put('tools/custom/{tool}', [ToolkitController::class, 'updateCustom']);
        Route::delete('tools/custom/{tool}', [ToolkitController::class, 'destroyCustom']);

        // Library of saved outputs
        Route::get('documents', [LibraryController::class, 'index']);
        Route::post('documents', [LibraryController::class, 'store']);
        Route::patch('documents/{document}', [LibraryController::class, 'update']);
        Route::delete('documents/{document}', [LibraryController::class, 'destroy']);

        // AI YouTube: transcripts and analysis
        Route::get('youtube/actions', [YouTubeController::class, 'actions']);
        Route::post('youtube/transcript', [YouTubeController::class, 'transcript']);
        Route::post('youtube/analyze', [YouTubeController::class, 'analyze']);

        // Producer chat
        Route::get('chats', [ChatController::class, 'index']);
        Route::post('chats', [ChatController::class, 'store']);
        Route::get('chats/{conversation}', [ChatController::class, 'show']);
        Route::patch('chats/{conversation}', [ChatController::class, 'update']);
        Route::delete('chats/{conversation}', [ChatController::class, 'destroy']);
        Route::post('chats/{conversation}/messages', [ChatController::class, 'send']);

        // Streaming (Server-Sent Events) versions of the slow AI actions
        Route::post('stream/tools/run', [ToolkitController::class, 'runStream']);
        Route::post('stream/chats/{conversation}/messages', [ChatController::class, 'sendStream']);
        Route::post('stream/youtube/analyze', [YouTubeController::class, 'analyzeStream']);
        Route::post('stream/scripts/{script}/draft', [ScriptStreamController::class, 'draft']);
        Route::post('stream/scripts/{script}/review', [ScriptStreamController::class, 'review']);
        Route::post('stream/scripts/{script}/substack', [ScriptStreamController::class, 'substack']);
        Route::post('stream/scripts/{script}/edit-brief', [ScriptStreamController::class, 'editBrief']);

        // Transcribe uploaded recordings
        Route::get('transcribe', [TranscriptionController::class, 'info']);
        Route::post('transcribe', [TranscriptionController::class, 'store']);

        // News & blog feeds (idea signals)
        Route::get('feeds', [FeedController::class, 'index']);
        Route::post('feeds', [FeedController::class, 'store']);
        Route::post('feeds/refresh', [FeedController::class, 'refresh']);
        Route::patch('feeds/{feed}/toggle', [FeedController::class, 'toggle']);
        Route::delete('feeds/{feed}', [FeedController::class, 'destroy']);
        Route::delete('feed-items/{item}', [FeedController::class, 'destroyItem']);
    });
});

// Single Page Application (SPA) entry point - pure HTML + Vanilla JavaScript
Route::fallback(function () {
    return response()->file(public_path('index.html'));
});
