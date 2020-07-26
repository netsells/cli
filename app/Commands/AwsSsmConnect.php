<?php

namespace App\Commands;

use App\Helpers\Helpers;
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
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
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

        $instanceId = $this->menu("Choose an instance to connect to...", $instances->toArray())->open();

        // TODO: Try and figure this out based on the AMI ID - https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/connection-prereqs.html#connection-prereqs-get-info-about-instance
        $username = $this->askWithCompletion("What user do you want to connect with?", ['ubuntu', 'ec2-user', 'admin', 'root']);

        $awsOptions = implode(' ', [
            '--aws-profile', $this->option('aws-profile') ?: 'default',
            '--aws-region', $this->option('aws-region') ?: 'eu-west-2',
        ]);

        $options = [
            '-o', '"IdentityFile ~/.ssh/netsells-cli-ssm-ssh-tmp"',
            '-o', '"IdentitiesOnly yes"',
            '-o', '"GSSAPIAuthentication no"',
            '-o', '"PasswordAuthentication no"',
            '-o', "\"ProxyCommand bash -c \\\"$(php /Users/sam/code/desktop/netsells-cli/netsells aws:ssm:start-session {$username} {$instanceId} {$awsOptions})\\\"\"",
        ];

        $command = sprintf("ssh %s %s@%s", implode(' ', $options), $username, $instanceId);

        $this->info("Run the following command to connect:");
        $this->info(' ');
        $this->comment($command);
        $this->info(' ');
    }
}
