<?php

namespace App\Http\Controllers\Api;

use App\Domains\Community\Community;
use App\Domains\Community\Models\CommunityItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Courses (ordered lessons, progress, points) and live events (RSVP, calendar file,
 * reminders, replay post) in the Community Hub, plus her side of both. Pro-only courses,
 * lessons and events show their titles to everyone and their content to Pro members.
 */
class LearningController extends Controller
{
    // ==========================================================================================
    // Courses: members
    // ==========================================================================================

    public function courses(Request $request): JsonResponse
    {
        $user = $request->user();
        $pro = Community::isPro($user);
        $done = DB::table('lesson_progress')->where('user_id', $user->id)->pluck('lesson_id')->flip();
        $lessons = DB::table('course_lessons')->get(['id', 'course_id'])->groupBy('course_id');

        return response()->json([
            'courses' => DB::table('courses')->where('status', 'published')->orderBy('position')->orderBy('id')->get()
                ->map(function ($c) use ($lessons, $done, $pro) {
                    $ids = ($lessons[$c->id] ?? collect())->pluck('id');

                    return [
                        'id' => $c->id,
                        'title' => $c->title,
                        'description' => $c->description,
                        'tier' => $c->tier,
                        'locked' => $c->tier === 'pro' && !$pro,
                        'lessons' => $ids->count(),
                        'completed' => $ids->filter(fn ($id) => isset($done[$id]))->count(),
                    ];
                })->values(),
        ]);
    }

    public function course(Request $request, int $id): JsonResponse
    {
        $c = DB::table('courses')->where('status', 'published')->where('id', $id)->first();
        abort_unless($c, 404);
        $user = $request->user();
        $pro = Community::isPro($user);
        $done = DB::table('lesson_progress')->where('user_id', $user->id)->pluck('completed_at', 'lesson_id');

        return response()->json([
            'course' => ['id' => $c->id, 'title' => $c->title, 'description' => $c->description, 'tier' => $c->tier],
            'lessons' => DB::table('course_lessons')->where('course_id', $c->id)->orderBy('position')->orderBy('id')->get()
                ->map(function ($l) use ($c, $pro, $done) {
                    $locked = ($c->tier === 'pro' && $l->tier !== 'free_preview' && !$pro) || ($l->tier === 'pro' && !$pro);

                    return [
                        'id' => $l->id,
                        'title' => $l->title,
                        'minutes' => $l->minutes,
                        'locked' => $locked,
                        'body' => $locked ? null : $l->body,
                        'video_url' => $locked ? null : $l->video_url,
                        'done' => isset($done[$l->id]),
                    ];
                })->values(),
            'pro' => $pro,
        ]);
    }

    public function complete(Request $request, int $lesson): JsonResponse
    {
        $data = $request->validate(['done' => 'required|boolean']);
        $l = DB::table('course_lessons')->join('courses', 'courses.id', '=', 'course_lessons.course_id')
            ->where('courses.status', 'published')->where('course_lessons.id', $lesson)
            ->first(['course_lessons.*', 'courses.tier as course_tier']);
        abort_unless($l, 404);
        $user = $request->user();
        $pro = Community::isPro($user);
        abort_if((($l->course_tier === 'pro' && $l->tier !== 'free_preview') || $l->tier === 'pro') && !$pro, 403, 'This lesson is for Pro members.');
        if ($data['done']) {
            $new = DB::table('lesson_progress')->insertOrIgnore(['user_id' => $user->id, 'lesson_id' => $lesson, 'completed_at' => now()]);
            if ($new) {
                Community::award($user, 'lesson');
            }
        } else {
            DB::table('lesson_progress')->where('user_id', $user->id)->where('lesson_id', $lesson)->delete();
        }

        return response()->json(['ok' => true, 'done' => (bool)$data['done']]);
    }

    // ==========================================================================================
    // Events: members
    // ==========================================================================================

    public function events(Request $request): JsonResponse
    {
        $user = $request->user();
        $pro = Community::isPro($user);
        $mine = DB::table('event_rsvps')->where('user_id', $user->id)->pluck('event_id')->flip();
        $counts = DB::table('event_rsvps')->select('event_id', DB::raw('count(*) as n'))->groupBy('event_id')->pluck('n', 'event_id');
        $rows = DB::table('community_events')->where('starts_at', '>=', now()->subDays(60))->orderBy('starts_at')->get()
            ->map(fn ($e) => $this->eventRow($e, $pro, isset($mine[$e->id]), (int)($counts[$e->id] ?? 0)));

        return response()->json([
            'upcoming' => $rows->filter(fn ($e) => !$e['past'])->values(),
            'past' => $rows->filter(fn ($e) => $e['past'])->reverse()->values(),
        ]);
    }

    public function rsvp(Request $request, int $id): JsonResponse
    {
        $e = DB::table('community_events')->where('id', $id)->first();
        abort_unless($e, 404);
        $user = $request->user();
        abort_if($e->tier === 'pro' && !Community::isPro($user), 403, 'This event is for Pro members.');
        $existing = DB::table('event_rsvps')->where('event_id', $id)->where('user_id', $user->id)->exists();
        if ($existing) {
            DB::table('event_rsvps')->where('event_id', $id)->where('user_id', $user->id)->delete();
        } else {
            DB::table('event_rsvps')->insert(['event_id' => $id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['going' => !$existing, 'count' => DB::table('event_rsvps')->where('event_id', $id)->count()]);
    }

    /**
     * A calendar file (.ics) for the event.
     */
    public function ics(Request $request, int $id): Response
    {
        $e = DB::table('community_events')->where('id', $id)->first();
        abort_unless($e, 404);
        $pro = Community::isPro($request->user());
        $start = \Carbon\CarbonImmutable::parse($e->starts_at)->utc();
        $end = $start->addMinutes((int)$e->minutes);
        $esc = fn ($s) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string)$s);
        $url = ($e->tier !== 'pro' || $pro) ? ($e->join_url ?: url('/community')) : url('/community');
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Creator Studio//Community//EN', 'BEGIN:VEVENT',
            'UID:event-' . $e->id . '@' . parse_url(url('/'), PHP_URL_HOST),
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:' . $esc($e->title),
            'DESCRIPTION:' . $esc(Str::limit((string)$e->description, 500) . "\n" . $url),
            'URL:' . $url,
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event-' . $e->id . '.ics"',
        ]);
    }

    // ==========================================================================================
    // Her side
    // ==========================================================================================

    public function adminCourses(): JsonResponse
    {
        $lessons = DB::table('course_lessons')->orderBy('position')->orderBy('id')->get()->groupBy('course_id');
        $progress = DB::table('lesson_progress')->select('lesson_id', DB::raw('count(*) as n'))->groupBy('lesson_id')->pluck('n', 'lesson_id');

        return response()->json([
            'courses' => DB::table('courses')->orderBy('position')->orderBy('id')->get()->map(fn ($c) => (array)$c + [
                'lessons' => ($lessons[$c->id] ?? collect())->map(fn ($l) => (array)$l + ['completions' => (int)($progress[$l->id] ?? 0)])->values(),
            ]),
        ]);
    }

    public function saveCourse(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title' => [$id ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => 'nullable|string|max:5000',
            'tier' => 'sometimes|in:free,pro',
            'status' => 'sometimes|in:draft,published',
            'position' => 'sometimes|integer|min:0',
        ]);
        if ($id) {
            abort_unless(DB::table('courses')->where('id', $id)->update($data + ['updated_at' => now()]) || DB::table('courses')->where('id', $id)->exists(), 404);
        } else {
            $id = DB::table('courses')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        }

        return $this->adminCourses();
    }

    public function deleteCourse(int $id): JsonResponse
    {
        DB::table('courses')->where('id', $id)->delete();

        return $this->adminCourses();
    }

    public function saveLesson(Request $request, ?int $course = null, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title' => [$id ? 'sometimes' : 'required', 'string', 'max:200'],
            'body' => 'nullable|string|max:100000',
            'video_url' => 'nullable|url:http,https|max:500',
            'tier' => 'sometimes|in:free,pro,free_preview',
            'minutes' => 'nullable|integer|min:0|max:1000',
            'position' => 'sometimes|integer|min:0',
        ]);
        if ($id) {
            abort_unless(DB::table('course_lessons')->where('id', $id)->exists(), 404);
            DB::table('course_lessons')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            abort_unless(DB::table('courses')->where('id', $course)->exists(), 404);
            DB::table('course_lessons')->insert($data + [
                'course_id' => $course,
                'position' => $data['position'] ?? ((int)DB::table('course_lessons')->where('course_id', $course)->max('position') + 1),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $this->adminCourses();
    }

    public function updateLesson(Request $request, int $id): JsonResponse
    {
        return $this->saveLesson($request, null, $id);
    }

    public function deleteLesson(int $id): JsonResponse
    {
        DB::table('course_lessons')->where('id', $id)->delete();

        return $this->adminCourses();
    }

    public function adminEvents(): JsonResponse
    {
        $counts = DB::table('event_rsvps')->select('event_id', DB::raw('count(*) as n'))->groupBy('event_id')->pluck('n', 'event_id');

        return response()->json([
            'events' => DB::table('community_events')->orderByDesc('starts_at')->take(100)->get()
                ->map(fn ($e) => $this->eventRow($e, true, false, (int)($counts[$e->id] ?? 0)) + ['join_url' => $e->join_url]),
        ]);
    }

    public function saveEvent(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title' => [$id ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => 'nullable|string|max:5000',
            'starts_at' => [$id ? 'sometimes' : 'required', 'date'],
            'minutes' => 'sometimes|integer|min:5|max:600',
            'join_url' => 'nullable|url:https|max:500',
            'replay_url' => 'nullable|url:https|max:500',
            'tier' => 'sometimes|in:free,pro',
        ]);
        if (isset($data['starts_at'])) {
            $data['starts_at'] = \Carbon\CarbonImmutable::parse($data['starts_at'])->utc()->toDateTimeString();
            $data['reminded_day_at'] = null;
            $data['reminded_hour_at'] = null;
        }
        if ($id) {
            $e = DB::table('community_events')->where('id', $id)->first();
            abort_unless($e, 404);
            DB::table('community_events')->where('id', $id)->update($data + ['updated_at' => now()]);
            // A replay link becomes a post (once).
            if (!empty($data['replay_url']) && !$e->replay_item_id) {
                $item = CommunityItem::create([
                    'type' => 'dispatch',
                    'title' => 'Replay: ' . ($data['title'] ?? $e->title),
                    'body' => $data['description'] ?? $e->description,
                    'url' => $data['replay_url'],
                    'tier' => $data['tier'] ?? $e->tier,
                    'status' => 'published',
                    'published_at' => now(),
                    'user_id' => $request->user()->id,
                ]);
                DB::table('community_events')->where('id', $id)->update(['replay_item_id' => $item->id]);
            }
        } else {
            DB::table('community_events')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        }

        return $this->adminEvents();
    }

    public function deleteEvent(int $id): JsonResponse
    {
        DB::table('community_events')->where('id', $id)->delete();

        return $this->adminEvents();
    }

    // ==========================================================================================
    // Scheduler: event reminders and win-back emails
    // ==========================================================================================

    /**
     * 24 hours and 1 hour before, to everyone who RSVP'd. Returns emails sent.
     */
    public static function remindEvents(): int
    {
        $sent = 0;
        foreach ([['reminded_day_at', 24 * 60, 'tomorrow'], ['reminded_hour_at', 60, 'in an hour']] as [$col, $mins, $when]) {
            $events = DB::table('community_events')->whereNull($col)
                ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addMinutes($mins))->get();
            foreach ($events as $e) {
                $users = User::whereIn('id', DB::table('event_rsvps')->where('event_id', $e->id)->pluck('user_id'))->where('notify_email', true)->get();
                foreach ($users as $u) {
                    if (AuthController::mail($u->email, "Starting {$when}: {$e->title}", "Hi {$u->name},\n\n“{$e->title}” starts {$when} ("
                        . \Carbon\CarbonImmutable::parse($e->starts_at)->toDayDateTimeString() . " UTC).\n\n" . ($e->join_url ?: url('/community')))) {
                        $sent++;
                    }
                }
                DB::table('community_events')->where('id', $e->id)->update([$col => now()]);
            }
        }

        return $sent;
    }

    private function eventRow(object $e, bool $pro, bool $going, int $count): array
    {
        $locked = $e->tier === 'pro' && !$pro;
        $start = \Carbon\CarbonImmutable::parse($e->starts_at);

        return [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'starts_at' => $start->toIso8601String(),
            'minutes' => (int)$e->minutes,
            'tier' => $e->tier,
            'locked' => $locked,
            'join_url' => $locked ? null : $e->join_url,
            'replay_url' => $locked ? null : $e->replay_url,
            'going' => $going,
            'count' => $count,
            'past' => $start->addMinutes((int)$e->minutes)->isPast(),
            'live' => $start->isPast() && $start->addMinutes((int)$e->minutes)->isFuture(),
        ];
    }
}
