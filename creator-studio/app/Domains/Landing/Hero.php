<?php

namespace App\Domains\Landing;

use App\Models\Setting;

/**
 * The landing page hero: her headline, intro and buttons (edited on the dashboard's "Hero" card)
 * with {countries} and {audience} filled from her real numbers, a status pill from About you and
 * Client journey, and four stats she picks; the live ones come from the same data as the rest of
 * the page, so the hero never contradicts the sections below it.
 */
class Hero
{
    public const SETTING = 'landing_hero';

    /**
     * Stats she can show (pick four): number, label, the section that backs it, and whether it's live.
     */
    public const STATS = ['audience', 'countries', 'mvp', 'google', 'talks', 'slots', 'networks'];

    public const DEFAULTS = [
        'headline' => 'Making tech *less scary*: built from beach cafés in {countries} countries.',
        'lede' => 'I am a software engineer who left Big Tech to build apps, create content for {audience} people, and prove you do not need a hoodie and a standing desk to ship great software.',
        'reassurance' => '', // optional line under the buttons; empty shows nothing
        'cta' => 'Book an Intro Call',
        'show_status' => true,
        'stats' => ['audience', 'countries', 'mvp', 'google'],
    ];

    public static function get(): array
    {
        $saved = json_decode((string)Setting::get(self::SETTING, '{}'), true);

        return array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
    }

    public static function save(array $data): array
    {
        $h = array_replace(self::get(), array_intersect_key($data, self::DEFAULTS));
        Setting::put(self::SETTING, json_encode($h));

        return $h;
    }

    /**
     * Every stat with its current value.
     */
    public static function statCatalog(): array
    {
        $about = About::get();
        $reach = Audience::reach();
        $talks = Talk::ordered();
        $a = ClientJourney::availability();
        $visibleNetworks = count(array_filter($reach['networks'], fn ($n) => !$n['hidden']));

        return [
            'audience' => [
                'value' => Audience::headline(),
                'count' => Audience::total(),
                'label' => 'Combined audience across platforms',
                'target' => 'reach',
                'live' => true,
            ],
            'countries' => [
                'value' => (string)$about['countries'],
                'count' => (int)$about['countries'],
                'label' => 'Countries traveled and worked from',
                'target' => 'about',
                'live' => false,
            ],
            'mvp' => [
                'value' => '2-4',
                'unit' => 'wks',
                'label' => 'Idea to shipped production MVP',
                'target' => 'process',
                'live' => false,
            ],
            'google' => [
                'value' => 'ex-G',
                'label' => 'Google engineering background',
                'target' => 'about',
                'live' => false,
            ],
            'talks' => [
                'value' => (string)$talks->count(),
                'count' => $talks->count(),
                'label' => $talks->count() === 1 ? 'Talk on stages worldwide' : 'Talks on stages worldwide',
                'target' => 'speaking',
                'live' => true,
            ],
            'slots' => $a['show']
                ? [
                    'value' => (string)$a['open'],
                    'count' => $a['open'],
                    'label' => $a['open'] === 1 ? 'Build slot open right now' : ($a['open'] ? 'Build slots open right now' : 'Builds in progress: join the waitlist'),
                    'target' => 'pricing',
                    'live' => true,
                    'status' => $a['status'],
                ]
                : null,
            'networks' => [
                'value' => (string)$visibleNetworks,
                'count' => $visibleNetworks,
                'label' => 'Networks where she publishes',
                'target' => 'reach',
                'live' => true,
            ],
        ];
    }

    /**
     * {countries} and {audience} from her real numbers.
     */
    public static function fill(string $text): string
    {
        return strtr($text, [
            '{countries}' => (string)About::get()['countries'],
            '{audience}' => Audience::headline(),
        ]);
    }

    public static function publicSection(): array
    {
        $h = self::get();
        $catalog = self::statCatalog();
        $stats = [];
        foreach ($h['stats'] as $key) {
            if (!empty($catalog[$key])) {
                $stats[] = [
                    'key' => $key,
                ] + $catalog[$key];
            }
        }
        // Always four tiles: top up with the defaults if a picked one isn't available (e.g. slots hidden).
        foreach (self::DEFAULTS['stats'] as $key) {
            if (count($stats) >= 4) {
                break;
            }
            if (!in_array($key, array_column($stats, 'key'), true)) {
                $stats[] = [
                    'key' => $key,
                ] + $catalog[$key];
            }
        }

        $about = About::get();
        $a = ClientJourney::availability();
        $status = null;
        if ($h['show_status']) {
            $parts = array_filter([
                $a['show'] ? ($a['status'] === 'open' ? 'Booking new projects' : 'Fully booked: join the waitlist') : null,
                trim((string)$about['location']) ? 'Now in ' . trim($about['location']) : null,
            ]);
            if ($parts) {
                $status = [
                    'text' => implode(' · ', $parts),
                    'state' => $a['show'] ? $a['status'] : 'info',
                ];
            }
        }

        return [
            'headline' => self::fill($h['headline']),
            'lede' => self::fill($h['lede']),
            'reassurance' => $h['reassurance'],
            'cta' => $h['cta'] ?: self::DEFAULTS['cta'],
            'status' => $status,
            'stats' => array_slice($stats, 0, 4),
        ];
    }

    public static function dashboard(): array
    {
        $catalog = self::statCatalog();

        return self::get() + [
            'catalog' => array_map(fn ($k) => [
                'key' => $k,
                'label' => $catalog[$k]['label'] ?? 'Build slots (hidden: no capacity set)',
                'value' => $catalog[$k]['value'] ?? '-',
                'live' => $catalog[$k]['live'] ?? true,
                'available' => !empty($catalog[$k]),
            ], self::STATS),
            'preview' => self::publicSection(),
        ];
    }
}
