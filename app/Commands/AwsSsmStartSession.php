<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;
use App\Helpers\Helpers;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AwsSsmStartSession extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ssm:start-session';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Configures your machine for SSM SSH connections';

    protected $tempKeyName = 'netsells-cli-ssm-ssh-tmp';

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
            new InputArgument('username', null, InputArgument::REQUIRED, 'The username to connect with'),
            new InputArgument('instance-id', null, InputArgument::REQUIRED, 'The instance ID to connect to'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $instanceId = $this->argument('instance-id');
        $username = $this->argument('username');

        $key = $this->generateTempSshKey();

        $command = $this->generateRemoteCommand($username, $key);

        if (!$this->helpers->aws()->ssm()->sendRemoteCommand($this, $instanceId, $command)) {
            $this->error('Failed to send remote command');
            return 1;
        }

        // We can't actually run this in PHP as we can't pass the output to OpenSSH,
        // we'll echo the command instead then it can be ran by bash
        $process = $this->helpers->aws()->ssm()->startSessionProcess($this, $instanceId);

        $this->line(implode(' ', $process->getArguments()));

        return 0;
    }

    private function generateTempSshKey()
    {
        $sshDir = $_SERVER['HOME'] . '/.ssh/';

        unlink($sshDir . $this->tempKeyName);
        unlink($sshDir . $this->tempKeyName . '.pub');

        try {
            $this->helpers->process()
                ->withCommand([
                    'ssh-keygen',
                    '-t', 'ed25519',
                    '-N', "",
                    '-f', $sshDir . $this->tempKeyName,
                    '-C', "netsells-cli-ssm-ssh-session"
                ])
                ->echoLineByLineOutput(false)
                ->run();
        } catch (ProcessFailed $e) {
            $this->error("Unable to generate temp ssh key.");
            return false;
        }

        return trim(file_get_contents($sshDir . $this->tempKeyName . '.pub'));
    }

    private function generateRemoteCommand($username, $key)
    {
        // Borrowed from https://github.com/elpy1/ssh-over-ssm/blob/master/ssh-ssm.sh#L10
        return trim(<<<EOF
            u=\$(getent passwd $username) && x=\$(echo \$u |cut -d: -f6) || exit 1
            install -d -m700 -o$username \${x}/.ssh; grep '$key' \${x}/.ssh/authorized_keys && exit 1
            printf '$key'|tee -a \${x}/.ssh/authorized_keys && sleep 15
            sed -i s,'$key',, \${x}/.ssh/authorized_keys
EOF);
    }
}
