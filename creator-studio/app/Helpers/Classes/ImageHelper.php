<?php

namespace App\Helpers\Classes;

use App\Extensions\AdvancedImage\System\Helpers\Tool;

class ImageHelper
{
    public static function toolShow(string $action): array
    {
        if (MarketplaceHelper::isRegistered('advanced-image')) {

            $tools = collect(Tool::get());

            if ($tools->where('action', $action)) {
                return [
                    'show' => true,
                    'link' => route('dashboard.user.advanced-image.index', ['action' => $action]),
                ];
            }
        }

        if (MarketplaceHelper::isRegistered('photo-studio') && setting('photo_studio', 1) == 1 && $action !== 'sketch_to_image') {
            return [
                'show' => true,
                'link' => route('dashboard.user.photo-studio.index', ['action' => $action]),
            ];
        }

        return [
            'link' => null,
            'show' => false,
        ];
    }
}
