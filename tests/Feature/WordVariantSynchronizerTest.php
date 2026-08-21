<?php

namespace Tests\Feature;

use App\Models\Word;
use App\Services\WordVariantSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordVariantSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_direct_variants_without_transitive_chains(): void
    {
        $storeShop = $this->word('магазин', 'store');
        $storeKeep = $this->word('хранить', 'store');
        $shop = $this->word('магазин', 'shop');
        $keep = $this->word('хранить', 'keep');

        app(WordVariantSynchronizer::class)->synchronize();

        $this->assertSame(['хранить'], $storeShop->fresh()->ru_variants);
        $this->assertSame(['shop'], $storeShop->fresh()->en_variants);
        $this->assertSame(['магазин'], $storeKeep->fresh()->ru_variants);
        $this->assertSame(['keep'], $storeKeep->fresh()->en_variants);
        $this->assertSame(['store'], $shop->fresh()->en_variants);
        $this->assertSame(['store'], $keep->fresh()->en_variants);
    }

    public function test_it_flattens_existing_comma_separated_values_and_removes_duplicates(): void
    {
        $word = $this->word('пирог, торт', 'cake');
        $this->word('торт,пирожное', 'cake');

        app(WordVariantSynchronizer::class)->synchronize();

        $this->assertSame(['пирожное'], $word->fresh()->ru_variants);
    }

    private function word(string $ru, string $en): Word
    {
        return Word::query()->create([
            'ru' => $ru,
            'en' => $en,
            'grade' => 1,
        ]);
    }
}
