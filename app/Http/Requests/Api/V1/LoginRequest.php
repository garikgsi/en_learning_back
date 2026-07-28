<?php

namespace App\Http\Requests\Api\V1;

use App\Support\RussianPhone;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+7\d{10}$/'],
            'pinCode' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => RussianPhone::normalize($this->input('phone')),
        ]);
    }
}
