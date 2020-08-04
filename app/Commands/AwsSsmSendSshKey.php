<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Exceptions\ProcessFailed;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputArgument;

class AwsSsmSendSshKey extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ssm:send-ssh-key';

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

        return 0;
    }

    private function generateTempSshKey()
    {
        $requiredBinaries = ['aws', 'ssh', 'ssh-keygen'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

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
