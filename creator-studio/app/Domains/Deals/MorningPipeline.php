<?php

namespace App\Domains\Deals;

use App\Domains\Agents\Models\AgentEvent;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The 08:15 pipeline briefing (studio:pipeline): a prep brief for each intro call in the next
 * 24 hours that doesn't have one yet (when an AI key is set), and one notification with today's
 * calls and the follow-ups due. Once a day; nothing is sent to anyone.
 */
class MorningPipeline
{
    public function __construct(private CallPrep $prep) {}

    /** @return array{prepped: int, calls: int, follow_ups: int, message: ?string} */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $tz = Setting::get('timezone') ?: 'UTC';
        $calls = Deal::whereNotNull('meeting_at')->whereBetween('meeting_at', [$now, $now->addDay()])->whereNotIn('stage', ['paid', 'lost'])->orderBy('meeting_at')->get();

        $prepped = 0;
        if (Llm::configured()) {
            foreach ($calls->filter(fn ($d) => ! $d->prep_at || $d->prep_at->lt($now->subDays(3))) as $deal) {
                try {
                    $this->prep->brief($deal);
                    $prepped++;
                } catch (Throwable) {
                    // one failed brief never stops the others
                }
            }
        }

        $due = FollowUps::due();
        $day = $now->setTimezone($tz)->toDateString();
        $message = null;
        if (($calls->isNotEmpty() || $due->isNotEmpty()) && Setting::get('pipeline_briefed_on') !== $day) {
            $parts = [];
            if ($calls->isNotEmpty()) {
                $parts[] = ($calls->count() === 1 ? '1 intro call' : "{$calls->count()} intro calls").': '
                    .$calls->map(fn ($d) => "{$d->brand_name} at ".$d->meeting_at->setTimezone($tz)->format('H:i'))->implode(', ')
                    .($prepped ? ' (prep briefs ready in Deals)' : '');
            }
            if ($due->isNotEmpty()) {
                $money = $due->sum(fn ($r) => (float) $r['deal']->rate);
                $parts[] = "{$due->count()} follow-up".($due->count() === 1 ? '' : 's').' due'.($money > 0 ? ' ($'.number_format($money).' in play)' : '')
                    .': '.$due->take(3)->map(fn ($r) => $r['deal']->brand_name)->implode(', ').($due->count() > 3 ? '…' : '');
            }
            $message = 'Today: '.implode('. ', $parts).'.';
            AgentEvent::log('lead', $message);
            Setting::put('pipeline_briefed_on', $day);
        }

        return ['prepped' => $prepped, 'calls' => $calls->count(), 'follow_ups' => $due->count(), 'message' => $message];
    }
}
