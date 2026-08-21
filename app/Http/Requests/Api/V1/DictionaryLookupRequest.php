<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DictionaryLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'max:255'],
            'sourceLanguage' => [
                'required',
                'string',
                Rule::in(['ru', 'en']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('word'))) {
            $this->merge(['word' => trim($this->input('word'))]);
        }
    }
}
