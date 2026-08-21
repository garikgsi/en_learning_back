<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DictionaryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'russian' => ['required', 'string', 'max:255'],
            'english' => ['required', 'string', 'max:255'],
            'transcription' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['russian', 'english', 'transcription'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->input('transcription') === '') {
            $this->merge(['transcription' => null]);
        }
    }
}
