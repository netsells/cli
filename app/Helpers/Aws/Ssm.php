<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\Process;
use App\Exceptions\ProcessFailed;
use LaravelZero\Framework\Commands\Command;

class Ssm
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function sendRemoteCommand(string $instanceId, string $remoteCommand): bool
    {
        try {
            $this->aws->newProcess([
                'ssm', 'send-command',
                '--instance-ids', $instanceId,
                '--document-name', 'AWS-RunShellScript',
                '--parameters', "commands=\"{$remoteCommand}\"",
                '--comment', '"Temporary SSM SSH Access via Netsells CLI"'
            ])
            ->run();

            return true;
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to send SSH key to server via ssm send-command.");
            $this->aws->getCommand()->comment("Failing command was: " . $e->getCommand());
            return false;
        }
    }

    public function startSessionProcess(string $instanceId): Process
    {
        return $this->aws->newProcess([
            'ssm', 'start-session',
            '--target', $instanceId,
            '--document-name', 'AWS-StartSSHSession',
        ]);
    }
}
