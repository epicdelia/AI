<?php

namespace App\Domains\Landing;

use App\Models\Setting;

/**
 * The moving band above the landing page footer: her phrases (each jumps to a section), mixed with
 * live facts the studio already knows (audience, open build slots, her next or latest talk, her
 * latest video, countries). She edits the phrases, switches the live facts and sets the speed on
 * the dashboard's "Marquee" card.
 */
class Marquee
{
    public const SETTING = 'landing_marquee';

    /**
     * Sections a phrase can jump to.
     */
    public const TARGETS = [
        'services' => 'Services',
        'youtube' => 'Content',
        'process' => 'How it works',
        'pricing' => 'Pricing',
        'reach' => 'Reach',
        'speaking' => 'On Stage',
        'about' => 'About',
        'contact' => 'Contact',
    ];

    public const SPEEDS = [
        'slow' => 60,
        'normal' => 40,
        'fast' => 26,
    ]; // seconds per loop

    public const DEFAULTS = [
        'items' => [
            [
                'label' => 'Vibecoded Apps',
                'target' => 'services',
            ],
            [
                'label' => 'Content Creation',
                'target' => 'youtube',
            ],
            [
                'label' => 'Speaking & Keynotes',
                'target' => 'speaking',
            ],
            [
                'label' => 'AI Integration',
                'target' => 'services',
            ],
            [
                'label' => 'Brand Partnerships',
                'target' => 'pricing',
            ],
            [
                'label' => 'Technical Advisory',
                'target' => 'pricing',
            ],
        ],
        'show_live' => true,
        'speed' => 'normal',
    ];

    public static function get(): array
    {
        $saved = json_decode((string)Setting::get(self::SETTING, '{}'), true);

        return array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
    }

    public static function save(array $data): array
    {
        $m = array_replace(self::get(), array_intersect_key($data, self::DEFAULTS));
        Setting::put(self::SETTING, json_encode($m));

        return $m;
    }

    /**
     * Facts from her own data, each pointing at the section that backs it up.
     */
    public static function liveFacts(): array
    {
        $facts = [[
            'label' => Audience::headline() . ' people follow along',
            'target' => 'reach',
            'icon' => 'users',
        ]];

        $a = ClientJourney::availability();
        if ($a['show']) {
            $facts[] = [
                'label' => $a['label'],
                'target' => 'process',
                'icon' => $a['status'] === 'open' ? 'zap' : 'clock',
                'status' => $a['status'],
            ];
        }

        $talks = Talk::ordered();
        $next = $talks->filter->upcoming->sortBy('date')->first();
        $latest = $next ?? $talks->filter(fn ($t) => $t->date)->sortByDesc('date')->first();
        if ($latest) {
            $facts[] = [
                'label' => ($next ? 'Next on stage: ' : 'Latest talk: ') . $latest->event,
                'target' => 'speaking',
                'icon' => 'mic',
            ];
        }

        $video = rescue(fn () => LandingFeed::get()['platforms']['youtube'][0] ?? null, null, false);
        if ($video && (LandingFeed::get()['sources']['youtube'] ?? '') === 'live') {
            $facts[] = [
                'label' => 'New video: ' . mb_strimwidth($video['title'], 0, 60, '…'),
                'target' => 'youtube',
                'icon' => 'play',
            ];
        }

        $countries = (int)(About::get()['countries'] ?? 0);
        if ($countries) {
            $facts[] = [
                'label' => "Shipped from {$countries} countries",
                'target' => 'about',
                'icon' => 'globe',
            ];
        }

        return $facts;
    }

    /**
     * What the band shows: her phrases with a live fact after every second one.
     */
    public static function items(): array
    {
        $m = self::get();
        $phrases = array_values(array_filter(array_map(fn ($i) => [
            'label' => trim((string)($i['label'] ?? '')),
            'target' => $i['target'] ?? 'services',
            'kind' => 'phrase',
        ], $m['items']), fn ($i) => $i['label'] !== ''));
        $facts = $m['show_live'] ? array_map(fn ($f) => $f + [
            'kind' => 'live',
        ], self::liveFacts()) : [];

        $out = [];
        foreach ($phrases as $i => $p) {
            $out[] = $p;
            if ($i % 2 === 1 && $facts) {
                $out[] = array_shift($facts);
            }
        }

        return array_merge($out, $facts);
    }

    public static function publicData(): array
    {
        $m = self::get();

        return [
            'items' => self::items(),
            'duration' => self::SPEEDS[$m['speed']] ?? self::SPEEDS['normal'],
        ];
    }

    public static function dashboard(): array
    {
        return self::get() + [
            'targets' => self::TARGETS,
            'speeds' => array_keys(self::SPEEDS),
            'live' => self::liveFacts(),
            'preview' => self::items(),
        ];
    }
}
