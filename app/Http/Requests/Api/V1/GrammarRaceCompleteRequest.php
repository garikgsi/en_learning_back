<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GrammarRaceCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'clientResultId' => ['required', 'uuid'],
            'completedAt' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toISOString()],
            'rounds' => ['required', 'array', 'min:1', 'max:500'],
            'rounds.*' => ['required', 'array'],
            'rounds.*.taskPosition' => ['required', 'integer', 'min:1'],
            'rounds.*.playerAnswer' => ['present', 'nullable', 'string', 'max:64'],
            'rounds.*.playerAnswerMs' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
            'rounds.*.secondPlayerAnswer' => ['sometimes', 'nullable', 'string', 'max:64'],
            'rounds.*.secondPlayerAnswerMs' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
