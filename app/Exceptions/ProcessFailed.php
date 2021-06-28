<?php

namespace App\Exceptions;

use Symfony\Component\Process\Process;

class ProcessFailed extends \Exception
{
    protected $command;
    protected $process;

    public function getCommand()
    {
        return $this->command;
    }

    public function setCommand(string $command)
    {
        $this->command = $command;
        return $this;
    }

    public function getProcess(): ?Process
    {
        return $this->process;
    }

    public function setProcess(Process $process)
    {
        $this->process = $process;
        return $this;
    }
}
