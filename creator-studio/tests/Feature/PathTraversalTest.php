<?php

namespace Tests\Feature;

use Tests\TestCase;

class PathTraversalTest extends TestCase
{
    public function test_images_route_serves_only_images_inside_public_images(): void
    {
        $this->get('/images/speaking/head-start-2024.jpg')->assertOk();

        foreach (['..%2F..%2F.env', '..%2F..%2Fcomposer.json', '../../composer.json', '..%2Findex.php', 'speaking%2F..%2F..%2F..%2F.env'] as $evil) {
            $this->get('/images/'.$evil)->assertNotFound();
        }
    }
}
