<?php

namespace App\Services\Dictionary\Drivers;

use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Data\PhoneticsResult;
use Throwable;

class FallbackPhoneticsDriver implements PhoneticsDriver
{
    /**
     * @param  list<PhoneticsDriver>  $drivers
     */
    public function __construct(private readonly array $drivers) {}

    public function find(string $english): ?PhoneticsResult
    {
        foreach ($this->drivers as $driver) {
            try {
                $result = $driver->find($english);

                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return null;
    }
}
