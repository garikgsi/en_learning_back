<?php

namespace App\Exceptions;

use DomainException;

class NoWordsAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Нет слов в словаре');
    }
}
