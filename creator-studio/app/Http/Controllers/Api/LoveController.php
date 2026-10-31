<?php

namespace App\Http\Controllers\Api;

use App\Domains\Love\Love;
use App\Domains\Love\LoveLetter;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** The "For you" page: letters, special dates, milestones, and her photo. */
class LoveController extends Controller
{
    /** What the app checks on open: new letters, milestones to celebrate, today's special day. */
    public function status(): JsonResponse
    {
        if (! Love::enabled()) {
            return response()->json(['enabled' => false]);
        }
        $new = Love::unlockDue();

        return response()->json([
            'enabled' => true,
            'unlocked' => array_map(fn ($l) => ['id' => $l->id, 'title' => $l->title], $new),
            'waiting' => LoveLetter::where(fn ($q) => $q->whereNotNull('unlocked_at')->orWhere('unlock', 'anytime'))->whereNull('opened_at')->count(),
            'milestones' => Love::newMilestones(),
            'calendar' => Love::calendar(),
            'photo' => (bool) Setting::get('love_photo'),
        ]);
    }

    public function index(): JsonResponse
    {
        Love::unlockDue();

        return response()->json([
            'letters' => Love::letters(),
            'calendar' => Love::calendar(),
            'dates' => Love::dates(),
            'together_since' => Setting::get('love_together_since'),
            'photo' => (bool) Setting::get('love_photo'),
            'from' => Setting::get('love_from', ''),
        ]);
    }

    /** Open a letter (it must be unlocked, or an "anytime" one). */
    public function open(LoveLetter $letter): JsonResponse
    {
        if ($letter->unlock !== 'anytime' && ! $letter->unlocked_at) {
            return response()->json(['error' => 'Not yet 😉 '.Love::hint($letter).'.'], 423);
        }
        $letter->update(['opened_at' => $letter->opened_at ?? now(), 'unlocked_at' => $letter->unlocked_at ?? now()]);

        return response()->json(['letter' => ['id' => $letter->id, 'title' => $letter->title, 'body' => $letter->body, 'opened_at' => $letter->opened_at]]);
    }

    /** Today's jar question and the answered ones. */
    public function jar(): JsonResponse
    {
        return response()->json([
            'today' => Love::jarToday(),
            'past' => \Illuminate\Support\Facades\DB::table('memory_jar')->where('asked_on', '<', Love::jarToday()['asked_on'])
                ->where(fn ($q) => $q->whereNotNull('her_answer')->orWhereNotNull('his_answer'))->orderByDesc('asked_on')->limit(60)->get(),
            'her' => Setting::get('nickname') ?: explode(' ', (string) (Setting::get('creator_name') ?: 'Her'))[0],
            'him' => Setting::get('love_from') ?: 'Him',
        ]);
    }

    public function answerJar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['who' => ['required', Rule::in(['her', 'his'])], 'answer' => ['nullable', 'string', 'max:2000']]);
        $n = \Illuminate\Support\Facades\DB::table('memory_jar')->where('id', $id)->update([$data['who'].'_answer' => trim((string) ($data['answer'] ?? '')) ?: null, 'updated_at' => now()]);
        abort_unless($n, 404);

        return $this->jar();
    }

    // ---- the writer's desk (for him) ----

    public function show(LoveLetter $letter): JsonResponse
    {
        return response()->json(['letter' => $letter->makeVisible('body')]);
    }

    public function store(Request $request): JsonResponse
    {
        $letter = LoveLetter::create($this->validated($request));

        return response()->json(['letter' => ['id' => $letter->id]], 201);
    }

    public function update(Request $request, LoveLetter $letter): JsonResponse
    {
        $data = $this->validated($request);
        // Changing the rule re-seals a letter that hasn't been read yet.
        if (! $letter->opened_at && ($data['unlock'] !== $letter->unlock || ($data['unlock_value'] ?? null) !== $letter->unlock_value)) {
            $data['unlocked_at'] = null;
        }
        $letter->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroy(LoveLetter $letter): JsonResponse
    {
        $letter->delete();

        return response()->json(['ok' => true]);
    }

    public function saveDates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'together_since' => ['nullable', 'date'],
            'dates' => ['present', 'array', 'max:20'],
            'dates.*.label' => ['required', 'string', 'max:60'],
            'dates.*.date' => ['required', 'date'],
            'dates.*.kind' => ['required', Rule::in(['anniversary', 'birthday', 'other'])],
            'dates.*.yearly' => ['boolean'],
        ]);
        Setting::put('love_together_since', $data['together_since'] ?? null);
        Setting::put('love_dates', json_encode(array_map(fn ($d) => ['label' => trim($d['label']), 'date' => substr($d['date'], 0, 10), 'kind' => $d['kind'], 'yearly' => (bool) ($d['yearly'] ?? true)], $data['dates'])));

        return response()->json(['calendar' => Love::calendar(), 'dates' => Love::dates()]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);
        if ($old = Setting::get('love_photo')) {
            Storage::disk('local')->delete($old);
        }
        $path = $request->file('photo')->store('love', 'local');
        Setting::put('love_photo', $path);

        return response()->json(['ok' => true]);
    }

    public function photo(): Response
    {
        $path = Setting::get('love_photo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function deletePhoto(): JsonResponse
    {
        if ($old = Setting::get('love_photo')) {
            Storage::disk('local')->delete($old);
        }
        Setting::put('love_photo', null);

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000'],
            'unlock' => ['required', Rule::in(['anytime', 'date', 'followers', 'first_post', 'streak'])],
            'unlock_value' => ['nullable', 'string', 'max:60'],
        ]);
        $v = $data['unlock_value'] ?? null;
        $ok = match ($data['unlock']) {
            'date' => $v && strtotime($v) !== false,
            'followers' => (bool) preg_match('/^(linkedin|instagram|x|tiktok|threads|youtube):\d+$/', (string) $v),
            'streak' => ctype_digit((string) $v) && (int) $v > 0,
            default => true,
        };
        if (! $ok) {
            abort(response()->json(['error' => 'Pick when it opens.', 'message' => 'Pick when it opens.'], 422));
        }
        if ($data['unlock'] === 'date') {
            $data['unlock_value'] = date('Y-m-d', strtotime($v));
        }
        if (in_array($data['unlock'], ['anytime', 'first_post'], true)) {
            $data['unlock_value'] = null;
        }

        return $data;
    }
}
