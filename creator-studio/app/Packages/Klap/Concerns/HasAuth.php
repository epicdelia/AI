<?php

namespace App\Packages\Klap\Concerns;

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
}
