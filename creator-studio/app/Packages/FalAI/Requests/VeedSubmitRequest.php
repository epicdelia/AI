<?php

namespace App\Packages\FalAI\Requests;

use App\Packages\FalAI\Enums\Veed\AvatarEnum;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VeedSubmitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'avatar_id' => 'required|string|in:' . implode(',', AvatarEnum::getValues()),
            'text'      => 'required|string',
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
