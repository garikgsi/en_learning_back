<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('type')) {
            $this->merge(['type' => 'translate']);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['translate', 'plural'])],
        ];
    }
}
