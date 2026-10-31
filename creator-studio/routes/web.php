<?php

use App\Http\Controllers\Api\AgentsController;
use App\Http\Controllers\Api\AudienceController;
use App\Http\Controllers\Api\BioLinksController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\YouTubeStudioController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\DealsController;
use App\Http\Controllers\Api\EngagementController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\GrowthController;
use App\Http\Controllers\Api\InstallController;
use App\Http\Controllers\Api\ScriptWizardController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\LoveController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScriptStreamController;
use App\Http\Controllers\Api\SocialAccountsController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\SocialPlanController;
use App\Http\Controllers\Api\SocialInsightsController;
use App\Http\Controllers\Api\ScriptVersionController;
use App\Http\Controllers\Api\ToolkitController;
use App\Http\Controllers\Api\TranscriptionController;
use App\Http\Controllers\Api\VisionController;
use App\Http\Controllers\Api\WorkflowsController;
use App\Http\Controllers\Api\YouTubeController;
use App\Http\Controllers\Api\StripePaymentController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// ==========================================
// API ROUTES (Zero Blade, 100% JSON)
// ==========================================

Route::prefix('api')->group(function () {
    // Auth
    Route::get('auth/status', [ApiController::class, 'authStatus']);
    Route::post('auth/setup', [ApiController::class, 'setup']);
    Route::post('auth/register', [ApiController::class, 'register']);
    Route::post('auth/login', [ApiController::class, 'login']);
    Route::post('auth/logout', [ApiController::class, 'logout']);
    Route::post('auth/forgot-password', [ApiController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [ApiController::class, 'resetPassword']);

    // Guided Server & Database Install (Optional)
    Route::get('install/status', [InstallController::class, 'status']);
    Route::post('install/test-db', [InstallController::class, 'testDatabase']);
    Route::post('install/setup-db', [InstallController::class, 'setupDatabase']);
    Route::post('install/run-migrations', [InstallController::class, 'runMigrations']);

    // Public Stripe Payments & Intro Bookings (Tech Unicorn Landing & Retainers)
    Route::get('stripe/packages', [StripePaymentController::class, 'packages']);
    // Visitors write to her CRM, so cap it per IP (Stripe's webhook is signed instead).
    Route::post('stripe/checkout-session', [StripePaymentController::class, 'createCheckoutSession'])->middleware('throttle:5,1');
    Route::post('stripe/webhook', [StripePaymentController::class, 'webhook']);
    Route::post('booking', [BookingController::class, 'store'])->middleware('throttle:5,1');
    Route::get('landing/youtube-videos', [\App\Http\Controllers\LandingPageController::class, 'youtubeVideos']);
    Route::get('landing/social-feed', [\App\Http\Controllers\LandingPageController::class, 'socialFeed']);
    Route::get('landing/pricing', [\App\Http\Controllers\LandingPageController::class, 'pricing']);
    Route::get('landing/about', [\App\Http\Controllers\LandingPageController::class, 'about']);
    Route::get('landing/talks', [\App\Http\Controllers\LandingPageController::class, 'talks']);
    Route::get('landing/availability', [\App\Http\Controllers\LandingPageController::class, 'availability']);
    Route::post('landing/click', [\App\Http\Controllers\LandingPageController::class, 'click'])->middleware('throttle:60,1');

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
        Route::get('social/composer', [SocialController::class, 'composerMeta']);
        Route::post('social/compose', [SocialController::class, 'compose']);
        Route::post('social/demo', [SocialController::class, 'demo']);
        Route::get('social/due', [SocialPlanController::class, 'due']);
        Route::post('social/posts/{post}/auto-schedule', [SocialPlanController::class, 'autoSchedule']);
        Route::post('social/plan-week', [SocialPlanController::class, 'planWeek']);
        Route::post('social/posts/{post}/media', [SocialPlanController::class, 'uploadMedia']);
        Route::post('social/posts/{post}/media/attach', [SocialPlanController::class, 'attachExisting']);
        Route::delete('social/posts/{post}/media/{media}', [SocialPlanController::class, 'removeMedia'])->whereNumber('media');
        Route::post('social/posts/{post}/media/{media}/alt', [SocialPlanController::class, 'altText'])->whereNumber('media');
        Route::get('social/posts/{post}/srt', [SocialPlanController::class, 'srt']);
        Route::delete('social/demo', [SocialController::class, 'removeDemo']);
        Route::get('social/calendar', [SocialController::class, 'calendar']);
        Route::get('social/stats', [SocialController::class, 'stats']);
        Route::get('social/analytics', [SocialInsightsController::class, 'analytics']);
        Route::get('social/best-times', [SocialInsightsController::class, 'bestTimes']);
        Route::get('social/dashboard', [SocialInsightsController::class, 'dashboard']);
        Route::post('social/followers', [SocialInsightsController::class, 'followers']);
        Route::post('social/posts/{post}/fresh-take', [SocialInsightsController::class, 'freshTake']);
        Route::post('social/import', [SocialInsightsController::class, 'import']);
        Route::get('social/bios', [SocialInsightsController::class, 'bios']);
        Route::post('social/bios/options', [SocialInsightsController::class, 'bioOptions']);
        Route::put('social/bios', [SocialInsightsController::class, 'saveBio']);
        Route::post('stream/social/review', [SocialInsightsController::class, 'review']);

        // Brand Deals, Sponsorships & Media Kit
        Route::get('deals', [DealsController::class, 'index']);
        Route::post('deals', [DealsController::class, 'store']);
        Route::get('deals/rate-card', [DealsController::class, 'rateCard']);
        Route::get('deals/media-kit', [DealsController::class, 'mediaKit']);
        Route::post('deals/pitch', [DealsController::class, 'generatePitch']);
        Route::get('deals/{deal}', [DealsController::class, 'show']);
        Route::patch('deals/{deal}', [DealsController::class, 'update']);
        Route::delete('deals/{deal}', [DealsController::class, 'destroy']);
        Route::post('deals/{deal}/sponsor-script', [DealsController::class, 'generateSponsorScript']);
        Route::get('deals/{deal}/report', [DealsController::class, 'campaignReport']);

        // For you: love letters, special dates, milestones, her photo
        Route::get('love/status', [LoveController::class, 'status']);
        Route::get('love', [LoveController::class, 'index']);
        Route::put('love/dates', [LoveController::class, 'saveDates']);
        Route::post('love/photo', [LoveController::class, 'uploadPhoto']);
        Route::get('love/photo', [LoveController::class, 'photo']);
        Route::delete('love/photo', [LoveController::class, 'deletePhoto']);
        Route::post('love/letters', [LoveController::class, 'store']);
        Route::get('love/letters/{letter}', [LoveController::class, 'show']);
        Route::put('love/letters/{letter}', [LoveController::class, 'update']);
        Route::delete('love/letters/{letter}', [LoveController::class, 'destroy']);
        Route::post('love/letters/{letter}/open', [LoveController::class, 'open']);

        // Memory jar
        Route::get('love/jar', [LoveController::class, 'jar']);
        Route::put('love/jar/{id}', [LoveController::class, 'answerJar'])->whereNumber('id');

        // YouTube studio: upload from the studio, title test & compare
        Route::get('youtube/studio', [YouTubeStudioController::class, 'index']);
        Route::get('youtube/studio/videos', [YouTubeStudioController::class, 'videos']);
        Route::post('youtube/uploads', [YouTubeStudioController::class, 'startUpload']);
        Route::post('youtube/uploads/drain', [YouTubeStudioController::class, 'drain']);
        Route::post('youtube/uploads/{upload}/chunk', [YouTubeStudioController::class, 'chunk']);
        Route::delete('youtube/uploads/{upload}', [YouTubeStudioController::class, 'cancelUpload']);
        Route::post('youtube/tests', [YouTubeStudioController::class, 'startTest']);
        Route::post('youtube/tests/{test}/stop', [YouTubeStudioController::class, 'stopTest']);

        // Her profile photo (header avatar)
        Route::post('me/avatar', [ProfileController::class, 'upload']);
        Route::get('me/avatar', [ProfileController::class, 'show']);
        Route::delete('me/avatar', [ProfileController::class, 'destroy']);

        // Brand kit
        Route::get('brand', [BrandController::class, 'show']);
        Route::put('brand', [BrandController::class, 'update']);
        Route::post('brand/logo', [BrandController::class, 'uploadLogo']);
        Route::get('brand/logo', [BrandController::class, 'logo']);
        Route::delete('brand/logo', [BrandController::class, 'deleteLogo']);

        // Packages & pricing: prices, visibility, most popular, results per plan
        Route::get('pricing', [\App\Http\Controllers\LandingPageController::class, 'pricingEdit']);
        Route::put('pricing', [\App\Http\Controllers\LandingPageController::class, 'pricingSave']);

        // "About you" for the landing page's About section
        Route::get('about', [\App\Http\Controllers\LandingPageController::class, 'aboutEdit']);
        Route::put('about', [\App\Http\Controllers\LandingPageController::class, 'aboutSave']);
        Route::post('about/portrait', [\App\Http\Controllers\LandingPageController::class, 'aboutPortrait']);
        Route::delete('about/portrait', [\App\Http\Controllers\LandingPageController::class, 'aboutPortraitReset']);

        // Talks for the landing page's "On Stage"
        Route::get('talks', [\App\Http\Controllers\Api\TalksController::class, 'index']);
        Route::post('talks', [\App\Http\Controllers\Api\TalksController::class, 'store']);
        Route::patch('talks/{talk}', [\App\Http\Controllers\Api\TalksController::class, 'update']);
        Route::delete('talks/{talk}', [\App\Http\Controllers\Api\TalksController::class, 'destroy']);
        Route::post('talks/{talk}/image', [\App\Http\Controllers\Api\TalksController::class, 'uploadImage']);
        Route::delete('talks/{talk}/image', [\App\Http\Controllers\Api\TalksController::class, 'deleteImage']);

        // Landing page content feed: what's on it, clicks, pin / hide, refresh now
        Route::get('landing/content', [\App\Http\Controllers\LandingPageController::class, 'content']);
        Route::put('landing/content', [\App\Http\Controllers\LandingPageController::class, 'curate']);
        Route::put('landing/reach', [\App\Http\Controllers\LandingPageController::class, 'saveReach']);
        Route::put('landing/capacity', [\App\Http\Controllers\LandingPageController::class, 'saveCapacity']);
        Route::post('landing/content/refresh', [\App\Http\Controllers\LandingPageController::class, 'refresh']);

        // Link in bio (editor + stats; the page itself is public at /links)
        Route::get('bio', [BioLinksController::class, 'index']);
        Route::put('bio', [BioLinksController::class, 'save']);

        // Ask your numbers
        Route::post('social/ask', [GrowthController::class, 'ask']);

        // Post score & hook lab, daily brief, follower goals
        Route::post('social/score', [GrowthController::class, 'score']);
        Route::post('social/hooks', [GrowthController::class, 'hooks']);
        Route::get('brief', [GrowthController::class, 'brief']);
        Route::get('social/goals', [GrowthController::class, 'goals']);
        Route::put('social/goals', [GrowthController::class, 'saveGoals']);

        // Connected accounts (OAuth): sign in to each network and pull its numbers
        Route::get('social/accounts', [SocialAccountsController::class, 'index']);
        Route::post('social/accounts/sync', [SocialAccountsController::class, 'syncAll']);
        Route::put('social/accounts/{platform}/app', [SocialAccountsController::class, 'saveApp']);
        Route::post('social/accounts/{platform}/connect', [SocialAccountsController::class, 'connect']);
        Route::post('social/accounts/{platform}/sync', [SocialAccountsController::class, 'sync']);
        Route::delete('social/accounts/{platform}', [SocialAccountsController::class, 'disconnect']);

        // Social media agents (plan, write, publish, learn) and their notifications
        Route::get('agents', [AgentsController::class, 'index']);
        Route::post('agents', [AgentsController::class, 'store']);
        Route::post('agents/scrape', [AgentsController::class, 'scrape']);
        Route::post('agents/audiences', [AgentsController::class, 'audiences']);
        Route::get('agents/notifications', [AgentsController::class, 'notifications']);
        Route::post('agents/notifications/read', [AgentsController::class, 'readNotifications']);
        Route::delete('agents/notifications', [AgentsController::class, 'clearNotifications']);
        Route::post('agents/posts/{post}/approve', [AgentsController::class, 'approve']);
        Route::post('agents/posts/{post}/reject', [AgentsController::class, 'reject']);
        Route::post('agents/posts/{post}/publish', [AgentsController::class, 'publishNow']);
        Route::get('agents/{agent}', [AgentsController::class, 'show'])->whereNumber('agent');
        Route::patch('agents/{agent}', [AgentsController::class, 'update'])->whereNumber('agent');
        Route::delete('agents/{agent}', [AgentsController::class, 'destroy'])->whereNumber('agent');
        Route::post('agents/{agent}/run', [AgentsController::class, 'run'])->whereNumber('agent');
        Route::post('agents/{agent}/learn', [AgentsController::class, 'learn'])->whereNumber('agent');

        // Workflows: trigger + steps automations, built on the node canvas
        Route::get('workflows', [WorkflowsController::class, 'index']);
        Route::post('workflows', [WorkflowsController::class, 'store']);
        Route::get('workflows/{workflow}', [WorkflowsController::class, 'show']);
        Route::put('workflows/{workflow}', [WorkflowsController::class, 'update']);
        Route::delete('workflows/{workflow}', [WorkflowsController::class, 'destroy']);
        Route::post('workflows/{workflow}/duplicate', [WorkflowsController::class, 'duplicate']);
        Route::post('workflows/{workflow}/run', [WorkflowsController::class, 'run']);
        Route::post('workflows/{workflow}/test-step', [WorkflowsController::class, 'testStep']);

        // Inbox: comments & DMs, replies, automations
        Route::get('engage', [EngagementController::class, 'index']);
        Route::post('engage/poll', [EngagementController::class, 'poll']);
        Route::post('engage/draft-all', [EngagementController::class, 'draftAll']);
        Route::post('engage/items/{item}/draft', [EngagementController::class, 'draft']);
        Route::post('engage/items/{item}/reply', [EngagementController::class, 'reply']);
        Route::post('engage/items/{item}/ignore', [EngagementController::class, 'ignore']);
        Route::post('engage/rules', [EngagementController::class, 'storeRule']);
        Route::patch('engage/rules/{rule}', [EngagementController::class, 'updateRule']);
        Route::delete('engage/rules/{rule}', [EngagementController::class, 'destroyRule']);

        // My channel: her videos vs her own median, and what viewers ask for in comments
        Route::get('audience', [AudienceController::class, 'index']);
        Route::post('audience/comments', [AudienceController::class, 'comments']);
        Route::post('audience/replies', [AudienceController::class, 'replies']);
        Route::post('audience/snapshot', [AudienceController::class, 'snapshot']);

        // Backups of the studio database
        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'store']);
        Route::get('backups/{name}', [BackupController::class, 'download'])->where('name', '[\w.-]+');
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
        Route::post('stream/scripts/{script}/publish-kit', [ScriptStreamController::class, 'publishKit']);
        Route::get('scripts/{script}/chapters', [ScriptStreamController::class, 'chapters']);

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

// The public Tech Unicorn landing page is "/" (signed out); old addresses redirect there.
Route::get('techunicorn', [\App\Http\Controllers\LandingPageController::class, 'show']);
Route::get('landing-page', [\App\Http\Controllers\LandingPageController::class, 'show']);
Route::get('landing/about-portrait', [\App\Http\Controllers\LandingPageController::class, 'aboutPortraitFile']);
Route::get('landing/talk-image/{talk}', [\App\Http\Controllers\LandingPageController::class, 'talkImage'])->whereNumber('talk');
Route::get('landing/thumb/{post}', [\App\Http\Controllers\LandingPageController::class, 'thumb'])->whereNumber('post');

// Link in bio: public page and counted redirects
Route::get('links', [BioLinksController::class, 'page']);
Route::get('links/go/{link}', [BioLinksController::class, 'go'])->whereNumber('link');

// "Login with Google" (uses the Google app from Connections → YouTube; only her own email)
Route::get('auth/google/redirect', [\App\Http\Controllers\GoogleLoginController::class, 'redirect']);
Route::get('auth/google/callback', [\App\Http\Controllers\GoogleLoginController::class, 'callback']);

// OAuth return from each network (a browser redirect, so it uses the session, not the JSON API)
Route::get('oauth/{platform}/callback', [SocialAccountsController::class, 'callback'])->where('platform', '[a-z]+');

// Static public image assets (SVG / PNG / JPG)
Route::get('images/{file}', function ($file) {
    $path = public_path('images/'.$file);
    if (! file_exists($path)) {
        abort(404);
    }
    $mime = str_ends_with($file, '.svg') ? 'image/svg+xml' : (str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg');

    return response()->file($path, ['Content-Type' => $mime]);
})->where('file', '.*');

// Single Page Application (SPA) entry point - pure HTML + Vanilla JavaScript
Route::fallback(function () {
    return response()->file(public_path('index.html'));
});
