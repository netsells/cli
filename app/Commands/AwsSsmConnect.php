<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AwsSsmConnect extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ssm:connect';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Connect to an server via SSH (Use --tunnel to establish an SSH tunnel)';

    protected $tempKeyName = 'netsells-cli-ssm-ssh-tmp';

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('instance-id', null, InputOption::VALUE_OPTIONAL, 'The instance ID to connect to'),
            new InputOption('username', null, InputOption::VALUE_OPTIONAL, 'The username connect with'),
            new InputOption('tunnel', null, InputOption::VALUE_NONE, 'Sets up an SSH tunnel'),
            new InputOption('tunnel-remote-server', null, InputOption::VALUE_OPTIONAL, 'The SSH tunnel remote server'),
            new InputOption('tunnel-remote-port', null, InputOption::VALUE_OPTIONAL, 'The SSH tunnel remote port'),
            new InputOption('tunnel-local-port', null, InputOption::VALUE_OPTIONAL, 'The SSH tunnel local port'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['aws', 'ssh', 'ssh-keygen'];

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

        $options = [
            '-o', 'IdentityFile ~/.ssh/netsells-cli-ssm-ssh-tmp',
            '-o', 'IdentitiesOnly yes',
            '-o', 'GSSAPIAuthentication no',
            '-o', 'PasswordAuthentication no',
        ];

        if ($this->option('tunnel')) {
            $rebuildOptions[] = '--tunnel';

            $tunnelRemoteServer = trim($this->option('tunnel-remote-server') ?: $this->askForTunnelRemoteServer());
            $tunnelRemotePort = trim($this->option('tunnel-remote-port') ?: $this->askForTunnelRemotePort());
            $tunnelLocalPort = trim($this->option('tunnel-local-port') ?: $this->askForTunnelLocalPort());

            $options[] = '-N';
            $options[] = '-L';
            $options[] = sprintf('%s:%s:%s', $tunnelLocalPort, $tunnelRemoteServer, $tunnelRemotePort);

            $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'tunnel-remote-server', $tunnelRemoteServer);
            $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'tunnel-remote-port', $tunnelRemotePort);
            $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'tunnel-local-port', $tunnelLocalPort);
        }

        $command = $this->generateRemoteCommand($username, $this->generateTempSshKey());

        $this->info("Sending a temporary SSH key to the server...", OutputInterface::VERBOSITY_VERBOSE);
        if (!$this->helpers->aws()->ssm()->sendRemoteCommand($instanceId, $command)) {
            $this->error('Failed to send SSH key to server');
            return 1;
        }

        $sessionCommand = $this->helpers->aws()->ssm()->startSessionProcess($instanceId);
        $sessionCommandString = implode(' ', $sessionCommand->getArguments());

        array_unshift($options, '-o', "ProxyCommand {$sessionCommandString}");

        if ($this->option('tunnel')) {
            $this->sendReRunHelper($rebuildOptions);

            $this->info("Establishing an SSH tunnel connection with {$instanceId}, this may take a few seconds...");

            $this->comment("You should try and connect to 127.0.0.1:{$tunnelLocalPort} - there will be no futher output after this message.");
        } else {
            $this->info("Establishing an SSH connection with {$instanceId}, this may take a few seconds...");
        }

        try {
            $this->helpers->process()->withCommand(array_merge([
                'ssh',
            ], $options, [
                sprintf("%s@%s", $username, $instanceId)
            ]))
            ->withTimeout(null)
            ->withProcessModifications(function ($process) {
                $process->setTty(Process::isTtySupported());
                $process->setIdleTimeout(null);
            })
            ->run();
        } catch (ProcessFailed $e) {
            $this->info(' ');
            $this->error("SSH command exited with an exit code of " . $e->getCode());
        }

        if (!$this->option('tunnel')) {
            $this->info(' ');
            $this->info(' ');
            $this->sendReRunHelper($rebuildOptions);
        }
    }

    protected function sendReRunHelper($rebuildOptions): void
    {
        $this->info("You can run this command again without having to go through options using this:");
        $this->info(' ');
        $this->comment("netsells aws:ssm:connect " . implode(' ', $rebuildOptions));
        $this->info(' ');
    }

    protected function appendResolvedArgument($array, $key, $localValue = null): array
    {
        if ($this->option($key) || $localValue) {
            $array[] = "--{$key}";
            $array[] = $this->option($key) ?: $localValue;
        }

        return $array;
    }

    protected function askForTunnelRemoteServer()
    {
        return $this->ask("What is the remote server URL/IP for the SSH tunnel?");
    }

    protected function askForTunnelRemotePort()
    {
        return $this->ask("What is the remote port for the SSH tunnel?");
    }

    protected function askForTunnelLocalPort()
    {
        return $this->ask("What is the local port for the SSH tunnel?");
    }

    protected function askForUsername()
    {
        // TODO: Try and figure this out based on the AMI ID - https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/connection-prereqs.html#connection-prereqs-get-info-about-instance
        return $this->askWithCompletion("What user do you want to connect with?", ['ubuntu', 'ec2-user', 'admin', 'root']);
    }

    protected function askForInstanceId()
    {
        $instances = $this->helpers->aws()->ec2()->listInstances(
            "Reservations[*].Instances[*].{InstanceId:InstanceId,Name:Tags[?Key=='Name']|[0].Value,PrivateIpAddress:PrivateIpAddress,InstanceType:InstanceType}"
        )->flatten(1);

        if (is_null($instances)) {
            $this->error("Could not get instances.");
        }

        $instances = $instances->keyBy('InstanceId')->map(function ($instance) {
            return "[{$instance['InstanceId']}] {$instance['Name']} - {$instance['PrivateIpAddress']} {$instance['InstanceType']}";
        });

        return $this->menu("Choose an instance to connect to...", $instances->toArray())->open();
    }

    private function generateTempSshKey()
    {
        $sshDir = $_SERVER['HOME'] . '/.ssh/';
        $keyName = $sshDir . $this->tempKeyName;
        $pubKeyName = "{$keyName}.pub";

        if (file_exists($keyName)) {
            unlink($keyName);
        }

        if (file_exists($pubKeyName)) {
            unlink($pubKeyName);
        }

        try {
            $this->helpers->process()
                ->withCommand([
                    'ssh-keygen',
                    '-t', 'ed25519',
                    '-N', "",
                    '-f', $keyName,
                    '-C', "netsells-cli-ssm-ssh-session"
                ])
                ->run();
        } catch (ProcessFailed $e) {
            $this->error("Unable to generate temp ssh key.");
            return false;
        }

        return trim(file_get_contents($pubKeyName));
    }

    private function generateRemoteCommand($username, $key)
    {
        // Borrowed from https://github.com/elpy1/ssh-over-ssm/blob/master/ssh-ssm.sh#L10
        return trim(<<<EOF
            u=\$(getent passwd $username) && x=\$(echo \$u |cut -d: -f6) || exit 1
            install -d -m700 -o$username \${x}/.ssh; grep '$key' \${x}/.ssh/authorized_keys && exit 1
            printf '\n$key'|tee -a \${x}/.ssh/authorized_keys && sleep 15
            sed -i s,'$key',, \${x}/.ssh/authorized_keys && sed -i '/^$/d' \${x}/.ssh/authorized_keys
EOF);
    }
}
