<?php

namespace Tests\Unit;

use App\Support\RussianPhone;
use PHPUnit\Framework\TestCase;

class RussianPhoneTest extends TestCase
{
    public function test_plus_eight_prefix_is_not_normalized_to_plus_seven(): void
    {
        $phone = '+8 (999) 123-45-67';

        $this->assertSame($phone, RussianPhone::normalize($phone));
    }

    public function test_russian_phone_formats_are_normalized(): void
    {
        $this->assertSame('+79991234567', RussianPhone::normalize('+7 (999) 123-45-67'));
        $this->assertSame('+79991234567', RussianPhone::normalize('8 (999) 123-45-67'));
        $this->assertSame('+79991234567', RussianPhone::normalize('9991234567'));
    }
}
