<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | RULES
    |--------------------------------------------------------------------------
    */

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | MESSAGE
            |--------------------------------------------------------------------------
            */

            'message' => [

                'nullable',

                'string',

                'required_without:attachment',
            ],

            /*
            |--------------------------------------------------------------------------
            | ATTACHMENT
            |--------------------------------------------------------------------------
            */

            'attachment' => [

                'nullable',

                'file',

                'required_without:message',

                /*
                |--------------------------------------------------------------------------
                | ALLOWED TYPES
                |--------------------------------------------------------------------------
                */

                'mimes:jpg,jpeg,png,pdf,mp4,mov',

                /*
                |--------------------------------------------------------------------------
                | MAX SIZE = 10MB
                |--------------------------------------------------------------------------
                */

                'max:10240',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGES
    |--------------------------------------------------------------------------
    */

    public function messages(): array
    {
        return [

            'message.required_without' =>

            'Message or attachment is required.',

            'attachment.required_without' =>

            'Attachment or message is required.',

            'attachment.mimes' =>

            'Allowed file types: jpg, jpeg, png, pdf, mp4, mov.',

            'attachment.max' =>

            'Attachment size must not exceed 10MB.',
        ];
    }
}
