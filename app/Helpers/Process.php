<?php

namespace App\Helpers;

use App\Exceptions\ProcessFailed;
use Closure;
use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    protected $echoOnFailure = true;
    protected $echoLineByLineOutput = false;

    protected $timeout = 60;
    protected $environmentVars = [];
    protected $arguments = [];
    protected $process;
    protected $processModifications;

    public function withCommand(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function run()
    {
        $this->process = new SymfonyProcess($this->arguments, null, $this->environmentVars, null, $this->timeout);

        if ($this->processModifications && is_callable($this->processModifications)) {
            $processModificationsClosure = $this->processModifications;
            $processModificationsClosure($this->process);
        }

        $this->process->start();

        if ($this->echoLineByLineOutput) {
            foreach ($this->process as $data) {
                echo $data;
            }
        }

        $this->process->wait();

        if ($this->process->getExitCode() !== 0) {
            // If we're already echo'ing line by line, there's no point echoing it again
            if ($this->echoOnFailure && !$this->echoLineByLineOutput) {
                foreach ($this->process as $data) {
                    echo $data;
                }
            }

            throw (new ProcessFailed("Process failed to run", $this->process->getExitCode()))
                ->setCommand(implode(' ', $this->arguments))
                ->setProcess($this->process);
        }

        return $this->process->getOutput();
    }

    public function withProcessModifications(Closure $closure)
    {
        $this->processModifications = $closure;
        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function withTimeout(?int $timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function withEnvironmentVars(array $environmentVars)
    {
        $this->environmentVars = array_merge($this->environmentVars, $environmentVars);
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
