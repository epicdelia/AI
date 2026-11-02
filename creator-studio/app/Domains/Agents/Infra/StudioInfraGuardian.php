<?php

namespace App\Domains\Agents\Infra;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * StudioInfraGuardian
 *
 * High ROI Feature: Unified Infrastructure, Terraform, Helm & App Health Guardian.
 * Audits all operational subsystems and provides actionable status diagnostics.
 */
class StudioInfraGuardian
{
    /**
     * Perform a complete diagnostic inspection of Creator Studio systems.
     *
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $timestamp = now()->toIso8601String();

        // 1. Database Health Check
        $databaseStatus = 'unknown';
        $dbDriver = config('database.default');
        try {
            DB::connection()->getPdo();
            $databaseStatus = 'healthy';
        } catch (\Throwable $e) {
            $databaseStatus = 'error: ' . $e->getMessage();
        }

        // 2. Cache & Storage Health Check
        $cacheStatus = 'healthy';
        try {
            Cache::put('guardian_ping', $timestamp, 10);
            $cacheVal = Cache::get('guardian_ping');
            if ($cacheVal !== $timestamp) {
                $cacheStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $cacheStatus = 'error: ' . $e->getMessage();
        }

        $storageStatus = 'healthy';
        try {
            $testFile = 'guardian_test_' . time() . '.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            Storage::disk('local')->delete($testFile);
        } catch (\Throwable $e) {
            $storageStatus = 'error: ' . $e->getMessage();
        }

        // 3. Terraform Stacks & Modules Check
        $baseDir = base_path();
        $tfModulesCommunity = is_dir($baseDir . '/terraform/modules/providers/aws/community') || is_file($baseDir . '/module_community.lst');
        $tfModulesOfficials = is_dir($baseDir . '/terraform/modules/providers/aws/officials') || is_file($baseDir . '/module_officials.lst');

        $tfStacks = [
            'core' => is_file($baseDir . '/terraform/environment/providers/aws/infra/core/main.tf'),
            'tfstate' => is_file($baseDir . '/terraform/environment/providers/aws/infra/tfstate/main.tf'),
            'budget' => is_file($baseDir . '/terraform/environment/providers/aws/infra/resources/budget/main.tf'),
            'rds' => is_file($baseDir . '/terraform/environment/providers/aws/infra/resources/rds/laraveldb/main.tf'),
            'eks' => is_file($baseDir . '/terraform/environment/providers/aws/infra/resources/eks/main.tf'),
        ];
        $allStacksPresent = !in_array(false, $tfStacks, true);

        // 4. Helm Templates Check
        $helmTemplates = ['api', 'backend', 'configmap', 'frontend', 'secretref', 'stateful', 'svcrole'];
        $helmStatus = [];
        foreach ($helmTemplates as $t) {
            $chartPath = $baseDir . "/helm/templates/{$t}/Chart.yaml";
            $helmStatus[$t] = is_file($chartPath);
        }

        // 5. AI Services Credentials Status
        $hasLlmKey = !empty(Setting::get('llm_api_key'));
        $hasSearchKey = !empty(Setting::get('serper_api_key')) || !empty(Setting::get('perplexity_api_key'));
        $hasTtsKey = !empty(Setting::get('elevenlabs_api_key')) || !empty(Setting::get('openai_api_key'));

        return [
            'status' => 'operational',
            'timestamp' => $timestamp,
            'environment' => config('app.env'),
            'diagnostics' => [
                'database' => [
                    'driver' => $dbDriver,
                    'status' => $databaseStatus,
                ],
                'cache' => [
                    'store' => config('cache.default'),
                    'status' => $cacheStatus,
                ],
                'storage' => [
                    'disk' => 'local',
                    'status' => $storageStatus,
                ],
                'terraform' => [
                    'community_submodules_downloaded' => $tfModulesCommunity,
                    'official_submodules_downloaded' => $tfModulesOfficials,
                    'stacks' => $tfStacks,
                    'validation_ready' => $allStacksPresent && $tfModulesCommunity && $tfModulesOfficials,
                ],
                'helm' => [
                    'templates_available' => $helmStatus,
                    'all_templates_present' => !in_array(false, $helmStatus, true),
                ],
                'ai_integrations' => [
                    'llm_configured' => $hasLlmKey,
                    'search_grounding_configured' => $hasSearchKey,
                    'voice_synthesis_configured' => $hasTtsKey,
                ],
            ],
        ];
    }
}

