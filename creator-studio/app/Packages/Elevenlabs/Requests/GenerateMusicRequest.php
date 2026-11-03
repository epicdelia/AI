<?php

namespace App\Packages\Elevenlabs\Requests;

use App\Concerns\HasJsonValidationFailedResponse;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GenerateMusicRequest extends FormRequest
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
            'negative_prompt' => 'sometimes|string',
            'cfg_scale'       => 'sometimes|float',
        ];
    }

    protected function failedValidation(Validator $validator): void
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
