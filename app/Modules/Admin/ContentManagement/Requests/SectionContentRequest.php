<?php
namespace App\Modules\Admin\ContentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SectionContentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'type' => 'required|in:text,media,quiz,h5p',
            'title' => 'nullable|string',

            'content' => 'required_if:type,text|nullable|string',

            'meta' => 'required_if:type,media|array',
            'meta.shortcode' => 'required_if:type,media|exists:media,shortcode',

            'order' => 'nullable|integer',
        ];
    }
}