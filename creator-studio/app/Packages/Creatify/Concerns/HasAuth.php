<?php

namespace App\Packages\Creatify\Concerns;

trait HasAuth
{
    /**
     * API ID
     */
    private string $X_API_ID;

    /**
     * API Key
     */
    private string $X_API_KEY;

    /**
     * return auth header
     */
    public function authHeader(): array
    {
        return [
            'X-API-ID'  => $this->X_API_ID,
            'X-API-KEY' => $this->X_API_KEY,
        ];
    }

    /**
     * make sure if auth is provided or not
     */
    public function ensureAuth(): bool
    {
        return ! (empty($this->X_API_ID) || empty($this->X_API_KEY));
    }

    /**
     * set the auth from parameter
     */
    public function authFrom(string $x_api_id, string $x_api_key): void
    {
        $this->X_API_ID = $x_api_id;
        $this->X_API_KEY = $x_api_key;
    }
}
