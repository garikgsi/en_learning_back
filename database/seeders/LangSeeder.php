<?php

namespace Database\Seeders;

use App\Enums\LangCode;
use App\Models\Lang;
use Illuminate\Database\Seeder;

class LangSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LangCode::cases() as $code) {
            $lang = Lang::query()->findOrNew($code->value);

            $lang->forceFill([
                'id' => $code->value,
                'name' => $code->name,
                'title' => $code->title(),
            ])->save();
        }
    }
}
