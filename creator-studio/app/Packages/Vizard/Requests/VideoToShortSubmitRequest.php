<?php

namespace App\Packages\Vizard\Requests;

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
            'file'           => 'sometimes|file|required_without:source_video_url',
            'videoUrl'       => 'sometimes|url|required_without:file',
            'lang'           => 'required|string',
            'preferLength'   => 'required',
            'videoType'      => 'required|int',
            'ratioOfClip'    => 'sometimes|int',
            'headlineSwitch' => 'sometimes|int',
            'subtitleSwitch' => 'sometimes|int',
            'maxClipNumber'  => 'sometimes|int',
        ];
    }
}
