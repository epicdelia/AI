<?php

namespace App\Packages\Elevenlabs\Concerns;

trait HasAuth
{
    /**
     * API ID
     */
    private string $ELEVENLABS_KEY;

    /**
     * return auth header
     */
    public function authHeader(): array
    {
        return [
            'xi-api-key' => $this->ELEVENLABS_KEY,
        ];
    }

    /**
     * make sure if auth is provided or not
     */
    public function ensureAuth(): bool
    {
        return ! empty($this->ELEVENLABS_KEY);
    }

    /**
     * set the auth from parameter
     */
    public function authFrom(string $elevenlabsKey): void
    {
        $this->ELEVENLABS_KEY = $elevenlabsKey;
    }
}
