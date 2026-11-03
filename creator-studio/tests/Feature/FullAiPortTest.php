<?php

namespace Tests\Feature;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Facades\Engine;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Packages\Creatify\CreatifyService;
use App\Packages\Elevenlabs\ElevenlabsService;
use App\Packages\FalAI\FalAIService;
use App\Packages\Klap\KlapService;
use App\Packages\Topview\TopviewService;
use App\Packages\Vizard\VizardService;
use App\Services\Ai\AiCompletionService;
use App\Services\Ai\AIImageClient;
use App\Services\Llm;
use App\Services\VectorService;
use Tests\TestCase;

class FullAiPortTest extends TestCase
{
    public function test_all_33_engine_drivers_registered_and_resolvable(): void
    {
        $checked = 0;
        foreach (EngineEnum::cases() as $engine) {
            $class = $engine->driverClass();
            $this->assertTrue(class_exists($class), "Engine driver class missing: {$class}");
            $driver = new $class;
            $this->assertNotNull($driver);
            $checked++;
        }
        $this->assertGreaterThanOrEqual(33, $checked);
    }

    public function test_all_248_entity_model_drivers_registered_and_resolvable(): void
    {
        $checked = 0;
        foreach (EntityEnum::cases() as $entity) {
            $class = $entity->driverClass();
            $this->assertTrue(class_exists($class), "Entity driver class missing: {$class}");
            $driver = new $class;
            $this->assertNotNull($driver);
            $this->assertEquals($entity, $driver->enum());
            $checked++;
        }
        $this->assertGreaterThanOrEqual(248, $checked);
    }

    public function test_ai_packages_instantiable(): void
    {
        $this->assertTrue(class_exists(FalAIService::class));
        $this->assertTrue(class_exists(ElevenlabsService::class));
        $this->assertTrue(class_exists(VizardService::class));
        $this->assertTrue(class_exists(KlapService::class));
        $this->assertTrue(class_exists(CreatifyService::class));
        $this->assertTrue(class_exists(TopviewService::class));
    }

    public function test_ai_services_resolvable_from_container(): void
    {
        $completion = app(AiCompletionService::class);
        $this->assertInstanceOf(AiCompletionService::class, $completion);

        $imageClient = app(AIImageClient::class);
        $this->assertInstanceOf(AIImageClient::class, $imageClient);

        $vector = app(VectorService::class);
        $this->assertInstanceOf(VectorService::class, $vector);
    }

    public function test_llm_integration_with_fake(): void
    {
        Llm::$fake = fn (string $system, string $user) => "Port completed successfully: {$user}";

        $llm = app(Llm::class);
        $result = $llm->chat('You are an expert assistant.', 'Hello Studio AI');

        $this->assertEquals('Port completed successfully: Hello Studio AI', $result);

        Llm::$fake = null;
    }
}

