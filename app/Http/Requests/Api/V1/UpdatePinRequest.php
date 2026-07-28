<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePinRequest extends FormRequest
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
            'currentPin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pinCode' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pinCodeConfirmation' => ['required', 'same:pinCode'],
        ];
    }
}
