<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Exceptions\ProcessFailed;
use Illuminate\Support\Collection;

class Ec2
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function listInstances($query): ?Collection
    {
        $commandOptions = [
            'ec2',
            'describe-instances',
        ];

        if ($query) {
            $commandOptions[] = "--query={$query}";
        }

        try {
            $processOutput = $this->aws->newProcess($commandOptions)
            ->run();
        } catch (ProcessFailed $e) {
            $this->command->error("Unable to list ec2 instances");
            return null;
        }

        return collect(json_decode($processOutput, true));
    }

}
