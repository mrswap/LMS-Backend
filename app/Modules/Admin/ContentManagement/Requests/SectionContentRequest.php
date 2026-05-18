<?php

namespace App\Modules\Admin\ContentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SectionContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | BASIC
            |--------------------------------------------------------------------------
            */

            'type' => 'required|in:text,media,quiz,h5p',

            'title' => 'nullable|string|max:255',

            /*
            |--------------------------------------------------------------------------
            | CONTENT
            |--------------------------------------------------------------------------
            */

            'content' => 'required_if:type,text|nullable|string',

            /*
            |--------------------------------------------------------------------------
            | MEDIA
            |--------------------------------------------------------------------------
            */

            'meta' => 'nullable|array',

            'meta.shortcode' => 'required_if:type,media|exists:media,shortcode',

            /*
            |--------------------------------------------------------------------------
            | ORDER
            |--------------------------------------------------------------------------
            */

            'order' => 'nullable|integer|min:0',

            /*
            |--------------------------------------------------------------------------
            | SYSTEM USER ONLY FIELDS
            |--------------------------------------------------------------------------
            */

            'status' => 'nullable|boolean',

            'publish_status' => 'nullable|in:draft,published,unpublished',
        ];
    }
}
