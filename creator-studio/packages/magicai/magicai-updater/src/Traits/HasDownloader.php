<?php

namespace MagicAI\Updater\Traits;

use Exception;
use Illuminate\Support\Facades\Http;
use MagicAI\Updater\Exceptions\InvalidURLException;
use RuntimeException;

trait HasDownloader
{
    public string $path;

    public function download(string $url, ?string $filename = null): string
    {
        // Extract the filename from the URL using pathinfo
        $filename = $filename ?: basename(parse_url($url, PHP_URL_PATH));

        // If no filename is found, throw an exception
        if (! $filename) {
            throw new InvalidURLException('Invalid URL, unable to extract the filename.');
        }

        // The full path where the zip file will be saved
        $this->path = base_path($filename);

        try {

            // Download the file from the URL
            $response = Http::timeout(1800)
                ->withOptions([
                    'sink' => $this->path,
                ])
                ->throw(function ($request, $exception) {

                    throw new RuntimeException('Failed to download the zip file');
                })
                ->get($url);

            // If the request is unsuccessful, throw an exception
            if (! $response->successful()) {
                throw new RuntimeException('Failed to download the zip file');
            }
        } catch (Exception|RuntimeException $exception) {
            throw new RuntimeException('Failed to download the zip file');
        }

        // Save the file content to the base_path() directory

        return $this->path;
    }
}
