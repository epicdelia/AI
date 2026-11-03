<?php

declare(strict_types=1);

namespace OpenAI\Testing\Responses\Concerns;

use Http\Discovery\Psr17FactoryDiscovery;
use OpenAI\Responses\StreamResponse;

trait FakeableForStreamedResponse
{
    /**
     * @param  null  $resource
     */
    public static function fake($resource = null): StreamResponse
    {
        if ($resource === null) {
            $filename = str_replace(['OpenAI\Responses', '\\'], [__DIR__ . '/../Fixtures/', '/'], static::class) . 'Fixture.txt';
            $resource = fopen($filename, 'rb');
        }

        $stream = Psr17FactoryDiscovery::findStreamFactory()
            ->createStreamFromResource($resource);

        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse()
            ->withBody($stream);

        return new StreamResponse(static::class, $response);
    }
}
