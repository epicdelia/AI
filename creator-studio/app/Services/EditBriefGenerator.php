<?php

namespace App\Services;

use App\Models\Script;
use Carbon\CarbonImmutable;

class EditBriefGenerator
{
    /**
     * Generate the complete 1-Click Video Editor Handoff Pack.
     *
     * @return array{
     *   folder_name: string,
     *   folder_path: string,
     *   scaffolding: array<string>,
     *   markdown: string,
     *   visual_cues: array<array{time: string, line: int, text: string}>,
     *   target_minutes: float,
     *   ceiling_minutes: float,
     *   estimated_chapters: ?string,
     *   sponsor_info: ?array
     * }
     */
    public static function generate(Script $script, ?string $publishDate = null): array
    {
        $body = (string)($script->body ?? '');
        $structure = ScriptStructure::analyse($body);
        $totalSeconds = max(60, $structure['total_seconds'] ?? 180);
        $targetMin = round($totalSeconds / 60, 1);
        $ceilingMin = ceil($targetMin * 1.2);

        $pubDate = $publishDate
            ? CarbonImmutable::parse($publishDate)
            : CarbonImmutable::now()->next(CarbonImmutable::SUNDAY);

        $slug = \Illuminate\Support\Str::slug($script->title ?: 'untitled-video');
        $folderSlug = $pubDate->format('Y-m-d') . '_' . $slug;
        $folderPath = "YouTube/{$folderSlug}/";

        $roughDue = $pubDate->subDays(5)->format('M j, Y (D)');
        $finalDue = $pubDate->subDays(2)->format('M j, Y (D)');

        // Extract visual cues
        $visualCues = [];
        $lines = preg_split('/\R/u', $body);
        $wCount = 0;
        foreach ($lines as $idx => $line) {
            $lineTrim = trim($line);
            if (preg_match('/^#{1,6}\s+(.+)$/u', $lineTrim)) {
                continue;
            }
            if (preg_match_all('/\[(?:VISUAL|B-?ROLL|SCREEN|GRAPHIC|ON[- ]?SCREEN|SFX)\b([^\]]*)\]/i', $lineTrim, $matches)) {
                $elapsedSec = ScriptStructure::seconds($wCount);
                foreach ($matches[0] as $match) {
                    $visualCues[] = [
                        'time' => ScriptStructure::clock($elapsedSec),
                        'line' => $idx + 1,
                        'text' => trim($match),
                    ];
                }
            }
            $wCount += str_word_count(strip_tags($lineTrim));
        }

        // Linked deal / sponsor
        $sponsorInfo = null;
        $deal = $script->deals()->first();
        if ($deal) {
            $sponsorInfo = [
                'brand_name' => $deal->brand_name,
                'rate' => $deal->rate,
                'requirements' => is_array($deal->deliverables) ? implode(', ', $deal->deliverables) : 'Dedicated integration segment',
            ];
        }

        // Subdirectories per workflow.md
        $scaffolding = [
            "{$folderPath}01_script/",
            "{$folderPath}02_raw/",
            "{$folderPath}03_assets/",
            "{$folderPath}04_edits/",
            "{$folderPath}05_final/",
            "{$folderPath}06_thumbnail/",
        ];

        // Format Markdown brief
        $shotListMd = empty($visualCues)
            ? "- No explicit [VISUAL] tags detected in script. Please follow chapter flow.\n"
            : implode("\n", array_map(fn ($c) => "- **{$c['time']}** (Line {$c['line']}): `{$c['text']}`", array_slice($visualCues, 0, 40))) . "\n";

        $sponsorMd = $sponsorInfo
            ? "Segment: Mid-roll (~02:30)\nBrand: {$sponsorInfo['brand_name']}\nDeliverable Notes: {$sponsorInfo['requirements']}\nBrand review needed before publish: Yes"
            : "No paid sponsor attached to this upload.";

        $md = <<<MARKDOWN
# Edit brief: {$script->title}

**Publish date:** {$pubDate->format('F j, Y (D)')}
**Folder:** {$folderPath}
**Target length:** {$targetMin} min (hard ceiling {$ceilingMin} min)
**Rough cut due:** {$roughDue}  **Final due:** {$finalDue}

## The one-sentence promise
{$script->hook}

## Title + thumbnail (so the cold open matches)
Title: {$script->title}
Thumbnail text: (from 06_thumbnail/)
**The first 30 seconds must pay this off by showing:** Cold open hook & immediate demonstration of value.

## Reference videos (pacing and style to copy)
1. Tech Unicorn style: Fast cuts, crisp zoom-ins on code, terminal popups, high audio fidelity.
2. Avoid: Overused meme sound effects, spinning 3D transitions, low-contrast text.

## Shot list
The `[VISUAL]` / `[B-ROLL]` notes extracted from `01_script/final_script.md`:
{$shotListMd}
## Must cut
- Flubs and retakes (I say "again" or "take two" before a retake)
- Anything after I say "cut that"
- Silence gaps longer than 1.2s on talking head segments

## Must keep
- Authentic reactions, terminal live coding executions, and raw bug fixes
- Personal stories and engineering insights

## Sponsor / disclosure
{$sponsorMd}

## Deliverables & SLA (Strict 2-round review limit)
- [ ] Rough cut v1 (structure and pacing only, zero polish) due {$roughDue}
- [ ] Polished v2 (graphics, zooms, sound, captions) due {$finalDue}
- [ ] Final 4K export (H.264 / ProRes) in `05_final/`
- [ ] Captions file (`captions.srt`)
- [ ] YouTube chapter timestamps list
- [ ] 3 vertical Shorts (9:16) with burned-in dynamic captions
MARKDOWN;

        return [
            'folder_name' => $folderSlug,
            'folder_path' => $folderPath,
            'scaffolding' => $scaffolding,
            'markdown' => $md,
            'visual_cues' => $visualCues,
            'target_minutes' => $targetMin,
            'ceiling_minutes' => $ceilingMin,
            'estimated_chapters' => $structure['chapters'] ?? null,
            'sponsor_info' => $sponsorInfo,
        ];
    }
}

