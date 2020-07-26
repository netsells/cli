<?php

namespace App\Exceptions;

class ProcessFailed extends \Exception
{
    protected $command;

    public function getCommand()
    {
        return $this->command;
    }

    public function setCommand(string $command)
    {
        $this->command = $command;
        return $this;
    }
}
