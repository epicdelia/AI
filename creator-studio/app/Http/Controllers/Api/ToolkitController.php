<?php

namespace App\Http\Controllers\Api;

use App\Domains\Toolkit\Models\CustomTool;
use App\Domains\Toolkit\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ToolkitController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'tools' => ToolRegistry::all(),
            'categories' => ToolRegistry::CATEGORIES,
            'favorites' => $this->favorites(),
            'defaults' => ['tone' => Setting::get('tone_of_voice', ''), 'language' => Setting::get('language', 'English')],
        ]);
    }

    public function run(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'tool' => 'required|string|max:60',
            'inputs' => 'nullable|array',
            'inputs.*' => 'nullable|string|max:40000',
            'tone' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'results' => 'nullable|integer|min:1|max:5',
            'creativity' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $tool = ToolRegistry::find($data['tool']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $inputs = $data['inputs'] ?? [];
        foreach ($tool['fields'] as $f) {
            if (! empty($f['required']) && trim((string) ($inputs[$f['name']] ?? '')) === '') {
                return response()->json(['error' => "Fill in “{$f['label']}” first."], 422);
            }
        }

        try {
            $output = $llm->as('tool:'.$data['tool'])
                ->creativity(isset($data['creativity']) ? (float) $data['creativity'] : null)
                ->chat(ToolRegistry::system(), ToolRegistry::userPrompt($tool, $inputs, $data));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['output' => $output, 'tool' => $tool['name']]);
    }

    public function storeCustom(Request $request): JsonResponse
    {
        $tool = CustomTool::create($this->validated($request));

        return response()->json(['tool' => $tool, 'message' => 'Template saved.']);
    }

    public function updateCustom(Request $request, CustomTool $tool): JsonResponse
    {
        $tool->update($this->validated($request));

        return response()->json(['tool' => $tool, 'message' => 'Template updated.']);
    }

    public function destroyCustom(CustomTool $tool): JsonResponse
    {
        $tool->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        $key = (string) $request->validate(['key' => 'required|string|max:60'])['key'];
        $favs = $this->favorites();
        $favs = in_array($key, $favs, true) ? array_values(array_diff($favs, [$key])) : [...$favs, $key];
        Setting::put('favorite_tools', json_encode($favs));

        return response()->json(['favorites' => $favs]);
    }

    private function favorites(): array
    {
        $favs = json_decode((string) Setting::get('favorite_tools', '[]'), true);

        return is_array($favs) ? array_values(array_filter($favs, 'is_string')) : [];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:300',
            'icon' => 'nullable|string|max:16',
            'fields' => 'required|array|min:1|max:10',
            'prompt' => 'required|string|max:10000',
        ]);
        $data['fields'] = ToolRegistry::cleanFields($data['fields']);
        $data['icon'] = ($data['icon'] ?? '') ?: '✨';
        if (! $data['fields']) {
            abort(response()->json(['error' => 'Add at least one field with a name (letters, numbers, underscores).'], 422));
        }

        return $data;
    }
}
