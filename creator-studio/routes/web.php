<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AgentsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\PortalController;
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
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\SocialProofController;
use App\Http\Controllers\Api\DealRoomController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// ==========================================
// API ROUTES (Zero Blade, 100% JSON)
// ==========================================

Route::prefix('api')->group(function () {
    // Auth (every public write is rate limited per IP). Roles live in App\Enums\Role.
    Route::get('auth/status', [ApiController::class, 'authStatus']);
    Route::post('auth/setup', [AuthController::class, 'setup'])->middleware('throttle:5,1');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware(['bots', 'throttle:5,10']); // clients only
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('auth/two-factor', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:10,1');
    Route::post('auth/demo-client', [AuthController::class, 'demoClient'])->middleware('throttle:10,1'); // 404 unless DEMO_CLIENT_LOGIN=true
    Route::post('auth/logout', [AuthController::class, 'logout']);
    // The native app: email + password (+ code) → a bearer token.
    Route::post('auth/mobile-token', [\App\Http\Controllers\Api\MobileTokenController::class, 'issue'])->middleware('throttle:10,1');
    Route::delete('auth/mobile-token', [\App\Http\Controllers\Api\MobileTokenController::class, 'revokeCurrent']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,10');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

    // Guided Server & Database Install (Optional)
    Route::get('install/status', [InstallController::class, 'status']);
    Route::post('install/test-db', [InstallController::class, 'testDatabase'])->middleware('throttle:10,1');
    Route::post('install/setup-db', [InstallController::class, 'setupDatabase'])->middleware('throttle:5,1');
    Route::post('install/run-migrations', [InstallController::class, 'runMigrations'])->middleware('throttle:5,1');
    Route::post('install/vps', [InstallController::class, 'vpsInstall'])->middleware('throttle:5,1');
    Route::get('install/nginx', [InstallController::class, 'nginxSnippet']);

    // Public Stripe Payments & Intro Bookings (Tech Unicorn Landing & Retainers)
    Route::get('stripe/packages', [StripePaymentController::class, 'packages']);
    // Visitors write to her CRM, so cap it per IP (Stripe's webhook is signed instead).
    Route::post('stripe/checkout-session', [StripePaymentController::class, 'createCheckoutSession'])->middleware(['bots', 'throttle:5,1']);
    Route::post('stripe/webhook', [StripePaymentController::class, 'webhook']);
    Route::post('booking', [BookingController::class, 'store'])->middleware(['bots', 'throttle:5,1']);
    Route::get('booking/slots', [BookingController::class, 'slots']);
    // Website funnel (visits, CTA clicks, form starts: counts only) and a brand's own media kit link.
    Route::post('landing/event', [PipelineController::class, 'event'])->middleware('throttle:120,1');
    // Testimonials (approved ones on the page, and a client's private review form) and the email opt-in.
    Route::get('landing/testimonials', [SocialProofController::class, 'publicTestimonials']);
    Route::get('review/{token}', [SocialProofController::class, 'review'])->where('token', '[A-Za-z0-9]{20,64}');
    Route::post('review/{token}', [SocialProofController::class, 'submitReview'])->where('token', '[A-Za-z0-9]{20,64}')->middleware(['bots', 'throttle:10,1']);
    Route::get('landing/lead-magnet', [SocialProofController::class, 'magnet']);
    Route::post('subscribe', [SocialProofController::class, 'subscribe'])->middleware('throttle:10,1'); // its own honeypot
    // A client's deal page: their proposal, Accept, Pay.
    Route::get('room/{token}', [DealRoomController::class, 'show'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:60,1');
    Route::post('room/{token}/select-tier', [DealRoomController::class, 'selectTier'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:20,1');
    Route::post('room/{token}/accept', [DealRoomController::class, 'accept'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:10,1');
    Route::post('room/{token}/pay', [DealRoomController::class, 'pay'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:10,1');
    Route::get('room/{token}/dpa', [DealRoomController::class, 'dpa'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:60,1');
    Route::post('room/{token}/dpa/sign', [DealRoomController::class, 'signDpa'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:10,1');
    Route::get('kit/{token}', [PipelineController::class, 'kit'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:30,1');
    Route::get('landing/youtube-videos', [\App\Http\Controllers\LandingPageController::class, 'youtubeVideos']);
    Route::get('landing/social-feed', [\App\Http\Controllers\LandingPageController::class, 'socialFeed']);
    Route::get('landing/hero', [\App\Http\Controllers\LandingPageController::class, 'hero']);
    Route::get('landing/marquee', [\App\Http\Controllers\LandingPageController::class, 'marquee']);
    Route::get('landing/partners', [\App\Http\Controllers\LandingPageController::class, 'partners']);
    Route::get('landing/pricing', [\App\Http\Controllers\LandingPageController::class, 'pricing']);
    Route::get('landing/about', [\App\Http\Controllers\LandingPageController::class, 'about']);
    Route::get('landing/talks', [\App\Http\Controllers\LandingPageController::class, 'talks']);
    Route::get('landing/availability', [\App\Http\Controllers\LandingPageController::class, 'availability']);
    Route::post('landing/click', [\App\Http\Controllers\LandingPageController::class, 'click'])->middleware('throttle:60,1');

    // Multilingual & 24 EU Localization Routes
    Route::post('locale/{locale}', [\App\Http\Controllers\Api\LocaleController::class, 'setLocale'])->where('locale', '[a-zA-Z]{2}');
    Route::get('locales', [\App\Http\Controllers\Api\LocaleController::class, 'locales']);
    Route::post('website/translate', [\App\Http\Controllers\Api\LocaleController::class, 'translateContent']);

    // The portal's white-label look (name, logo, accent) for clients and members.
    Route::get('branding', [\App\Http\Controllers\Api\PortalBrandController::class, 'show'])->middleware('throttle:60,1');
    Route::get('branding/logo', [\App\Http\Controllers\Api\PortalBrandController::class, 'logo'])->middleware('throttle:60,1');

    // Newsletter: the one-click unsubscribe link in every email (signed).
    Route::match(['get', 'post'], 'newsletter/unsubscribe/{subscriber}', [\App\Http\Controllers\Api\NewsletterController::class, 'unsubscribe'])->whereNumber('subscriber')->name('newsletter.unsubscribe')->middleware('throttle:30,1');

    // Shop: digital products (public list and checkout; the emailed download link).
    Route::get('shop', [\App\Http\Controllers\Api\ShopController::class, 'index']);
    Route::post('shop/{slug}/checkout', [\App\Http\Controllers\Api\ShopController::class, 'checkout'])->where('slug', '[a-z0-9-]{1,120}')->middleware('throttle:10,10');
    Route::get('shop/download/{token}', [\App\Http\Controllers\Api\ShopController::class, 'download'])->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:30,10');

    // A team member's invite link (sets their own password).
    Route::get('team-invite/{token}', [TeamController::class, 'showInvite'])->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:30,1');
    Route::post('team-invite/{token}', [TeamController::class, 'acceptInvite'])->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:10,10');

    // EU Compliance & Commercial Legal Framework
    Route::get('legal/privacy', [LegalController::class, 'privacy']);
    Route::get('legal/terms', [LegalController::class, 'terms']);
    Route::get('legal/impressum', [LegalController::class, 'impressum']);

    // Enterprise Privacy & Trust Center
    Route::get('legal/dpa', [PrivacyController::class, 'dpa']);
    Route::get('privacy/subprocessors', [PrivacyController::class, 'subprocessors']);
    Route::post('privacy/subprocessors/subscribe', [PrivacyController::class, 'subscribeSubprocessor'])->middleware('throttle:10,1');
    Route::post('privacy/redact', [PrivacyController::class, 'redact'])->middleware('throttle:30,1');

    // Everyone signed in: their own account, and the email confirmation resend.
    Route::middleware(['auth', 'active', 'ability:account'])->group(function () {
        Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:3,10');
        Route::get('account', [AccountController::class, 'show']);
        Route::get('auth/password-fresh', [AccountController::class, 'passwordFresh']);
        Route::post('auth/confirm-password', [AccountController::class, 'confirmPasswordNow'])->middleware('throttle:6,1');
        // Two-factor setup stays open to an admin who hasn't turned it on yet (everything else waits).
        Route::post('account/two-factor/start', [AccountController::class, 'twoFactorStart'])->middleware('throttle:10,1');
        Route::post('account/two-factor/confirm', [AccountController::class, 'twoFactorConfirm'])->middleware('throttle:10,1');
        Route::middleware('two-factor')->group(function () {
            Route::patch('account', [AccountController::class, 'update']);
            Route::put('account/password', [AccountController::class, 'password'])->middleware('throttle:5,1');
            Route::put('account/email', [AccountController::class, 'email'])->middleware('throttle:5,1');
            Route::post('account/two-factor/recovery-codes', [AccountController::class, 'recoveryCodes'])->middleware('throttle:5,1');
            Route::delete('account/two-factor', [AccountController::class, 'twoFactorDisable'])->middleware('throttle:5,1');
            Route::delete('account', [AccountController::class, 'destroy'])->middleware('throttle:5,1');
            Route::get('account/sessions', [AccountController::class, 'sessions']);
            Route::delete('account/sessions', [AccountController::class, 'endOtherSessions'])->middleware('throttle:5,1');
            Route::get('account/export', [AccountController::class, 'export'])->middleware('throttle:5,1');
            Route::get('account/devices', [\App\Http\Controllers\Api\MobileTokenController::class, 'index']);
            Route::get('account/purchases/{order}/download', [\App\Http\Controllers\Api\ShopController::class, 'downloadMine'])->whereNumber('order');
            Route::delete('account/devices/{id}', [\App\Http\Controllers\Api\MobileTokenController::class, 'revoke'])->whereNumber('id');
        });
    });

    // Community Hub: for regular users, followers and clients
    Route::prefix('portal/community')->middleware(['auth', 'active', 'two-factor', 'ability:portal.community'])->group(function () {
        Route::get('hub', [\App\Http\Controllers\Api\CommunityController::class, 'hub']);
        Route::get('blueprints', [\App\Http\Controllers\Api\CommunityController::class, 'blueprints']);
        Route::post('blueprints/scaffold', [\App\Http\Controllers\Api\CommunityController::class, 'scaffoldBlueprint'])->middleware('throttle:10,1');
        Route::get('showcase', [\App\Http\Controllers\Api\CommunityController::class, 'showcase']);
        Route::post('showcase', [\App\Http\Controllers\Api\CommunityController::class, 'submitShowcase'])->middleware('throttle:10,1');
        Route::post('showcase/{id}/upvote', [\App\Http\Controllers\Api\CommunityController::class, 'upvoteShowcase'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post('ask', [\App\Http\Controllers\Api\CommunityController::class, 'ask'])->middleware('throttle:10,1');
        Route::post('upvote', [\App\Http\Controllers\Api\CommunityController::class, 'upvote'])->middleware('throttle:30,1');
        Route::post('items/{id}/vote', [\App\Http\Controllers\Api\CommunityController::class, 'vote'])->whereNumber('id')->middleware('throttle:60,1');
        Route::put('digest', [\App\Http\Controllers\Api\CommunityController::class, 'digest'])->middleware('throttle:10,1');
        Route::post('pro/checkout', [\App\Http\Controllers\Api\CommunityController::class, 'proCheckout'])->middleware('throttle:5,10');
        Route::post('pro/manage', [\App\Http\Controllers\Api\CommunityController::class, 'proManage'])->middleware('throttle:10,10');
        Route::get('courses', [\App\Http\Controllers\Api\LearningController::class, 'courses']);
        Route::get('courses/{id}', [\App\Http\Controllers\Api\LearningController::class, 'course'])->whereNumber('id');
        Route::post('lessons/{lesson}/complete', [\App\Http\Controllers\Api\LearningController::class, 'complete'])->whereNumber('lesson')->middleware('throttle:60,1');
        Route::get('events', [\App\Http\Controllers\Api\LearningController::class, 'events']);
        Route::post('events/{id}/rsvp', [\App\Http\Controllers\Api\LearningController::class, 'rsvp'])->whereNumber('id')->middleware('throttle:30,1');
        Route::get('events/{id}/ics', [\App\Http\Controllers\Api\LearningController::class, 'ics'])->whereNumber('id');
    });

    // Clients: only their own requests, payments and messages, once their email is confirmed.
    Route::prefix('portal')->middleware(['auth', 'active', 'verified', 'two-factor', 'ability:portal.requests'])->group(function () {
        Route::get('overview', [PortalController::class, 'overview']);
        Route::get('requests', [PortalController::class, 'index']);
        Route::post('requests', [PortalController::class, 'store'])->middleware('throttle:10,60');
        Route::get('requests/{deal}', [PortalController::class, 'show'])->whereNumber('deal');
        Route::get('requests/{deal}/milestones', [PortalController::class, 'milestones'])->whereNumber('deal');
        Route::post('requests/{deal}/accept', [PortalController::class, 'accept'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::post('requests/{deal}/pay', [PortalController::class, 'pay'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::get('requests/{deal}/messages', [PortalController::class, 'messages'])->whereNumber('deal');
        Route::post('requests/{deal}/messages', [PortalController::class, 'sendMessage'])->whereNumber('deal')->middleware('throttle:20,1');
        Route::get('unread', [PortalController::class, 'unread']);
        Route::post('requests/{deal}/approve', [PortalController::class, 'approve'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::post('requests/{deal}/changes', [PortalController::class, 'requestChanges'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::get('requests/{deal}/files', [PortalController::class, 'files'])->whereNumber('deal');
        Route::post('requests/{deal}/files', [PortalController::class, 'uploadFile'])->whereNumber('deal')->middleware('throttle:30,10');
        Route::get('requests/{deal}/files/{file}', [PortalController::class, 'downloadFile'])->whereNumber(['deal', 'file']);
        Route::get('requests/{deal}/files/{file}/stream', [PortalController::class, 'streamFile'])->whereNumber(['deal', 'file']);
        Route::delete('requests/{deal}/files/{file}', [PortalController::class, 'deleteFile'])->whereNumber(['deal', 'file']);
        Route::get('requests/{deal}/annotations', [PortalController::class, 'annotations'])->whereNumber('deal');
        Route::post('requests/{deal}/annotations', [PortalController::class, 'addAnnotation'])->whereNumber('deal')->middleware('throttle:30,1');
        Route::post('requests/{deal}/annotations/{annotation}/resolve', [PortalController::class, 'resolveAnnotation'])->whereNumber(['deal', 'annotation'])->middleware('throttle:30,1');
        Route::get('requests/{deal}/roi', [PortalController::class, 'roi'])->whereNumber('deal');
        Route::get('requests/{deal}/results-report', [PortalController::class, 'resultsReport'])->whereNumber('deal');
        Route::post('requests/{deal}/intake', [PortalController::class, 'saveIntake'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::post('requests/{deal}/copy/approve', [\App\Http\Controllers\Api\InquiryController::class, 'clientApprove'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::post('requests/{deal}/copy/changes', [\App\Http\Controllers\Api\InquiryController::class, 'clientChanges'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::post('requests/{deal}/onboarding/{key}', [PortalController::class, 'tickOnboarding'])->whereNumber('deal')->where('key', '[a-z0-9_]{1,40}')->middleware('throttle:30,1');
        Route::post('requests/{deal}/rights/{right}/extend', [PortalController::class, 'extendRight'])->whereNumber(['deal', 'right'])->middleware('throttle:10,10');
        Route::get('requests/{deal}/compliance', [PortalController::class, 'compliance'])->whereNumber('deal');
        Route::get('requests/{deal}/compliance/report', [PortalController::class, 'complianceReport'])->whereNumber('deal');
        Route::get('requests/{deal}/dpa', [PortalController::class, 'dpa'])->whereNumber('deal');
        Route::post('requests/{deal}/dpa/sign', [PortalController::class, 'signDpa'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::get('requests/{deal}/zdr-certificate', [PortalController::class, 'zdrCertificate'])->whereNumber('deal');
        Route::post('requests/{deal}/purge', [PortalController::class, 'purgeDealData'])->whereNumber('deal')->middleware('throttle:5,1');
        Route::get('privacy/access-log', [PrivacyController::class, 'accessLog']);
        Route::post('requests/{deal}/select-tier', [PortalController::class, 'selectTier'])->whereNumber('deal')->middleware('throttle:20,1');
        Route::get('requests/{deal}/add-ons', [PortalController::class, 'addOns'])->whereNumber('deal');
        Route::post('requests/{deal}/add-ons', [PortalController::class, 'applyAddOn'])->whereNumber('deal')->middleware('throttle:10,1');
        Route::get('brand-vault', [PortalController::class, 'brandVault']);
        Route::put('brand-vault', [PortalController::class, 'saveBrandVault'])->middleware('throttle:10,1');
        Route::post('brand-vault/verify-vies', [PortalController::class, 'verifyVies'])->middleware('throttle:10,1');
        Route::post('webhook/test', [PortalController::class, 'testWebhook'])->middleware('throttle:10,1');
        Route::get('referrals', [PortalController::class, 'referrals']);
        Route::get('calls/slots', [PortalController::class, 'slots']);
        Route::post('calls', [PortalController::class, 'bookCall'])->middleware('throttle:5,10');
        Route::middleware('ability:portal.billing')->group(function () {
            Route::get('billing', [PortalController::class, 'billing']);
            Route::get('payments/{payment}/receipt', [PortalController::class, 'receipt'])->whereNumber('payment');
            Route::get('requests/{deal}/invoice', [PortalController::class, 'invoice'])->whereNumber('deal');
            Route::post('checkout', [PortalController::class, 'checkout'])->middleware('throttle:5,10');
            Route::post('billing/manage', [PortalController::class, 'manageBilling'])->middleware('throttle:10,10');
        });
        Route::middleware('ability:portal.team')->group(function () {
            Route::get('team', [PortalController::class, 'team']);
            Route::post('team', [PortalController::class, 'invite'])->middleware('throttle:10,60');
            Route::post('team/join', [PortalController::class, 'join'])->middleware('throttle:10,1');
            Route::delete('team/members/{member}', [PortalController::class, 'removeMember'])->whereNumber('member');
            Route::delete('team/invites/{invite}', [PortalController::class, 'withdrawInvite'])->whereNumber('invite');
        });
    });

    // Staff & Team Production Pipeline (Admin & Staff)
    Route::middleware(['auth', 'active', 'two-factor', 'ability:staff.team'])->group(function () {
        Route::get('team', [TeamController::class, 'index']);
        Route::post('team/tasks', [TeamController::class, 'createTask'])->middleware('throttle:60,1');
        Route::patch('team/tasks/{id}', [TeamController::class, 'updateTask'])->whereNumber('id');
        Route::delete('team/tasks/{id}', [TeamController::class, 'deleteTask'])->whereNumber('id');
        Route::post('team/tasks/auto-repurpose', [TeamController::class, 'autoRepurpose'])->middleware('throttle:20,1');
        Route::post('team/thumbnails/simulate', [TeamController::class, 'simulateThumbnail']);
        Route::post('team/qa/toggle', [TeamController::class, 'toggleQaItem']);
        Route::get('team/payouts', [TeamController::class, 'payouts']);
        Route::get('team/checklists', [\App\Http\Controllers\Api\TeamOpsController::class, 'index']);
        Route::post('team/tasks/{id}/checklist/{key}', [\App\Http\Controllers\Api\TeamOpsController::class, 'tick'])->whereNumber('id')->where('key', '[a-z0-9_]{1,40}');
        Route::get('team/workload', [\App\Http\Controllers\Api\TeamOpsController::class, 'workload']);
        Route::get('team/brief-templates', [\App\Http\Controllers\Api\TeamWorkController::class, 'briefTemplates']);
        Route::post('team/tasks/{id}/brief/apply', [\App\Http\Controllers\Api\TeamWorkController::class, 'applyBrief'])->whereNumber('id');
        Route::put('team/tasks/{id}/brief', [\App\Http\Controllers\Api\TeamWorkController::class, 'saveBrief'])->whereNumber('id');
        Route::get('team/tasks/{id}/time', [\App\Http\Controllers\Api\TeamWorkController::class, 'time'])->whereNumber('id');
        Route::post('team/tasks/{id}/timer', [\App\Http\Controllers\Api\TeamWorkController::class, 'timer'])->whereNumber('id');
        Route::post('team/tasks/{id}/time', [\App\Http\Controllers\Api\TeamWorkController::class, 'addTime'])->whereNumber('id');
        Route::get('team/tasks/{id}/files', [\App\Http\Controllers\Api\TeamWorkController::class, 'files'])->whereNumber('id');
        Route::post('team/tasks/{id}/files', [\App\Http\Controllers\Api\TeamWorkController::class, 'upload'])->whereNumber('id')->middleware('throttle:30,10');
        Route::get('team/tasks/{id}/files/{file}/stream', [\App\Http\Controllers\Api\TeamWorkController::class, 'stream'])->whereNumber(['id', 'file']);
        Route::get('team/tasks/{id}/notes', [\App\Http\Controllers\Api\TeamWorkController::class, 'notes'])->whereNumber('id');
        Route::post('team/tasks/{id}/notes', [\App\Http\Controllers\Api\TeamWorkController::class, 'addNote'])->whereNumber('id')->middleware('throttle:60,1');
        Route::post('team/tasks/{id}/notes/{note}/resolve', [\App\Http\Controllers\Api\TeamWorkController::class, 'resolveNote'])->whereNumber(['id', 'note']);
        Route::post('team/tasks/{id}/send-back', [\App\Http\Controllers\Api\TeamWorkController::class, 'sendBack'])->whereNumber('id');
        // Hers alone: money and who is on the team.
        Route::middleware('ability:studio.private')->group(function () {
            Route::post('team/tasks/{id}/approve-payout', [TeamController::class, 'approvePayout'])->whereNumber('id');
            Route::post('team/tasks/{id}/paid', [TeamController::class, 'markPaid'])->whereNumber('id');
            Route::post('team/staff', [TeamController::class, 'inviteStaff'])->middleware('throttle:20,60');
            Route::delete('team/invites/{id}', [TeamController::class, 'withdrawInvite'])->whereNumber('id');
            Route::delete('team/staff/{id}', [TeamController::class, 'removeStaff'])->whereNumber('id');
            Route::put('team/checklists', [\App\Http\Controllers\Api\TeamOpsController::class, 'save']);
            Route::put('team/hours/{user}', [\App\Http\Controllers\Api\TeamOpsController::class, 'saveHours'])->whereNumber('user');
            Route::put('team/brief-templates', [\App\Http\Controllers\Api\TeamWorkController::class, 'saveBriefTemplate']);
            Route::get('team/rates', [\App\Http\Controllers\Api\TeamWorkController::class, 'rates']);
            Route::put('team/rates/{user}', [\App\Http\Controllers\Api\TeamWorkController::class, 'saveRate'])->whereNumber('user');
        });
    });

    // Her: the Clients page (client accounts, linking deals, invites, messages).
    Route::middleware(['auth', 'active', 'two-factor', 'ability:clients.manage'])->group(function () {
        Route::get('clients', [ClientsController::class, 'index']);
        Route::get('clients/{client}', [ClientsController::class, 'show'])->whereNumber('client');
        Route::patch('clients/{client}', [ClientsController::class, 'update'])->whereNumber('client');
        Route::post('clients/{client}/verification', [ClientsController::class, 'resendVerification'])->whereNumber('client')->middleware('throttle:5,10');
        Route::post('clients/{client}/deals', [ClientsController::class, 'linkDeal'])->whereNumber('client');
        Route::delete('clients/{client}/deals/{deal}', [ClientsController::class, 'unlinkDeal'])->whereNumber('client');
        Route::post('deals/{deal}/invite', [ClientsController::class, 'invite'])->middleware('throttle:10,10');
        Route::get('deals/{deal}/messages', [ClientsController::class, 'messages']);
        Route::get('clients/unread', [ClientsController::class, 'unread']);
        Route::get('deals/{deal}/files', [ClientsController::class, 'files']);
        Route::post('deals/{deal}/files', [ClientsController::class, 'uploadFile']);
        Route::get('deals/{deal}/files/{file}', [ClientsController::class, 'downloadFile'])->whereNumber('file');
        Route::get('deals/{deal}/files/{file}/stream', [ClientsController::class, 'streamFile'])->whereNumber('file');
        Route::delete('deals/{deal}/files/{file}', [ClientsController::class, 'deleteFile'])->whereNumber('file');
        Route::get('deals/{deal}/annotations', [ClientsController::class, 'annotations'])->whereNumber('deal');
        Route::post('deals/{deal}/annotations', [ClientsController::class, 'addAnnotation'])->whereNumber('deal');
        Route::post('deals/{deal}/annotations/{annotation}/resolve', [ClientsController::class, 'resolveAnnotation'])->whereNumber(['deal', 'annotation']);
        Route::get('deals/{deal}/roi', [ClientsController::class, 'roi'])->whereNumber('deal');
        Route::delete('deals/{deal}/share-links', [ClientsController::class, 'revokeLinks']);
        Route::get('security/audit', [ClientsController::class, 'audit']);
        // Getting paid and keeping work moving: terms per deal, usage rights, defaults, onboarding.
        Route::get('deals/{deal}/terms', [\App\Http\Controllers\Api\DealTermsController::class, 'show'])->whereNumber('deal');
        Route::put('deals/{deal}/terms', [\App\Http\Controllers\Api\DealTermsController::class, 'update'])->whereNumber('deal');
        Route::post('deals/{deal}/rights', [\App\Http\Controllers\Api\DealTermsController::class, 'addRight'])->whereNumber('deal');
        Route::patch('deals/{deal}/rights/{right}', [\App\Http\Controllers\Api\DealTermsController::class, 'updateRight'])->whereNumber(['deal', 'right']);
        Route::delete('deals/{deal}/rights/{right}', [\App\Http\Controllers\Api\DealTermsController::class, 'deleteRight'])->whereNumber(['deal', 'right']);
        Route::get('clients/terms', [\App\Http\Controllers\Api\DealTermsController::class, 'defaults']);
        Route::put('clients/terms', [\App\Http\Controllers\Api\DealTermsController::class, 'saveDefaults']);
        Route::get('clients/onboarding', [\App\Http\Controllers\Api\DealTermsController::class, 'onboardingSteps']);
        Route::put('clients/onboarding', [\App\Http\Controllers\Api\DealTermsController::class, 'saveOnboardingSteps']);
        Route::get('clients/intake', [ClientsController::class, 'intakeQuestions']);
        Route::put('clients/intake', [ClientsController::class, 'saveIntakeQuestions']);
        Route::post('deals/{deal}/messages', [ClientsController::class, 'sendMessage']);
    });

    // The studio itself: hers alone (Role::STUDIO), after two-factor sign-in.
    Route::middleware(['auth', 'active', 'two-factor', 'ability:studio', 'studio-private'])->group(function () {
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
        Route::post('scripts/{script}/live', [ApiController::class, 'scriptLive'])->middleware('throttle:120,1');
        Route::delete('scripts/{script}', [ApiController::class, 'deleteScript']);
        Route::post('scripts/{script}/review', [ApiController::class, 'reviewScript']);
        Route::post('scripts/{script}/substack', [ApiController::class, 'substackScript']);
        Route::post('scripts/{script}/edit-brief', [ApiController::class, 'editBriefScript']);
        Route::post('scripts/{script}/edit-brief-pack', [ApiController::class, 'editBriefPack']);
        Route::post('scripts/{script}/sponsor', [ApiController::class, 'attachDeal']);
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
        // The money loop: follow-ups, where leads come from, sponsor prospects
        Route::get('deals/follow-ups', [PipelineController::class, 'followUps']);
        Route::get('deals/insights', [DealRoomController::class, 'insights']);
        Route::post('deals/{deal}/room-link', [DealRoomController::class, 'link']);
        Route::post('deals/{deal}/prep', [DealRoomController::class, 'prep']);
        Route::post('deals/{deal}/lost', [DealRoomController::class, 'lost']);
        Route::get('deals/attribution', [PipelineController::class, 'attribution']);
        Route::get('deals/prospects', [PipelineController::class, 'prospects']);
        Route::post('deals/prospects', [PipelineController::class, 'addProspect']);
        Route::post('deals/{deal}/follow-up', [PipelineController::class, 'draftFollowUp']);
        Route::post('deals/{deal}/contacted', [PipelineController::class, 'contacted']);
        Route::post('deals/{deal}/snooze', [PipelineController::class, 'snooze']);
        Route::get('deals/{deal}/proposal', [PipelineController::class, 'showProposal']);
        Route::post('deals/{deal}/proposal', [PipelineController::class, 'proposal']);
        Route::put('deals/{deal}/proposal', [PipelineController::class, 'saveProposal']);
        Route::post('deals/{deal}/proposal/sent', [PipelineController::class, 'proposalSent']);
        Route::post('deals/{deal}/payment-link', [PipelineController::class, 'paymentLink']);
        Route::post('deals/{deal}/kit-link', [PipelineController::class, 'kitLink']);
        Route::get('deals/{deal}/meeting.ics', [PipelineController::class, 'meetingIcs']);
        Route::get('website', [\App\Http\Controllers\LandingPageController::class, 'workspace']);
        Route::get('landing/funnel', [PipelineController::class, 'funnel']);
        Route::get('testimonials', [SocialProofController::class, 'testimonials']);
        Route::post('testimonials', [SocialProofController::class, 'storeTestimonial']);
        Route::patch('testimonials/{testimonial}', [SocialProofController::class, 'updateTestimonial']);
        Route::delete('testimonials/{testimonial}', [SocialProofController::class, 'destroyTestimonial']);
        Route::post('deals/{deal}/testimonial-request', [SocialProofController::class, 'requestTestimonial']);
        Route::get('subscribers', [SocialProofController::class, 'subscribers']);
        Route::get('subscribers/export', [SocialProofController::class, 'exportSubscribers'])->middleware('fresh-password');
        Route::delete('subscribers/{subscriber}', [SocialProofController::class, 'destroySubscriber']);
        Route::put('landing/lead-magnet', [SocialProofController::class, 'saveMagnet']);
        Route::post('engage/items/{item}/to-deal', [SocialProofController::class, 'inboxToDeal']);
        Route::post('landing/tracked-link', [PipelineController::class, 'trackedLink']);
        Route::get('landing/booking-hours', [PipelineController::class, 'bookingHours']);
        Route::put('landing/booking-hours', [PipelineController::class, 'saveBookingHours']);
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
        Route::post('youtube/pre-flight', [YouTubeStudioController::class, 'preFlight']);
        Route::get('youtube/checks/{check}', [YouTubeStudioController::class, 'getCheck']);
        Route::get('youtube/ab-tests', [YouTubeStudioController::class, 'abTests']);
        Route::post('youtube/ab-tests', [YouTubeStudioController::class, 'startAbTest']);
        Route::post('youtube/ab-tests/{abTest}/performance', [YouTubeStudioController::class, 'updateAbTestPerformance']);
        Route::post('youtube/ab-tests/{abTest}/conclude', [YouTubeStudioController::class, 'concludeAbTest']);
        Route::post('youtube/ab-tests/{abTest}/stop', [YouTubeStudioController::class, 'stopAbTest']);

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

        // The hero at the top of the landing page
        Route::get('hero', [\App\Http\Controllers\LandingPageController::class, 'heroEdit']);
        Route::put('hero', [\App\Http\Controllers\LandingPageController::class, 'heroSave']);

        // The moving band above the footer
        Route::get('marquee', [\App\Http\Controllers\LandingPageController::class, 'marqueeEdit']);
        Route::put('marquee', [\App\Http\Controllers\LandingPageController::class, 'marqueeSave']);

        // Brand partners & stages strip
        Route::get('partners', [\App\Http\Controllers\LandingPageController::class, 'partnersEdit']);
        Route::put('partners', [\App\Http\Controllers\LandingPageController::class, 'partnersSave']);

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
        Route::get('agents/signals', [AgentsController::class, 'signals']);
        Route::post('agents/{agent}/run', [AgentsController::class, 'run'])->whereNumber('agent');
        Route::post('agents/{agent}/learn', [AgentsController::class, 'learn'])->whereNumber('agent');
        Route::post('agents/{agent}/repurpose-script', [AgentsController::class, 'repurposeScript'])->whereNumber('agent');
        Route::post('agents/{agent}/spike-breakout', [AgentsController::class, 'spikeBreakout'])->whereNumber('agent');
        Route::post('agents/{agent}/harness/run', [AgentsController::class, 'runHarness'])->whereNumber('agent');
        Route::post('agents/{agent}/harness/benchmark', [AgentsController::class, 'benchmark'])->whereNumber('agent');
        Route::get('agents/{agent}/harness/evals', [AgentsController::class, 'evals'])->whereNumber('agent');
        Route::get('agents/{agent}/evals', [AgentsController::class, 'evals'])->whereNumber('agent');
        Route::get('agents/{agent}/memory', [AgentsController::class, 'memory'])->whereNumber('agent');
        Route::post('agents/{agent}/deliberate', [AgentsController::class, 'deliberate'])->where('agent', '[0-9]+|studio');
        Route::get('agents/guardrails', [AgentsController::class, 'guardrails']);
        Route::post('agents/{agent}/swarm/plan', [AgentsController::class, 'planSwarm'])->whereNumber('agent');
        Route::post('agents/missions/{mission}/step', [AgentsController::class, 'stepSwarm'])->whereNumber('mission');
        Route::post('agents/missions/{mission}/run', [AgentsController::class, 'runSwarm'])->whereNumber('mission');
        Route::get('agents/{agent}/missions', [AgentsController::class, 'missions'])->whereNumber('agent');
        Route::post('agents/{agent}/prompt/optimize', [AgentsController::class, 'optimizePrompt'])->whereNumber('agent');
        Route::get('agents/{agent}/prompt/versions', [AgentsController::class, 'promptVersions'])->whereNumber('agent');
        Route::post('agents/{agent}/tree-search', [AgentsController::class, 'treeSearch'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/debate', [AgentsController::class, 'debate'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/sponsor-negotiate', [AgentsController::class, 'sponsorNegotiate'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/sponsor-save-deal', [AgentsController::class, 'sponsorSaveDeal'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/transmedia-cascade', [AgentsController::class, 'transmediaCascade'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/cascade-schedule', [AgentsController::class, 'cascadeSchedule'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/hook-matrix', [AgentsController::class, 'hookMatrix'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/fact-check', [AgentsController::class, 'factCheck'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/reverse-engineer-outlier', [AgentsController::class, 'reverseEngineerOutlier'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/bandit-titles', [AgentsController::class, 'banditTitles'])->where('agent', '[0-9]+|studio');
        Route::post('agents/launch-title-test', [AgentsController::class, 'launchTitleTest']);
        Route::post('agents/{agent}/triage-leads', [AgentsController::class, 'triageLeads'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/escalate-lead-deal', [AgentsController::class, 'escalateLeadDeal'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/audit-retention', [AgentsController::class, 'auditRetention'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/audit-deal-fulfillment', [AgentsController::class, 'auditDealFulfillment'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/generate-deal-wrap-report', [AgentsController::class, 'generateDealWrapReport'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/synthesize-brand-pitch', [AgentsController::class, 'synthesizeBrandPitch'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/recycle-evergreen', [AgentsController::class, 'recycleEvergreen'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/schedule-evergreen-reboot', [AgentsController::class, 'scheduleEvergreenReboot'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/shield-algorithm', [AgentsController::class, 'shieldAlgorithm'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/plan-sprint', [AgentsController::class, 'planSprint'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/architect-sponsor-placement', [AgentsController::class, 'architectSponsorPlacement'])->where('agent', '[0-9]+|studio');
        Route::post('agents/{agent}/repurposing-arbitrage', [AgentsController::class, 'repurposingArbitrage'])->where('agent', '[0-9]+|studio');
        Route::get('studio/infra/health', [AgentsController::class, 'infraHealth']);
        Route::post('mcp', [AgentsController::class, 'mcp']);
        Route::get('mcp/tools', [AgentsController::class, 'mcpTools']);

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
        Route::get('backups/{name}', [BackupController::class, 'download'])->where('name', '[\w.-]+')->middleware('fresh-password');
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
        Route::get('automations', [ApiController::class, 'automations']);

        // User Multi-Model Keys & Credit System
        Route::get('user/keys', [\App\Http\Controllers\Api\UserKeysController::class, 'index']);
        Route::post('user/keys', [\App\Http\Controllers\Api\UserKeysController::class, 'store']);
        Route::delete('user/keys/{provider}', [\App\Http\Controllers\Api\UserKeysController::class, 'destroy']);
        Route::post('user/keys/test', [\App\Http\Controllers\Api\UserKeysController::class, 'test']);
        // Slow AI work in the background (the dashboard polls; see RunAiTask).
        Route::post('tasks', [\App\Http\Controllers\Api\TasksController::class, 'store'])->middleware('throttle:20,1');
        Route::get('tasks/{task}', [\App\Http\Controllers\Api\TasksController::class, 'show']);
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

        // ---- Admin only below: every path here is in Role::PRIVATE_PATHS -------------------
        // Community Hub management: publish, answer, approve, Pro tier, warm leads.
        Route::get('community/admin', [\App\Http\Controllers\Api\CommunityController::class, 'adminIndex']);
        Route::post('community/admin/items', [\App\Http\Controllers\Api\CommunityController::class, 'adminStore']);
        Route::patch('community/admin/items/{id}', [\App\Http\Controllers\Api\CommunityController::class, 'adminUpdate'])->whereNumber('id');
        Route::delete('community/admin/items/{id}', [\App\Http\Controllers\Api\CommunityController::class, 'adminDestroy'])->whereNumber('id');
        Route::put('community/admin/settings', [\App\Http\Controllers\Api\CommunityController::class, 'adminSettings']);
        Route::post('community/admin/members/{user}/comp', [\App\Http\Controllers\Api\CommunityController::class, 'adminComp'])->whereNumber('user');
        Route::post('community/admin/members/{user}/to-crm', [\App\Http\Controllers\Api\CommunityController::class, 'adminToCrm'])->whereNumber('user');
        // Everything waiting on her, across clients, team, community and CRM.
        Route::get('needs-you', \App\Http\Controllers\Api\NeedsYouController::class);
        Route::get('needs-you/weekly', fn () => response()->json(['data' => $d = \App\Domains\Admin\WeeklyDigest::build(), 'text' => \App\Domains\Admin\WeeklyDigest::text($d), 'on' => \App\Models\Setting::get('weekly_digest', '1') !== '0']));
        Route::put('needs-you/weekly', function (\Illuminate\Http\Request $r) {
            \App\Models\Setting::put('weekly_digest', $r->validate(['on' => 'required|boolean'])['on'] ? '1' : '0');

            return response()->json(['on' => \App\Models\Setting::get('weekly_digest', '1') !== '0']);
        });
        // CRM: contacts, stages, follow-ups and a timeline per person.
        Route::get('crm', [\App\Http\Controllers\Api\CrmController::class, 'index']);
        Route::post('crm', [\App\Http\Controllers\Api\CrmController::class, 'store']);
        Route::post('crm/sync', [\App\Http\Controllers\Api\CrmController::class, 'sync']);
        Route::get('crm/export', [\App\Http\Controllers\Api\CrmController::class, 'export']);
        Route::get('crm/{id}', [\App\Http\Controllers\Api\CrmController::class, 'show'])->whereNumber('id');
        Route::patch('crm/{id}', [\App\Http\Controllers\Api\CrmController::class, 'update'])->whereNumber('id');
        Route::delete('crm/{id}', [\App\Http\Controllers\Api\CrmController::class, 'destroy'])->whereNumber('id');
        Route::post('crm/{id}/activities', [\App\Http\Controllers\Api\CrmController::class, 'addActivity'])->whereNumber('id');
        Route::post('crm/{id}/activities/{activity}/done', [\App\Http\Controllers\Api\CrmController::class, 'completeActivity'])->whereNumber(['id', 'activity']);
        // White-label branding for the client & member portal.
        Route::get('portal-brand', [\App\Http\Controllers\Api\PortalBrandController::class, 'edit']);
        Route::put('portal-brand', [\App\Http\Controllers\Api\PortalBrandController::class, 'update']);
        Route::post('portal-brand/logo', [\App\Http\Controllers\Api\PortalBrandController::class, 'uploadLogo']);
        Route::delete('portal-brand/logo', [\App\Http\Controllers\Api\PortalBrandController::class, 'deleteLogo']);
        // Courses and live events (hers).
        Route::get('community/admin/courses', [\App\Http\Controllers\Api\LearningController::class, 'adminCourses']);
        Route::post('community/admin/courses', [\App\Http\Controllers\Api\LearningController::class, 'saveCourse']);
        Route::patch('community/admin/courses/{id}', [\App\Http\Controllers\Api\LearningController::class, 'saveCourse'])->whereNumber('id');
        Route::delete('community/admin/courses/{id}', [\App\Http\Controllers\Api\LearningController::class, 'deleteCourse'])->whereNumber('id');
        Route::post('community/admin/courses/{course}/lessons', [\App\Http\Controllers\Api\LearningController::class, 'saveLesson'])->whereNumber('course');
        Route::patch('community/admin/lessons/{id}', [\App\Http\Controllers\Api\LearningController::class, 'updateLesson'])->whereNumber('id');
        Route::delete('community/admin/lessons/{id}', [\App\Http\Controllers\Api\LearningController::class, 'deleteLesson'])->whereNumber('id');
        Route::get('community/admin/events', [\App\Http\Controllers\Api\LearningController::class, 'adminEvents']);
        Route::post('community/admin/events', [\App\Http\Controllers\Api\LearningController::class, 'saveEvent']);
        Route::patch('community/admin/events/{id}', [\App\Http\Controllers\Api\LearningController::class, 'saveEvent'])->whereNumber('id');
        Route::delete('community/admin/events/{id}', [\App\Http\Controllers\Api\LearningController::class, 'deleteEvent'])->whereNumber('id');
        Route::post('community/admin/items/{id}/to-idea', [\App\Http\Controllers\Api\CommunityController::class, 'adminToIdea'])->whereNumber('id');
        // Affiliate links: /go/{slug}, clicks, commissions, dead-link check.
        Route::get('affiliates', [\App\Http\Controllers\Api\AffiliateController::class, 'index']);
        Route::post('affiliates', [\App\Http\Controllers\Api\AffiliateController::class, 'store']);
        Route::patch('affiliates/{id}', [\App\Http\Controllers\Api\AffiliateController::class, 'update'])->whereNumber('id');
        Route::delete('affiliates/{id}', [\App\Http\Controllers\Api\AffiliateController::class, 'destroy'])->whereNumber('id');
        Route::post('affiliates/earnings', [\App\Http\Controllers\Api\AffiliateController::class, 'addEarning']);
        Route::delete('affiliates/earnings/{id}', [\App\Http\Controllers\Api\AffiliateController::class, 'deleteEarning'])->whereNumber('id');
        Route::post('affiliates/import', [\App\Http\Controllers\Api\AffiliateController::class, 'import']);
        Route::post('affiliates/check', [\App\Http\Controllers\Api\AffiliateController::class, 'checkNow']);
        // Shop (hers): products, file, orders, resend a link.
        Route::get('shop/admin', [\App\Http\Controllers\Api\ShopController::class, 'admin']);
        Route::post('shop/admin/products', [\App\Http\Controllers\Api\ShopController::class, 'save']);
        Route::patch('shop/admin/products/{id}', [\App\Http\Controllers\Api\ShopController::class, 'update'])->whereNumber('id');
        Route::post('shop/admin/products/{id}/file', [\App\Http\Controllers\Api\ShopController::class, 'upload'])->whereNumber('id');
        Route::delete('shop/admin/products/{id}', [\App\Http\Controllers\Api\ShopController::class, 'destroy'])->whereNumber('id');
        Route::post('shop/admin/orders/{order}/resend', [\App\Http\Controllers\Api\ShopController::class, 'resend'])->whereNumber('order');
        // Newsletter campaigns (hers).
        Route::get('newsletter', [\App\Http\Controllers\Api\NewsletterController::class, 'index']);
        Route::post('newsletter', [\App\Http\Controllers\Api\NewsletterController::class, 'save']);
        Route::patch('newsletter/{id}', [\App\Http\Controllers\Api\NewsletterController::class, 'update'])->whereNumber('id');
        Route::delete('newsletter/{id}', [\App\Http\Controllers\Api\NewsletterController::class, 'destroy'])->whereNumber('id');
        Route::get('newsletter/from-script/{script}', [\App\Http\Controllers\Api\NewsletterController::class, 'fromScript'])->whereNumber('script');
        Route::post('newsletter/{id}/test', [\App\Http\Controllers\Api\NewsletterController::class, 'test'])->whereNumber('id');
        Route::post('newsletter/{id}/send', [\App\Http\Controllers\Api\NewsletterController::class, 'send'])->whereNumber('id');
        // Inquiry autopilot and sponsored-copy approval (hers).
        Route::get('inquiry-rules', [\App\Http\Controllers\Api\InquiryController::class, 'rules']);
        Route::put('inquiry-rules', [\App\Http\Controllers\Api\InquiryController::class, 'saveRules']);
        Route::post('deals/{deal}/auto-reply/send', [\App\Http\Controllers\Api\InquiryController::class, 'sendReply'])->whereNumber('deal');
        Route::delete('deals/{deal}/auto-reply', [\App\Http\Controllers\Api\InquiryController::class, 'discardReply'])->whereNumber('deal');
        Route::get('deals/{deal}/copy', [\App\Http\Controllers\Api\InquiryController::class, 'copy'])->whereNumber('deal');
        Route::post('deals/{deal}/copy', [\App\Http\Controllers\Api\InquiryController::class, 'sendCopy'])->whereNumber('deal');
        Route::put('deals/{deal}/copy/override', [\App\Http\Controllers\Api\InquiryController::class, 'overrideCopy'])->whereNumber('deal');
        // Translated titles & descriptions for YouTube (hers).
        Route::get('localize/{video}', [\App\Http\Controllers\Api\LocalizeController::class, 'show'])->where('video', '[A-Za-z0-9_-]{11}');
        Route::post('localize/{video}/translate', [\App\Http\Controllers\Api\LocalizeController::class, 'translate'])->where('video', '[A-Za-z0-9_-]{11}');
        Route::put('localize/{video}/{lang}', [\App\Http\Controllers\Api\LocalizeController::class, 'update'])->where(['video' => '[A-Za-z0-9_-]{11}', 'lang' => '[A-Za-z-]{2,8}']);
        Route::delete('localize/{video}/{lang}', [\App\Http\Controllers\Api\LocalizeController::class, 'destroy'])->where(['video' => '[A-Za-z0-9_-]{11}', 'lang' => '[A-Za-z-]{2,8}']);
        Route::post('localize/{video}/push', [\App\Http\Controllers\Api\LocalizeController::class, 'push'])->where('video', '[A-Za-z0-9_-]{11}');
        // @@ROLE-EXPANSION-ROUTES@@
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
// Affiliate links: count the click, then on to the partner.
Route::get('go/{slug}', [\App\Http\Controllers\Api\AffiliateController::class, 'go'])->where('slug', '[A-Za-z0-9-]{1,60}')->middleware('throttle:120,1');

// "Login with Google" (uses the Google app from Connections → YouTube; only her own email)
// The link in a client's confirmation email (signed; the hash must match their email).
Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->whereNumber('id')->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::get('auth/google/redirect', [\App\Http\Controllers\GoogleLoginController::class, 'redirect']);
Route::get('auth/google/callback', [\App\Http\Controllers\GoogleLoginController::class, 'callback']);
Route::get('auth/linkedin/redirect', [\App\Http\Controllers\OAuthLoginController::class, 'linkedinRedirect']);
Route::get('auth/linkedin/callback', [\App\Http\Controllers\OAuthLoginController::class, 'linkedinCallback']);

// OAuth return from each network (a browser redirect, so it uses the session, not the JSON API)
Route::get('oauth/{platform}/callback', [SocialAccountsController::class, 'callback'])->where('platform', '[a-z]+');

// Static public image assets (SVG / PNG / JPG)
Route::get('images/{file}', function ($file) {
    // Only real image files inside public/images: "../" (or %2F-encoded) must never reach .env.
    $root = realpath(public_path('images'));
    $path = realpath(public_path('images/'.$file));
    $mimes = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
    if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path) || ! isset($mimes[$ext])) {
        abort(404);
    }

    return response()->file($path, ['Content-Type' => $mimes[$ext], 'X-Content-Type-Options' => 'nosniff']);
})->where('file', '[A-Za-z0-9._\-/]+');

// Single Page Application (SPA) entry point - pure HTML + Vanilla JavaScript
Route::get('install', function () {
    return response()->file(public_path('index.html'));
});

Route::fallback(function () {
    return response()->file(public_path('index.html'));
});
