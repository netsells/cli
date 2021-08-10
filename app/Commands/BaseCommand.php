<?php

namespace App\Commands;

use App\Helpers\Helpers;
use LaravelZero\Framework\Commands\Command;

abstract class BaseCommand extends Command
{
    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;

        $this->helpers->setCommand($this);

        parent::__construct();
    }

}
