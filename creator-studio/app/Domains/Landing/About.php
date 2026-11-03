<?php

namespace App\Domains\Landing;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The landing page's "01: About": her words (edited on the dashboard's "About you" card) plus facts
 * the studio already knows, so the section never contradicts the rest of the page: her audience
 * from Public reach, her latest talk from Speaking, and whether a build slot is open.
 */
class About
{
    public const SETTING = 'landing_about';

    public const PORTRAIT_DEFAULT = '/images/549290582_18080889986298812_7772569637968987998_n.jpg';

    public const DEFAULTS = [
        'headline' => 'Engineer first. Storyteller always.',
        'bio' => [
            'I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it was not for them.',
        ],
        'thesis' => 'Tech feels scary because it is explained badly.',
        'thesis_detail' => 'Whether it is a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.',
        'now' => 'Building AI-powered apps and explaining them on camera',
        'location' => '',
        'countries' => 69,
        'story_url' => 'https://linkedin.com/in/delialazarescu',
        'milestones' => [
            [
                'label' => 'Self-taught engineer',
                'detail' => 'Learned to code on her own and got hired as a software engineer.',
            ],
            [
                'label' => 'Google',
                'detail' => 'Wrote production code at Google.',
            ],
            [
                'label' => 'Independent founder',
                'detail' => 'Left Big Tech to build AI-powered products for clients, from anywhere.',
            ],
            [
                'label' => 'Creator & speaker',
                'detail' => 'Explains tech to a large community and on stage.',
            ],
        ],
        'portrait' => null, // null = the bundled photo; otherwise an uploaded file under about/
    ];

    /**
     * Her saved copy over the defaults.
     */
    public static function get(): array
    {
        $saved = json_decode((string)Setting::get(self::SETTING, '{}'), true);

        return array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
    }

    public static function save(array $data): array
    {
        $about = array_replace(self::get(), array_intersect_key($data, self::DEFAULTS));
        Setting::put(self::SETTING, json_encode($about));

        return $about;
    }

    public static function portraitUrl(array $about): string
    {
        return $about['portrait'] && Storage::disk('local')->exists($about['portrait'])
            ? '/landing/about-portrait?v=' . substr(md5($about['portrait']), 0, 8)
            : self::PORTRAIT_DEFAULT;
    }

    /**
     * The public section: her words plus live facts.
     */
    public static function publicSection(): array
    {
        $about = self::get();
        $talks = Talk::ordered();
        $latest = $talks->filter(fn ($t) => $t->date)->sortByDesc('date')->first() ?? $talks->first();
        $availability = ClientJourney::availability();

        return [
            'headline' => $about['headline'],
            'bio' => array_values(array_filter($about['bio'], fn ($p) => trim((string)$p) !== '')),
            'thesis' => $about['thesis'],
            'thesis_detail' => $about['thesis_detail'],
            'now' => $about['now'],
            'location' => $about['location'],
            'story_url' => $about['story_url'],
            'milestones' => array_values(array_filter($about['milestones'], fn ($m) => trim((string)($m['label'] ?? '')) !== '')),
            'portrait_url' => self::portraitUrl($about),
            'facts' => [
                'countries' => (int)$about['countries'],
                'audience' => Audience::headline(),
                'networks' => count(array_filter(Audience::reach()['networks'], fn ($n) => !$n['hidden'])),
                'talks' => $talks->count(),
                'latest_talk' => $latest ? [
                    'event' => $latest->event,
                    'kind' => $latest->kind_label,
                    'date' => $latest->date?->format('Y-m-d'),
                    'upcoming' => $latest->upcoming,
                ] : null,
                'availability' => $availability['show'] ? [
                    'status' => $availability['status'],
                    'label' => $availability['label'],
                ] : null,
            ],
        ];
    }
}
