<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GrammarRaceAbandonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'clientResultId' => ['required', 'uuid'],
            'abandonedAt' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toISOString()],
        ];
    }
}
