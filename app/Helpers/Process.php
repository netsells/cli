<?php

namespace App\Helpers;

use App\Exceptions\ProcessFailed;
use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    protected $echoOnFailure = true;
    protected $echoLineByLineOutput = true;

    protected $timeout = 60;
    protected $environmentVars = [];
    protected $arguments = [];
    protected $process;

    public function withCommand(array $arguments)
    {
        $this->arguments = $arguments;

        $this->process = new SymfonyProcess($this->arguments, null, $this->environmentVars, null, $this->timeout);
        return $this;
    }

    public function run()
    {
        $this->process->start();

        if ($this->echoLineByLineOutput) {
            foreach ($this->process as $data) {
                echo $data;
            }
        }

        $this->process->wait();

        if ($this->process->getExitCode() !== 0) {
            if ($this->echoOnFailure) {
                foreach ($this->process as $data) {
                    echo $data;
                }
            }

            throw (new ProcessFailed("Process failed to run", $this->process->getExitCode()))->setCommand(implode(' ', $this->arguments));
        }

        return $this->process->getOutput();
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function withTimeout(int $timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function withEnvironmentVars(array $environmentVars)
    {
        $this->environmentVars = $environmentVars;
        return $this;
    }

    public function echoOnFailure(bool $toggle)
    {
        $this->echoOnFailure = $toggle;
        return $this;
    }

    public function echoLineByLineOutput(bool $toggle)
    {
        $this->echoLineByLineOutput = $toggle;
        return $this;
    }
}
