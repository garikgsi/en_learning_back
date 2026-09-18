<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminDailyExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isAdmin();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'userId' => ['required', 'uuid', 'exists:users,id'],
            'wordIds' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'wordIds.*' => ['required', 'integer', 'distinct', 'exists:words,id'],
            'dueDate' => ['required', 'date_format:Y-m-d'],
            'replaceExisting' => ['required', 'boolean'],
        ];
    }
}
