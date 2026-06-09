<?php

namespace App\Support;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct(public float $shortfall)
    {
        parent::__construct("Insufficient wallet balance — need {$shortfall} more.");
    }
}
