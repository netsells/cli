<?php

namespace App\Helpers;

use LaravelZero\Framework\Commands\Command;

abstract class BaseHelper
{
    protected $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }
}
