<?php

namespace App\Modules\Admin\ContentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',

            // 🔹 Added document support
            'type' => 'sometimes|in:image,video,audio,document',

            'file' => 'nullable|file',
            'external_url' => 'nullable|url',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $type = $this->input('type');

            // ✅ Only enforce on STORE (POST /media)
            if ($this->isMethod('post') && !$this->route('id')) {

                if (!$this->hasFile('file') && !$this->external_url) {
                    $validator->errors()->add('file', 'File or external URL is required');
                    return;
                }
            }

            // 🔹 File validation (only if file exists)
            if ($this->hasFile('file')) {

                $ext = strtolower($this->file->extension());

                if ($type === 'image' && !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $validator->errors()->add('file', 'Invalid image format');
                }

                if ($type === 'video' && !in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                    $validator->errors()->add('file', 'Invalid video format');
                }

                if ($type === 'audio' && !in_array($ext, ['mp3', 'wav', 'aac'])) {
                    $validator->errors()->add('file', 'Invalid audio format');
                }

                if ($type === 'document' && !in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
                    $validator->errors()->add('file', 'Invalid document format');
                }
            }
        });
    }
}
