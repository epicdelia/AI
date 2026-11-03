<?php

namespace MagicAI\Healthy\Repositories;

use App\Domains\Marketplace\MarketplaceServiceProvider;
use App\Helpers\Classes\Helper;
use MagicAI\Healthy\Services\ExtensionValidationService;

class ExtensionRepository
{
    public function __construct(
        private ExtensionValidationService $validationService
    ) {}

    public function checkSelectedExtensions(array $extensions): array
    {

        $regExtensions = $this->getRegisteredExtensions();

        $results = [];

        foreach ($extensions as $slug) {

            if (Helper::appIsDemo()) {
                $results[$slug] = [
                    'status'  => 'success',
                    'message' => 'Demo mode: License validation is not applicable.',
                ];

                continue;
            }

            if (array_key_exists($slug, $regExtensions)) {
                $results[$slug] = $this->validationService->validateSingle($slug);
            } else {
                $results[$slug] = [
                    'status'  => 'error',
                    'message' => "Extension '$slug' is not registered.",
                ];
            }
        }

        return $results;
    }

    public function checkAllRegisteredExtensions(): array
    {
        $registeredExtensions = $this->getRegisteredExtensions();
        $results = [];

        foreach ($registeredExtensions as $slug => $providerClass) {
            if (Helper::appIsDemo()) {
                $results[$slug] = [
                    'status'  => 'success',
                    'message' => 'Demo mode: License validation is not applicable.',
                ];

                continue;
            }
            $results[$slug] = $this->validationService->validateSingle($slug);
        }

        return $results;
    }

    public function getRegisteredExtensions(): array
    {
        $providers = MarketplaceServiceProvider::getExtensionProviders();
        $loadedProviders = app()->getLoadedProviders();

        return array_filter($providers, function ($provider) use ($loadedProviders) {
            return array_key_exists($provider, $loadedProviders);
        });
    }
}
