<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AwsSsmCopy extends AwsSsmConnect
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ssm:copy';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Copy files from/to a server over SSM';

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('instance-id', null, InputOption::VALUE_OPTIONAL, 'The instance ID to connect to'),
            new InputOption('username', null, InputOption::VALUE_OPTIONAL, 'The username connect with'),
            new InputOption('direction', null, InputOption::VALUE_OPTIONAL, 'Direction (Up/Down)'),
            new InputOption('local-path', null, InputOption::VALUE_OPTIONAL, 'The path of the other server (local or remote). Can be a file or folder'),
            new InputOption('remote-path', null, InputOption::VALUE_OPTIONAL, 'The path of the server. Can be a file or folder'),
            new InputOption('other-server', null, InputOption::VALUE_OPTIONAL, 'Left blank, the other server is the local machine. Otherwise, specify user@host'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['aws', 'ssh'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            return 1;
        }

        $rebuildOptions = [];

        $instanceId = $this->option('instance-id') ?: $this->askForInstanceId();
        $username = $this->option('username') ?: $this->askForUsername();

        if (!$instanceId) {
            $this->error('No instance ID provided.');
            return 1;
        }

        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'username', $username);
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'instance-id', $instanceId);
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'aws-profile');
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'aws-region');

        $key = $this->generateTempSshKey();
        $command = $this->generateRemoteCommand($username, $key);

        $this->info("Sending a temporary SSH key to the server...", OutputInterface::VERBOSITY_VERBOSE);
        if (!$this->helpers->aws()->ssm()->sendRemoteCommand($instanceId, $command)) {
            $this->error('Failed to send SSH key to server');
            return 1;
        }

        $sessionCommand = $this->helpers->aws()->ssm()->startSessionProcess($instanceId);
        $sessionCommandString = implode(' ', $sessionCommand->getArguments());

        $options = [
            '-o', 'IdentityFile ~/.ssh/netsells-cli-ssm-ssh-tmp',
            '-o', 'IdentitiesOnly yes',
            '-o', 'GSSAPIAuthentication no',
            '-o', 'PasswordAuthentication no',
            '-o', "ProxyCommand {$sessionCommandString}",
        ];

        $direction = trim($this->option('direction')) ?: $this->askForDirection();

        if (!$this->validateDirection($direction)) {
            $this->error('Invalid direction');
        }

        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'direction', $direction);

        $otherServer = trim($this->option('other-server')) ?: $this->askForOtherServer();

        $localPath = trim($this->option('local-path')) ?: $this->askForLocalPath($otherServer);
        $remotePath = trim($this->option('remote-path')) ?: $this->askForRemotePath();

        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'local-path', $localPath);
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'remote-path', $remotePath);
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'other-server', $otherServer);

        $this->sendReRunHelper($rebuildOptions);

        $localOption = ($otherServer !== '__localhost__' ? "{$otherServer}:" : null) . $localPath;
        $remoteOption = sprintf("%s@%s", $username, $instanceId) . ":{$remotePath}";

        $directionOptions = ($direction == 'up') ? [$localOption, $remoteOption] : [$remoteOption, $localOption];

        try {
            $this->helpers->process()->withCommand(array_merge(
                [
                    'scp',
                ],
                $options,
                $directionOptions,
            ))
            ->withTimeout(null)
            ->withProcessModifications(function ($process) {
                $process->setTty(Process::isTtySupported());
                $process->setIdleTimeout(null);
            })
            ->run();
        } catch (ProcessFailed $e) {
            $this->info(' ');
            $this->error("SCP command exited with an exit code of " . $e->getCode());
        }
    }

    protected function askForDirection()
    {
        return $this->menu("Which direction are you sending the files?", [
            'up' => 'Upstream',
            'down' => 'Downstream',
        ])->open();
    }

    protected function validateDirection($direction): bool
    {
        return in_array($direction, ['up', 'down']);
    }

    protected function askForLocalPath($otherServer)
    {
        if ($otherServer == '__localhost__') {
            return $this->ask("What is the path of the file/folder on your computer?");
        }

        return $this->ask("What is the path of the file/folder on the other server? ({$otherServer})");
    }

    protected function askForRemotePath()
    {
        return $this->ask("What is the path of the file/folder on the remote server?");
    }

    protected function askForOtherServer()
    {
        return $this->ask("What is the path of the other server? Should be in the format username@hostname. Leave blank for this computer", '__localhost__');
    }
}
