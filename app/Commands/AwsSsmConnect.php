<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;
use App\Helpers\Helpers;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class AwsSsmConnect extends Command
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
    protected $description = 'Connect to an server via SSH';

    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
        parent::__construct();
    }

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('instance-id', null, InputOption::VALUE_OPTIONAL, 'The instance ID to connect to'),
            new InputOption('username', null, InputOption::VALUE_OPTIONAL, 'The username connect with'),
            new InputOption('tunnel', null, InputOption::VALUE_NONE, 'Sets up an SSH tunnel'),
            new InputOption('tunnel-remote-server', null, InputOption::VALUE_OPTIONAL, 'The SSH tunnel remote server'),
            new InputOption('tunnel-remote-port', null, InputOption::VALUE_OPTIONAL, 'The SSH tunnel remote port'),
            new InputOption('tunnel-local-port', null, InputOption::VALUE_OPTIONAL, 'The SSH tunnel local port'),
            new InputOption('show-command', null, InputOption::VALUE_NONE, 'Shows the SSH command instead of running it'),
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

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $rebuildOptions = [];

        $instanceId = $this->option('instance-id') ?: $this->askForInstanceId();
        $username = $this->option('username') ?: $this->askForUsername();

        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'username', $username);
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'instance-id', $instanceId);

        $awsOptions = implode(' ', [
            '--aws-profile', $this->option('aws-profile') ?: 'default',
            '--aws-region', $this->option('aws-region') ?: 'eu-west-2',
        ]);

        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'aws-profile');
        $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'aws-region');

        $options = [
            '-o', 'IdentityFile ~/.ssh/netsells-cli-ssm-ssh-tmp',
            '-o', 'IdentitiesOnly yes',
            '-o', 'GSSAPIAuthentication no',
            '-o', 'PasswordAuthentication no',
            '-o', "ProxyCommand bash -c \"$(netsells aws:ssm:start-session {$username} {$instanceId} {$awsOptions})\"",
            '-vvv',
        ];

        if ($this->option('tunnel')) {
            $tunnelRemoteServer = $this->option('tunnel-remote-server') ?: $this->askForTunnelRemoteServer();
            $tunnelRemotePort = $this->option('tunnel-remote-port') ?: $this->askForTunnelRemotePort();
            $tunnelLocalPort = $this->option('tunnel-local-port') ?: $this->askForTunnelLocalPort();

            $options[] = '-N';
            $options[] = '-L';
            $options[] = sprintf('%s:%s:%s', $tunnelLocalPort, $tunnelRemoteServer, $tunnelRemotePort);

            $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'tunnel-remote-server', $tunnelRemoteServer);
            $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'tunnel-remote-port', $tunnelRemotePort);
            $rebuildOptions = $this->appendResolvedArgument($rebuildOptions, 'tunnel-local-port', $tunnelLocalPort);
        }

        if ($this->option('show-command')) {
            $command = sprintf("ssh %s %s@%s", implode(' ', $options), $username, $instanceId);

            $this->info("Run the following command to connect:");
            $this->info(' ');
            $this->comment($command);
            $this->info(' ');
        } else {
            $this->info("Establishing an SSH connection with {$instanceId}, this may take a few seconds...");
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
        }

        $this->info(' ');
        $this->info(' ');
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
            $this,
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
}
