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
                Rule::in(['+79031611479', '+79262260386', '+79917036701', '+9252463899']),
            ],
            'pinCode' => ['required', 'string', 'regex:/^\d{4}$/'],
            'firstGradeYear' => [
                'required',
                'integer',
                'min:1900',
                'max:'.now()->year,
            ],
            'avatar' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
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
