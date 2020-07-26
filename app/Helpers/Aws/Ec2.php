<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;

class Ec2
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function listInstances(Command $command, $query): ?Collection
    {
        $commandOptions = [
            'ec2',
            'describe-instances',
        ];

        if ($query) {
            $commandOptions[] = "--query={$query}";
        }

        try {
            $processOutput = $this->aws->newProcess($command, $commandOptions)
            ->echoLineByLineOutput(false)
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to list ec2 instances");
        }

        return collect(json_decode($processOutput, true));
    }

}
