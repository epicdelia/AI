<?php

namespace App\Services\Ai\OpenAI\Image;

use App\Helpers\Classes\Helper;
use Illuminate\Support\Facades\Http;

class CreateImageVariationService
{
    private string $generateURL = 'https://api.openai.com/v1/images/variations';

    private string $model = 'gpt-image-1';

    private string $size = '1024x1024';

    private string $image;

    public function generate(): array
    {
        $requestData = $this->requestData();

        $httpClient = Http::withToken(Helper::setOpenAiKey())
            ->asMultipart()
            ->post($this->generateURL, $requestData);

        if ($httpClient->failed()) {
            return [
                'status'  => false,
                'message' => $httpClient->json('error.message'),
            ];
        }

        if ($httpClient->json('created') && $httpClient->json('data')) {
            $data = $httpClient->json('data');

        }

        return [
            'status'  => false,
            'message' => 'Image generation failed. Please try again later.',
        ];
    }

    public function requestData(): array
    {
        return [
            ['name' => 'model', 'contents' => $this->getModel()],
            ['name' => 'size', 'contents' => $this->getSize()],
            ['name' => 'n', 'contents' => 1],
            ['name' => 'response_format', 'contents' => 'b64_json'],
            [
                'name'     => 'image',
                'contents' => fopen(public_path($this->getImage()), 'rb'),
                'filename' => basename($this->getImage()),
            ],
        ];
    }

    public function setModel(string $model): CreateImageVariationService
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setSize(string $size): CreateImageVariationService
    {
        $this->size = $size;

        return $this;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * @param  mixed  $image
     *
     * @return CreateImageVariationService
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getImage(): string
    {
        return $this->image;
    }
}
