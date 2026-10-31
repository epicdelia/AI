<?php

use App\Http\Controllers\Api\AudienceController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\ScriptWizardController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScriptStreamController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\ScriptVersionController;
use App\Http\Controllers\Api\ToolkitController;
use App\Http\Controllers\Api\TranscriptionController;
use App\Http\Controllers\Api\VisionController;
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

        // Script version history
        Route::scopeBindings()->group(function () {
            Route::get('scripts/{script}/versions', [ScriptVersionController::class, 'index']);
            Route::post('scripts/{script}/versions', [ScriptVersionController::class, 'store']);
            Route::get('scripts/{script}/versions/{version}', [ScriptVersionController::class, 'show']);
            Route::post('scripts/{script}/versions/{version}/restore', [ScriptVersionController::class, 'restore']);
            Route::delete('scripts/{script}/versions/{version}', [ScriptVersionController::class, 'destroy']);
        });

        // Social: LinkedIn, Instagram, X and YouTube posts, calendar and repurposing
        Route::get('social/meta', [SocialController::class, 'meta']);
        Route::get('social/posts', [SocialController::class, 'index']);
        Route::post('social/posts', [SocialController::class, 'store']);
        Route::get('social/posts/{post}', [SocialController::class, 'show']);
        Route::patch('social/posts/{post}', [SocialController::class, 'update']);
        Route::delete('social/posts/{post}', [SocialController::class, 'destroy']);
        Route::post('social/posts/{post}/duplicate', [SocialController::class, 'duplicate']);
        Route::post('social/check', [SocialController::class, 'check']);
        Route::post('social/repurpose', [SocialController::class, 'repurpose']);
        Route::get('social/calendar', [SocialController::class, 'calendar']);
        Route::get('social/stats', [SocialController::class, 'stats']);

        // My channel: her videos vs her own median, and what viewers ask for in comments
        Route::get('audience', [AudienceController::class, 'index']);
        Route::post('audience/comments', [AudienceController::class, 'comments']);
        Route::delete('audience/insights/{insight}', [AudienceController::class, 'destroy']);

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
        Route::post('stream/scripts/{script}/fact-check', [ScriptStreamController::class, 'factCheck']);

        // Script wizard (title → chapter plan → streamed draft)
        Route::post('wizard/titles', [ScriptWizardController::class, 'titles']);
        Route::post('wizard/outlines', [ScriptWizardController::class, 'outlines']);
        Route::post('wizard/create', [ScriptWizardController::class, 'create']);

        // Ctrl+K search & document upload
        Route::get('search', GlobalSearchController::class);
        Route::post('documents/upload', [LibraryController::class, 'upload']);
        Route::get('documents/{document}', [LibraryController::class, 'show']);

        // Media: read-aloud, thumbnail mockups, reference photos, Shorts (Vizard)
        Route::get('media/capabilities', [MediaController::class, 'capabilities']);
        Route::get('media', [MediaController::class, 'index']);
        Route::get('media/{media}/file', [MediaController::class, 'file']);
        Route::delete('media/{media}', [MediaController::class, 'destroy']);
        Route::post('media/photos', [MediaController::class, 'uploadPhoto']);
        Route::post('tts', [MediaController::class, 'speak']);
        Route::post('thumbnails', [MediaController::class, 'thumbnail']);
        Route::post('thumbnails/review', [VisionController::class, 'thumbnailReview']);
        Route::get('usage/limits', fn () => response()->json(\App\Domains\Usage\UsageLimit::summary()));
        Route::get('clips', [MediaController::class, 'clips']);
        Route::post('clips', [MediaController::class, 'submitClips']);
        Route::post('clips/{project}/refresh', [MediaController::class, 'refreshClips']);
        Route::delete('clips/{project}', [MediaController::class, 'destroyClips']);
        Route::get('broll', [MediaController::class, 'broll']);
        Route::post('broll', [MediaController::class, 'submitBroll']);
        Route::post('broll/{job}/refresh', [MediaController::class, 'refreshBroll']);
        Route::delete('broll/{job}', [MediaController::class, 'destroyBroll']);
        Route::post('audio/clean', [MediaController::class, 'cleanAudio']);

        // Exports (Word / Markdown / text)
        Route::get('export/scripts/{script}', [ExportController::class, 'script']);
        Route::get('export/chats/{conversation}', [ExportController::class, 'chat']);
        Route::get('export/documents/{document}', [ExportController::class, 'document']);

        // Copilot (agentic assistant panel)
        Route::get('copilot/meta', [CopilotController::class, 'meta']);
        Route::get('copilot/threads', [CopilotController::class, 'index']);
        Route::post('copilot/threads', [CopilotController::class, 'store']);
        Route::get('copilot/threads/{thread}', [CopilotController::class, 'show']);
        Route::patch('copilot/threads/{thread}', [CopilotController::class, 'update']);
        Route::delete('copilot/threads/{thread}', [CopilotController::class, 'destroy']);
        Route::post('stream/copilot/threads/{thread}/messages', [CopilotController::class, 'send']);
        Route::post('copilot/actions/{action}/apply', [CopilotController::class, 'apply']);
        Route::post('copilot/actions/{action}/dismiss', [CopilotController::class, 'dismiss']);
        Route::post('copilot/actions/{action}/undo', [CopilotController::class, 'undo']);
        Route::post('copilot/feedback', [CopilotController::class, 'feedback']);

        // First-run tour
        Route::post('tour/done', function () {
            \App\Models\Setting::put('tour_done', now()->toIso8601String());

            return response()->json(['ok' => true]);
        });

        // Memory: search her own past work
        Route::get('memory', [MemoryController::class, 'status']);
        Route::get('memory/search', [MemoryController::class, 'search']);
        Route::post('memory/reindex', [MemoryController::class, 'reindex']);

        // Scheduled jobs
        Route::get('schedule', [ScheduleController::class, 'index']);
        Route::post('schedule/{task}/run', [ScheduleController::class, 'run']);

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
