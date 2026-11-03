<?php

namespace App\Packages\FalAI\Concerns;

trait HasAuth
{
    /**
     * API ID
     */
    private string $FAL_KEY;

    /**
     * return auth header
     */
    public function authHeader(): array
    {
        return [
            'Authorization' => 'Key ' . $this->FAL_KEY,
        ];
    }

    /**
     * make sure if auth is provided or not
     */
    public function ensureAuth(): bool
    {
        return ! empty($this->FAL_KEY);
    }

    /**
     * set the auth from parameter
     */
    public function authFrom(string $falKey): void
    {
        $this->FAL_KEY = $falKey;
    }
}
