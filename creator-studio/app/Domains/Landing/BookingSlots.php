<?php

namespace App\Domains\Landing;

use App\Models\Deal;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Real intro-call times for the landing page: her working days and hours (in her timezone),
 * at least `notice_hours` ahead and up to `horizon_days` out, minus calls already requested.
 * Off until she turns it on; the booking form then falls back to "preferred date and time".
 */
class BookingSlots
{
    public const SETTING = 'booking_hours';

    public const DEFAULTS = [
        'enabled' => false,
        'days' => [1, 2, 3, 4, 5], // ISO weekdays, Monday = 1
        'start' => '10:00',
        'end' => '16:00',
        'minutes' => 30,
        'notice_hours' => 24,
        'horizon_days' => 14,
    ];

    public static function config(): array
    {
        $saved = json_decode((string) Setting::get(self::SETTING, '{}'), true);

        return array_merge(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : [])
            + ['timezone' => Setting::get('timezone') ?: 'UTC'];
    }

    public static function save(array $c): array
    {
        $clean = [
            'enabled' => (bool) ($c['enabled'] ?? false),
            'days' => array_values(array_unique(array_filter(array_map('intval', (array) ($c['days'] ?? [])), fn ($d) => $d >= 1 && $d <= 7))),
            'start' => self::hhmm($c['start'] ?? null, self::DEFAULTS['start']),
            'end' => self::hhmm($c['end'] ?? null, self::DEFAULTS['end']),
            'minutes' => in_array((int) ($c['minutes'] ?? 30), [15, 20, 30, 45, 60], true) ? (int) $c['minutes'] : 30,
            'notice_hours' => max(0, min(168, (int) ($c['notice_hours'] ?? 24))),
            'horizon_days' => max(1, min(60, (int) ($c['horizon_days'] ?? 14))),
        ];
        if ($clean['end'] <= $clean['start']) {
            $clean['end'] = self::DEFAULTS['end'] > $clean['start'] ? self::DEFAULTS['end'] : '23:30';
        }
        Setting::put(self::SETTING, json_encode($clean));

        return self::config();
    }

    /** @return array{enabled: bool, timezone: string, minutes: int, days: list<array{date: string, slots: list<string>}>} */
    public static function open(?CarbonImmutable $now = null): array
    {
        $c = self::config();
        $out = ['enabled' => $c['enabled'], 'timezone' => $c['timezone'], 'minutes' => $c['minutes'], 'days' => []];
        if (! $c['enabled'] || ! $c['days']) {
            return $out;
        }
        $now ??= CarbonImmutable::now();
        $earliest = $now->addHours($c['notice_hours']);
        $taken = Deal::whereNotNull('meeting_at')->where('meeting_at', '>=', $now->subDay())
            ->pluck('meeting_at')->map(fn ($t) => $t->getTimestamp())->flip();

        $day = $now->setTimezone($c['timezone'])->startOfDay();
        for ($i = 0; $i <= $c['horizon_days']; $i++, $day = $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $c['days'], true)) {
                continue;
            }
            [$sh, $sm] = array_map('intval', explode(':', $c['start']));
            [$eh, $em] = array_map('intval', explode(':', $c['end']));
            $slot = $day->setTime($sh, $sm);
            $end = $day->setTime($eh, $em);
            $slots = [];
            for (; $slot->addMinutes($c['minutes']) <= $end; $slot = $slot->addMinutes($c['minutes'])) {
                if ($slot >= $earliest && ! isset($taken[$slot->getTimestamp()])) {
                    $slots[] = $slot->utc()->toIso8601ZuluString();
                }
            }
            if ($slots) {
                $out['days'][] = ['date' => $day->toDateString(), 'slots' => $slots];
            }
        }

        return $out;
    }

    public static function isOpen(CarbonImmutable $at): bool
    {
        $iso = $at->utc()->toIso8601ZuluString();

        return collect(self::open()['days'])->contains(fn ($d) => in_array($iso, $d['slots'], true));
    }

    private static function hhmm(mixed $v, string $default): string
    {
        return is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : $default;
    }
}
