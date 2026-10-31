<?php

namespace App\Domains\Landing;

use App\Models\Setting;

/**
 * The landing page's "Brand Partners & Stages" strip: brands she lists on her dashboard (names
 * only appear when she adds them, never pulled from her deals) followed by the stages from her
 * talks list, so a new talk shows up here by itself. She can hide single stages or all of them.
 */
class Partners
{
    public const SETTING = 'landing_partners';

    public const DEFAULTS = [
        'brands' => [['name' => 'IBM', 'url' => ''], ['name' => 'Adobe', 'url' => ''], ['name' => 'Airalo', 'url' => ''], ['name' => 'Temporal', 'url' => '']],
        'show_stages' => true,
        'hidden_stages' => [],
    ];

    public static function get(): array
    {
        $saved = json_decode((string) Setting::get(self::SETTING, '{}'), true);

        return array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
    }

    public static function save(array $data): array
    {
        $current = array_replace(self::get(), array_intersect_key($data, self::DEFAULTS));
        Setting::put(self::SETTING, json_encode($current));

        return $current;
    }

    /** Every stage from her talks (visible talks), newest first, without repeats. */
    public static function stages(): array
    {
        return Talk::ordered()->pluck('event')->map(fn ($e) => trim((string) $e))->filter()->unique()->values()->all();
    }

    /** The strip: brands first, then stages. */
    public static function items(): array
    {
        $p = self::get();
        $items = [];
        foreach ($p['brands'] as $b) {
            $name = trim((string) ($b['name'] ?? ''));
            if ($name !== '') {
                $items[] = ['name' => $name, 'kind' => 'brand', 'url' => trim((string) ($b['url'] ?? '')) ?: null];
            }
        }
        if ($p['show_stages']) {
            $brandNames = array_map('mb_strtolower', array_column($items, 'name'));
            foreach (self::stages() as $stage) {
                if (! in_array($stage, $p['hidden_stages'], true) && ! in_array(mb_strtolower($stage), $brandNames, true)) {
                    $items[] = ['name' => $stage, 'kind' => 'stage', 'url' => null];
                }
            }
        }

        return $items;
    }

    /** Her dashboard: the settings, every stage (hidden ones flagged) and what the strip shows. */
    public static function dashboard(): array
    {
        $p = self::get();

        return $p + [
            'stages' => array_map(fn ($s) => ['name' => $s, 'hidden' => in_array($s, $p['hidden_stages'], true)], self::stages()),
            'items' => self::items(),
        ];
    }
}
