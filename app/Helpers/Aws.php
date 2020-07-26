<?php

namespace App\Helpers;

use App\Helpers\Aws\Ec2;
use App\Helpers\Aws\Ecs;
use App\Helpers\Aws\Ssm;
use App\Helpers\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class Aws
{
    public const DEFAULT_REGION = 'eu-west-2';
    public const DEFAULT_ACCOUNT_ID = '422860057079';

    /** @var Helpers $helpers */
    public $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    public function ecs(): Ecs
    {
        return new Ecs($this);
    }

    public function ssm(): Ssm
    {
        return new Ssm($this);
    }

    public function ec2(): Ec2
    {
        return new Ec2($this);
    }

    public function newProcess(Command $command, array $args = []): Process
    {
        return $this->helpers->process()->withCommand(array_merge(['aws'], $args, $this->standardCliArguments($command)));
    }

    public function standardCliArguments(Command $command): array
    {
        $awsRegion = $this->helpers->console()->handleOverridesAndFallbacks($command->option('aws-region'), NetsellsFile::DOCKER_AWS_REGION, Aws::DEFAULT_REGION);

        $return = [
            "--region={$awsRegion}",
        ];

        if ($awsProfile = $command->option('aws-profile')) {
            $return[] = "--profile={$awsProfile}";
        }

        return $return;
    }

    public static function commonConsoleOptions(): array
    {
        return [
            new InputOption('aws-region', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS region'),
            new InputOption('aws-account-id', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS account ID'),
            new InputOption('aws-profile', null, InputOption::VALUE_OPTIONAL, 'Override the AWS profile to use'),
        ];
    }

}
