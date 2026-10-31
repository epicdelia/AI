<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Workflows\Catalog;
use App\Domains\Workflows\Engine;
use App\Domains\Workflows\Models\Workflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/** Workflows: the node catalog, her workflows, the builder's save, runs and tests. */
class WorkflowsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'workflows' => Workflow::orderByDesc('updated_at')->get()->map(fn ($w) => $this->summary($w)),
            'templates' => Catalog::templates(),
            'catalog' => Catalog::nodes(),
        ]);
    }

    public function show(Workflow $workflow): JsonResponse
    {
        return response()->json([
            'workflow' => $this->summary($workflow) + ['nodes' => $workflow->nodes],
            'runs' => $workflow->runs()->latest()->limit(30)->get(),
            'catalog' => Catalog::nodes(),
            'agents' => SocialAgent::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:120'], 'template' => ['nullable', 'string', 'max:40'], 'trigger' => ['nullable', 'string', 'max:40']]);
        $template = collect(Catalog::templates())->firstWhere('id', $data['template'] ?? null);
        $nodes = $template['nodes'] ?? [['type' => ($data['trigger'] ?? null) && (Catalog::get($data['trigger'])['kind'] ?? '') === 'trigger' ? $data['trigger'] : 'any_comment', 'config' => []]];
        // Fresh, readable keys (trigger_1, action_1, …) like the builder makes.
        $count = ['trigger' => 0, 'logic' => 0, 'action' => 0];
        $nodes = array_map(function ($n) use (&$count) {
            $kind = Catalog::get($n['type'])['kind'];
            $count[$kind]++;

            return ['key' => "{$kind}_{$count[$kind]}", 'type' => $n['type'], 'config' => $n['config'] ?? [], 'enabled' => true];
        }, $nodes);
        if ($template && ($agent = SocialAgent::orderBy('id')->first())) {
            foreach ($nodes as &$n) {
                if ($n['type'] === 'run_agent') {
                    $n['config']['agent_id'] = $agent->id;
                }
            }
            unset($n);
        }
        $wf = Workflow::create(['name' => $data['name'] ?? $template['name'] ?? 'Untitled workflow', 'nodes' => $nodes, 'active' => false]);

        return response()->json(['workflow' => $this->summary($wf) + ['nodes' => $wf->nodes]], 201);
    }

    /** The builder's save: name, nodes, on/off. Turning on requires every step to be set up. */
    public function update(Request $request, Workflow $workflow): JsonResponse
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'nodes' => ['sometimes', 'array'], 'active' => ['sometimes', 'boolean']]);
        try {
            if (isset($data['nodes'])) {
                $data['nodes'] = Engine::normalise($data['nodes']);
            }
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $nodes = $data['nodes'] ?? $workflow->nodes;
        if (($data['active'] ?? $workflow->active) && ($problems = Engine::problems($nodes))) {
            if (! empty($data['active'])) {
                return response()->json(['error' => 'Before turning it on: '.implode(' ', $problems), 'problems' => $problems], 422);
            }
            $data['active'] = false; // an edit broke it: switch off rather than run half-configured
        }
        $workflow->update($data);

        return response()->json(['workflow' => $this->summary($workflow->fresh()) + ['nodes' => $workflow->nodes]]);
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $workflow->delete();

        return response()->json(['ok' => true]);
    }

    public function duplicate(Workflow $workflow): JsonResponse
    {
        $copy = Workflow::create(['name' => mb_substr($workflow->name.' (copy)', 0, 120), 'nodes' => $workflow->nodes, 'active' => false]);

        return response()->json(['workflow' => $this->summary($copy)], 201);
    }

    /** Test (dry run with sample data, nothing sent) or Run now (for "Run by hand" workflows). */
    public function run(Request $request, Workflow $workflow, Engine $engine): JsonResponse
    {
        $dry = $request->boolean('dry', true);
        if (! $dry) {
            if (($workflow->trigger()['type'] ?? '') !== 'manual') {
                return response()->json(['error' => 'Only “Run by hand” workflows run on demand; use Test to try this one safely.'], 422);
            }
            if ($problems = Engine::problems($workflow->nodes)) {
                return response()->json(['error' => implode(' ', $problems)], 422);
            }
        }
        @set_time_limit(300);
        try {
            return response()->json(['run' => $engine->start($workflow, $dry)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function testStep(Request $request, Workflow $workflow, Engine $engine): JsonResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:80']])['key'];
        try {
            return response()->json($engine->testStep($workflow, $key));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function summary(Workflow $w): array
    {
        $t = $w->trigger();

        return [
            'id' => $w->id, 'name' => $w->name, 'active' => $w->active, 'runs_count' => $w->runs_count, 'last_run_at' => $w->last_run_at,
            'updated_at' => $w->updated_at, 'trigger' => $t['type'] ?? null, 'steps' => array_values(array_map(fn ($n) => $n['type'], array_slice($w->nodes, 1))),
            'problems' => Engine::problems($w->nodes),
            'waiting' => $w->runs()->where('status', 'waiting')->count(),
            'failed_recent' => $w->runs()->where('status', 'failed')->where('dry_run', false)->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
