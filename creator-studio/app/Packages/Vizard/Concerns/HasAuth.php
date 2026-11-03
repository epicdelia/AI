<?php

namespace App\Packages\Vizard\Concerns;

trait HasAuth
{
    /**
     * API KEY
     */
    private string $API_KEY;

    /**
     * make sure if auth is provided or not
     */
    public function ensureAuth(): bool
    {
        return ! empty($this->API_KEY);
    }

    /**
     * set the auth from parameter
     */
    public function authFrom(string $apiKey): void
    {
        $this->API_KEY = $apiKey;
    }

    /**
     * return auth header
     */
    public function authHeader(): array
    {
        return [
            'VIZARDAI_API_KEY' => $this->API_KEY,
        ];
    }
}
