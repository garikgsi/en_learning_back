<?php

namespace Tests\Feature;

use App\Enums\LangCode;
use App\Models\Lang;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LangSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_languages_idempotently(): void
    {
        $this->seed(LangSeeder::class);
        $this->seed(LangSeeder::class);

        $this->assertDatabaseCount('lang', 2);

        foreach (LangCode::cases() as $code) {
            $this->assertDatabaseHas('lang', [
                'id' => $code->value,
                'name' => $code->name,
                'title' => $code->title(),
            ]);
            $this->assertSame(
                $code->value,
                Lang::forCode($code)->id,
            );
        }
    }
}
