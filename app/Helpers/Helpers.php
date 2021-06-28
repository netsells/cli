<?php

namespace App\Helpers;

use LaravelZero\Framework\Commands\Command;

class Helpers
{
    protected $command;

    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    public function git(): Git
    {
        return new Git($this->command);
    }

    public function docker(): Docker
    {
        return new Docker($this->command, $this);
    }

    public function checks(): Checks
    {
        return new Checks($this->command);
    }

    public function console(): Console
    {
        return new Console($this->command);
    }

    public function aws(): Aws
    {
        return new Aws($this->command, $this);
    }

    public function process(): Process
    {
        return app(Process::class);
    }

    public function netsellsFile(): NetsellsFile
    {
        return new NetsellsFile();
    }
}
