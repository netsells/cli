<?php

namespace App\Helpers;

use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class Aws
{
    protected const DEFAULT_REGION = 'eu-west-2';
    protected const DEFAULT_ACCOUNT_ID = '422860057079';
    protected const DEFAULT_PROFILE = 'default';

    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    public static function commonConsoleOptions(): array
    {
        return [
            new InputOption('aws-region', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS region'),
            new InputOption('aws-account-id', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS account ID'),
            new InputOption('aws-profile', null, InputOption::VALUE_OPTIONAL, 'Override the AWS profile to use'),
        ];
    }

    public function authenticateDocker(Command $command): bool
    {
        $awsRegion = $this->helpers->console()->handleOverridesAndFallbacks($command->option('aws-region'), NetsellsFile::DOCKER_AWS_REGION, self::DEFAULT_REGION);
        $awsAccountId = $this->helpers->console()->handleOverridesAndFallbacks($command->option('aws-account-id'), NetsellsFile::DOCKER_AWS_ACCOUNT_ID, self::DEFAULT_ACCOUNT_ID);
        $awsProfile = $this->helpers->console()->handleOverridesAndFallbacks($command->option('aws-profile'), null, self::DEFAULT_PROFILE);

        $process = new Process([
            'aws', 'ecr', 'get-login-password',
            "--region={$awsRegion}",
            "--profile={$awsProfile}",
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {

            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to get docker password from AWS");
            return false;
        }

        $password = $process->getOutput();

        $process = new Process([
            'docker', 'login',
            "--username=AWS",
            "--password={$password}",
            "{$awsAccountId}.dkr.ecr.eu-west-2.amazonaws.com"
        ]);

        $process->start();

        foreach ($process as $data) {
            echo $data;
        }

        return true;
    }

}