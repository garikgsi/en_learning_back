<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'coins' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'clientRequestId' => ['required', 'uuid'],
        ];
    }
}
