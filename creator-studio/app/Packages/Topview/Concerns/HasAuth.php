<?php

namespace App\Packages\Topview\Concerns;

trait HasAuth
{
    /**
     * API ID
     */
    private string $TOPVIEW_API_KEY;

    /**
     * API Key
     */
    private string $TOPVIEW_UID;

    /**
     * return auth header
     */
    public function authHeader(): array
    {
        return [
            'Topview-Uid' => $this->TOPVIEW_UID,
        ];
    }

    /**return topview api key to use on auth header */
    public function authToken(): string
    {
        return $this->TOPVIEW_API_KEY;
    }

    /**
     * make sure if auth is provided or not
     */
    public function ensureAuth(): bool
    {
        return ! (empty($this->TOPVIEW_API_KEY) || empty($this->TOPVIEW_UID));
    }

    /**
     * set the auth from parameter
     */
    public function authFrom(string $topwiew_uid, string $topview_api_key): void
    {
        $this->TOPVIEW_UID = $topwiew_uid;
        $this->TOPVIEW_API_KEY = $topview_api_key;
    }
}
