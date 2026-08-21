<?php

namespace App\Services\Dictionary\Contracts;

use App\Services\Dictionary\Data\PhoneticsResult;

interface PhoneticsDriver
{
    public function find(string $english): ?PhoneticsResult;
}
