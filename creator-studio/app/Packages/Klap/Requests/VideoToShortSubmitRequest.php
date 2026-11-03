<?php

namespace App\Packages\Klap\Requests;

use App\Concerns\HasJsonValidationFailedResponse;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class VideoToShortSubmitRequest extends FormRequest
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
            'file'                        => 'sometimes|file|required_without:source_video_url',
            'source_video_url'            => 'sometimes|url|required_without:file',
            'language'                    => 'sometimes|string',
            'max_duration'                => 'required|integer',
            'target_clip_count'           => 'required|integer',
            'editing_options'             => 'required|array',
            'editing_options.captions'    => 'required|boolean',
            'editing_options.intro_title' => 'required|boolean',
            'editing_options.emojis'      => 'required|boolean',
        ];
    }
}
