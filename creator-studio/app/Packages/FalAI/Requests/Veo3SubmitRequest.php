<?php

namespace App\Packages\FalAI\Requests;

use App\Packages\FalAI\Enums\AspectRatioEnum;
use App\Packages\FalAI\Enums\Veo3\DurationEnum;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class Veo3SubmitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'prompt'          => 'required|string',
            'aspect_ratio'    => 'sometimes|string|in:' . implode(',', AspectRatioEnum::getValues()),
            'duration'        => 'sometimes|string|in:' . implode(',', DurationEnum::getValues()),
            'negative_prompt' => 'sometimes|string',
            'enhance_prompt'  => 'sometimes|boolean',
            'seed'            => 'sometimes|integer',
            'generate_audio'  => 'sometimes|boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->first();

        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => $errors,
            ], 422)
        );
    }
}
