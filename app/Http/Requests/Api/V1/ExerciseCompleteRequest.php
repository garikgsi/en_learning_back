<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ExerciseCompleteRequest extends FormRequest
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
            'exercise_id' => ['required', 'integer', 'exists:exercise,id'],
            'exercise_items_result' => ['required', 'array'],
            'exercise_items_result.*' => ['required', 'array'],
            'exercise_items_result.*.exercise_item_id' => [
                'required',
                'integer',
                'exists:exercise_items,id',
            ],
            'exercise_items_result.*.errors_count' => [
                'required',
                'integer',
                'min:0',
            ],
            'exercise_items_result.*.hints_count' => [
                'required',
                'integer',
                'min:0',
            ],
            'exercise_items_result.*.lang_id' => [
                'required',
                'integer',
                'exists:lang,id',
            ],
            'exercise_items_result.*.variants' => [
                'present',
                'array',
            ],
            'exercise_items_result.*.variants.*' => [
                'required',
                'string',
            ],
        ];
    }
}
