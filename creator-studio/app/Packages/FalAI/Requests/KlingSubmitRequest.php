<?php

namespace App\Packages\FalAI\Requests;

use App\Concerns\HasJsonValidationFailedResponse;
use App\Packages\FalAI\Enums\AspectRatioEnum;
use App\Packages\FalAI\Enums\Kling\DurationEnum;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class KlingSubmitRequest extends FormRequest
{
    use HasJsonValidationFailedResponse;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'prompt'          => 'required|string',
            'duration'        => 'sometimes|string|in:' . implode(',', DurationEnum::getValues()),
            'aspect_ratio'    => 'sometimes|string|in:' . implode(',', AspectRatioEnum::getValues()),
            'negative_prompt' => 'sometimes|string',
            'cfg_scale'       => 'sometimes|float',
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
