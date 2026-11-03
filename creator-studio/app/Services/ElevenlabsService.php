<?php

namespace App\Services;

use App\Models\SettingTwo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ElevenlabsService
{
    public const URL = 'https://api.elevenlabs.io/v1/voices';

    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = SettingTwo::getCache()?->elevenlabs_api_key;
    }

    public function getVoices(): array|Collection
    {
        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
        ])->timeout(30)
            ->get(self::URL);

        if ($response->failed()) {
            return [];
        }

        $data = $response->json();

        return collect($data['voices'])->map(function ($voice) {
            $labels = $voice['labels'] ?? [];

            return [
                'voice_id'    => $voice['voice_id'],
                'name'        => $voice['name'],
                'preview_url' => $voice['preview_url'] ?? null,
                'category'    => $voice['category'] ?? null,
                'description' => $voice['description'] ?? null,
                'image_url'   => $voice['image_url'] ?? null,
                'language'    => $labels['language'] ?? null,
                'gender'      => $labels['gender'] ?? null,
                'age'         => $labels['age'] ?? null,
                'accent'      => $labels['accent'] ?? null,
                'use_case'    => $labels['use_case'] ?? ($labels['use case'] ?? null),
                'descriptive' => $labels['descriptive'] ?? null,
            ];
        });
    }
}
