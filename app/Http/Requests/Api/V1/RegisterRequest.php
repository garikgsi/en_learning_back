<?php

namespace App\Http\Requests\Api\V1;

use App\Support\RussianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+7\d{10}$/',
                Rule::unique('users', 'phone'),
            ],
            'pinCode' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name'))
                ? trim($this->string('name')->toString())
                : $this->input('name'),
            'phone' => RussianPhone::normalize($this->input('phone')),
        ]);
    }
}
