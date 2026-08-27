<?php

namespace App\Services\Notifications\Contracts;

interface PushGateway
{
    /**
     * @param  array<string, scalar>  $data
     */
    public function send(
        string $pushToken,
        string $title,
        string $body,
        array $data,
    ): void;
}
