<?php

namespace App\Services\Ai\OpenAI\Image;

use App\Helpers\Classes\Helper;
use App\Services\Ai\OpenAI\Image\Support\GptImageParamNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI;

class CreateImageEditService
{
    private string $generateURL = 'https://api.openai.com/v1/images/edits';

    /**
     * Supported models: dall-e-2, gpt-image-1, gpt-image-1.5, gpt-image-2
     */
    private string $model = 'gpt-image-1';

    private string $prompt = '';

    private string $size = '1024x1024';

    /**
     * dall-e-2: 'standard'
     * gpt-image-1: 'auto', 'high', 'low'
     */
    private string $quality = 'auto';

    private string $background = 'auto';

    private ?string $mask = null;

    private array $images = [];

    public string $output_format = 'png';

    public function generateForAi(): ?string
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3600);

        $client = OpenAI::client(Helper::setOpenAiKey());

        $response = $client
            ->images()
            ->edit($this->requestData());

        if (! isset($response['created'])) {
            return null;
        }

        if ($response['created'] && $response['data']) {
            $image = Arr::first($response['data']);
            if (isset($image['b64_json'])) {
                return $image['b64_json'];
            }
        }

        return null;
    }

    public function generate(): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3600);

        $client = OpenAI::client(Helper::setOpenAiKey());

        $response = $client
            ->images()
            ->edit($this->requestData());

        if (! isset($response['created'])) {
            return [
                'status'  => false,
                'message' => trans('AI model not available'),
            ];
        }

        if ($response['created'] && $response['data']) {

            $image = Arr::first($response['data']);

            if (isset($image['b64_json'])) {
                $base64Image = $image['b64_json'];

                $imageName = Str::uuid()->toString() . '.' . $this->getOutputFormat();

                Storage::disk('uploads')->put($imageName, base64_decode($base64Image));

                return [
                    'status'  => true,
                    'message' => trans('Image generated successfully'),
                    'path'    => '/uploads/' . $imageName,
                ];
            }
        }

        return [
            'status'  => false,
            'message' => 'Image generation failed. Please try again later.',
        ];
    }

    public function requestData(): array
    {
        $images = $this->getImages();

        $dataImage = [];

        if (count($images) > 1) {
            foreach ($this->getImages() as $image) {
                $dataImage[] = $this->resolveImage($image);
            }
        } else {
            $dataImage = $this->resolveImage(Arr::first($images));
        }

        $form = [
            'image'      => $dataImage,
            'model'      => $this->getModel(),
            'prompt'     => $this->getPrompt(),
            'n'          => 1,
            'size'       => $this->getSize(),
            'quality'    => $this->getQuality(),
            'background' => $this->getBackground(),
        ];

        $form = GptImageParamNormalizer::normalize($form);

        $mask = $this->resolveMask();
        if ($mask) {
            $form['mask'] = $mask;
        }

        return $form;
    }

    /**
     * @return resource File handle for the image
     */
    private function resolveImage(string $image): mixed
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $localHandle = $this->resolveSameOriginUrl($image);
            if ($localHandle !== null) {
                return $localHandle;
            }

            return $this->downloadToTemp($image);
        }

        if (str_starts_with($image, '/uploads') || str_starts_with($image, 'uploads')) {
            $localPath = public_path($image);
            if (file_exists($localPath)) {
                return fopen($localPath, 'r');
            }

            return $this->downloadToTemp(url($image));
        }

        return fopen(public_path($image), 'r');
    }

    /**
     * @return resource|null File handle for the mask, or null
     */
    private function resolveMask(): mixed
    {
        if (! $this->mask) {
            return null;
        }

        if (filter_var($this->mask, FILTER_VALIDATE_URL)) {
            $localHandle = $this->resolveSameOriginUrl($this->mask);
            if ($localHandle !== null) {
                return $localHandle;
            }

            return $this->downloadToTemp($this->mask);
        }

        if (str_starts_with($this->mask, '/uploads') || str_starts_with($this->mask, 'uploads')) {
            $localPath = public_path($this->mask);
            if (file_exists($localPath)) {
                return fopen($localPath, 'r');
            }

            return $this->downloadToTemp(url($this->mask));
        }

        $localPath = public_path($this->mask);

        return file_exists($localPath) ? fopen($localPath, 'r') : null;
    }

    /**
     * If the URL points back to this same app, return a file handle on the
     * local file instead of fetching over HTTP (avoids SSL issues on local certs).
     *
     * @return resource|null
     */
    private function resolveSameOriginUrl(string $url): mixed
    {
        $urlHost = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        if (! $urlHost || ! $appHost || $urlHost !== $appHost) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $localPath = public_path(ltrim($path, '/'));

        return file_exists($localPath) ? fopen($localPath, 'r') : null;
    }

    /**
     * Download a remote image to a temporary file and return a file handle.
     *
     * @return resource
     */
    private function downloadToTemp(string $url): mixed
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        $tempFile = sys_get_temp_dir() . '/' . uniqid('img_') . '.' . $extension;

        $contents = Http::timeout(60)->get($url)->body();
        file_put_contents($tempFile, $contents);

        return fopen($tempFile, 'r');
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function setBackground(string $background): CreateImageEditService
    {
        $this->background = $background;

        return $this;
    }

    public function getBackground(): string
    {
        return $this->background;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setPrompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setSize(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function setQuality(string $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    public function getQuality(): string
    {
        return $this->quality;
    }

    public function setMask(?string $mask): self
    {
        $this->mask = $mask;

        return $this;
    }

    public function getMask()
    {
        return $this->mask;
    }

    public function setImages(array $images): self
    {
        $this->images = $images;

        return $this;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function setOutputFormat(string $output_format): self
    {
        $this->output_format = $output_format;

        return $this;
    }

    public function getOutputFormat(): string
    {
        return $this->output_format;
    }
}
