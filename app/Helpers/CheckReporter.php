<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class CheckReporter
{
    /** @var Command $command */
    protected $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function reportMissing($type, $items): void
    {
        $typePlural = Str::plural($type, count($items));

        $this->command->comment("Cannot run due to missing required {$typePlural}: ");
        foreach ($items as $item) {
            $this->command->comment("- {$item}");
        }
    }
}
