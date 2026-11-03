<?php

namespace App\Domains\Clients;

use App\Http\Controllers\Api\BookingController;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * The questions a client answers when they ask for something, per service (her own list,
 * or a sensible default). Answers are saved on the deal (deals.intake) so the brief is
 * complete before the first call.
 */
class Intake
{
    public const TYPES = ['text', 'textarea', 'url', 'select', 'date'];

    /**
     * Used for any service she hasn't customised. All optional: she makes questions required.
     */
    public const DEFAULT = [
        ['key' => 'goal', 'label' => 'What should this achieve?', 'type' => 'textarea', 'required' => false],
        ['key' => 'audience', 'label' => 'Who is it for?', 'type' => 'text', 'required' => false],
        ['key' => 'links', 'label' => 'Links that help (website, examples, brief)', 'type' => 'textarea', 'required' => false],
        ['key' => 'success', 'label' => 'How will you know it worked?', 'type' => 'text', 'required' => false],
    ];

    /**
     * @return array<string, array<int, array>> keyed by service ("*" = default)
     */
    public static function all(): array
    {
        $saved = json_decode((string)Setting::get('intake_questions', ''), true);

        return is_array($saved) ? $saved : [];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool, options?: array<int, string>}>
     */
    public static function questionsFor(?string $service): array
    {
        $all = self::all();

        return $all[$service ?? ''] ?? $all['*'] ?? self::DEFAULT;
    }

    /**
     * Validate and save her questions for one service ("*" for the default).
     */
    public static function save(string $service, array $questions): array
    {
        if ($service !== '*' && !array_key_exists($service, BookingController::PROJECT_LABELS)) {
            throw ValidationException::withMessages(['service' => 'Unknown service.']);
        }
        $clean = [];
        foreach (array_slice($questions, 0, 15) as $i => $q) {
            $label = trim((string)($q['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = in_array($q['type'] ?? 'text', self::TYPES, true) ? $q['type'] : 'text';
            $item = [
                'key' => preg_replace('/[^a-z0-9_]/', '', strtolower((string)($q['key'] ?? ''))) ?: 'q' . ($i + 1),
                'label' => mb_substr($label, 0, 200),
                'type' => $type,
                'required' => (bool)($q['required'] ?? false),
            ];
            if ($type === 'select') {
                $item['options'] = array_values(array_slice(array_filter(array_map(fn ($o) => mb_substr(trim((string)$o), 0, 100), (array)($q['options'] ?? []))), 0, 20));
            }
            $clean[] = $item;
        }
        $all = self::all();
        $all[$service] = $clean;
        Setting::put('intake_questions', json_encode($all));

        return $clean;
    }

    /**
     * Check answers against the questions; returns [{key, label, answer}] to store.
     *
     * @return array<int, array{key: string, label: string, answer: string}>
     */
    public static function answers(?string $service, array $input): array
    {
        $out = [];
        $errors = [];
        foreach (self::questionsFor($service) as $q) {
            $v = trim((string)($input[$q['key']] ?? ''));
            if ($q['required'] && $v === '') {
                $errors["intake.{$q['key']}"] = "Please answer: {$q['label']}";
                continue;
            }
            if ($v !== '' && $q['type'] === 'url' && !filter_var($v, FILTER_VALIDATE_URL)) {
                $errors["intake.{$q['key']}"] = "{$q['label']}: enter a full link (https://…).";
                continue;
            }
            if ($v !== '' && $q['type'] === 'select' && !in_array($v, $q['options'] ?? [], true)) {
                $errors["intake.{$q['key']}"] = "{$q['label']}: pick one of the options.";
                continue;
            }
            $out[] = ['key' => $q['key'], 'label' => $q['label'], 'answer' => mb_substr($v, 0, 4000)];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
