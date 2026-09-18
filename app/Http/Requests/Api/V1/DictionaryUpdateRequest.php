<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DictionaryUpdateRequest extends FormRequest
{
    private const WORD_PATTERN = "~^[\\p{L}() ,'!?-]+$~u";

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $wordRules = ['required', 'string', 'max:255', 'regex:'.self::WORD_PATTERN];

        return [
            'russian' => $wordRules,
            'english' => $wordRules,
            'russianVariants' => ['present', 'array', 'list', 'max:50'],
            'englishVariants' => ['present', 'array', 'list', 'max:50'],
            'russianVariants.*' => [...$wordRules, 'distinct:ignore_case'],
            'englishVariants.*' => [...$wordRules, 'distinct:ignore_case'],
            'transcription' => ['missing'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $message = 'Допустимы буквы, пробелы, скобки, запятые, дефисы, апострофы, ! и ?.';

        return [
            'russian.regex' => $message,
            'english.regex' => $message,
            'russianVariants.*.regex' => $message,
            'englishVariants.*.regex' => $message,
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['russian', 'english'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        foreach (['russianVariants', 'englishVariants'] as $field) {
            if (is_array($this->input($field))) {
                $this->merge([$field => array_map(
                    fn ($value) => is_string($value) ? trim($value) : $value,
                    $this->input($field),
                )]);
            }
        }
    }
}
