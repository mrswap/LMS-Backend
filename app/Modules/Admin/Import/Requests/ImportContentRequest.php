<?php

namespace App\Modules\Admin\Import\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportContentRequest extends FormRequest {
    /**
     * Determine if the user is authorized.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Validation Rules
     */
    public function rules(): array {
        return [

            'program_id' => [
                'required',
                'integer',
                'exists:programs,id',
            ],

            'level_id' => [
                'required',
                'integer',
                'exists:levels,id',
            ],

            'type' => [
                'required',
                'string',
                'in:all,content,quiz,exam,both',
            ],

            'html' => [
                'required',
                'string',
                'min:100',
            ],

            'module_id' => [
                'nullable',
                'integer',
                'exists:modules,id',
            ],
        ];
    }

    /**
     * Validation Messages
     */
    public function messages(): array {
        return [

            'program_id.required' => 'Program is required.',
            'program_id.integer' => 'Program must be valid.',
            'program_id.exists' => 'Program does not exist.',

            'level_id.required' => 'Level is required.',
            'level_id.integer' => 'Level must be valid.',
            'level_id.exists' => 'Level does not exist.',

            'html.required' => 'HTML content is required.',
            'html.string' => 'HTML content must be valid.',
            'html.min' => 'Content is too short.',

            'type.required' => 'Import type is required.',
            'type.string' => 'Import type must be valid.',
            'type.in' => 'Invalid import type selected.',

        ];
    }

    /**
     * Prepare before validation
     */
    protected function prepareForValidation(): void {
        $this->merge([

            'program_id' => $this->program_id
                ? (int) $this->program_id
                : null,

            'level_id' => $this->level_id
                ? (int) $this->level_id
                : null,

            'type' => is_string($this->type)
                ? strtolower(trim($this->type))
                : 'all',

            'html' => is_string($this->html)
                ? trim($this->html)
                : null,

            'module_id' => $this->module_id
                ? (int) $this->module_id
                : null,
        ]);
    }

    public function getProgramId(): int {
        return (int) $this->validated('program_id');
    }

    public function getLevelId(): int {
        return (int) $this->validated('level_id');
    }

    public function getHtml(): string {
        return (string) $this->validated('html');
    }

    public function getType(): string {
        return (string) $this->validated('type');
    }

    public function getModuleId(): ?int {
        $value = $this->validated('module_id');

        return $value !== null
            ? (int) $value
            : null;
    }
}
