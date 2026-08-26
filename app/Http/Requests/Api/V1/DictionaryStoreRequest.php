<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DictionaryStoreRequest extends FormRequest
{
    private const WORD_PATTERN = "~^[\\p{L}() '!?-]+$~u";

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
            'russian' => [
                'required',
                'string',
                'max:255',
                'regex:'.self::WORD_PATTERN,
            ],
            'english' => [
                'required',
                'string',
                'max:255',
                'regex:'.self::WORD_PATTERN,
            ],
            'transcription' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Допустимы только буквы, круглые скобки, дефисы, пробелы, '
            .'прямые апострофы, восклицательные и вопросительные знаки.';

        return [
            'russian.regex' => $message,
            'english.regex' => $message,
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
