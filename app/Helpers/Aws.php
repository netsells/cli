<?php

namespace App\Helpers;

use App\Helpers\Aws\S3;
use App\Helpers\Aws\Ec2;
use App\Helpers\Aws\Ecs;
use App\Helpers\Aws\Iam;
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

    public function iam(): Iam
    {
        return new Iam($this);
    }

    public function s3(): S3
    {
        return new S3($this);
    }

    public function newProcess(Command $command, array $args = []): Process
    {
        return $this->helpers->process()->withCommand(array_merge(['aws'], $args, $this->standardCliArguments($command)));
    }

    public function standardSdkArguments(Command $command): array
    {
        return [
            'region' => $command->option('aws-region'),
            'profile' => $command->option('aws-profile'),
            'version' => 'latest',
        ];
    }

    public function standardCliArguments(Command $command): array
    {
        $awsRegion = $command->option('aws-region');

        $return = [
            "--region={$awsRegion}",
        ];

        $awsProfile = $command->option('aws-profile');

        if ($awsProfile) {
            $return[] = "--profile={$awsProfile}";
        }

        return $return;
    }

    public static function commonConsoleOptions(): array
    {
        return [
            new InputOption('aws-region', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS region', Aws::DEFAULT_REGION),
            new InputOption('aws-account-id', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS account ID', Aws::DEFAULT_ACCOUNT_ID),
            new InputOption('aws-profile', null, InputOption::VALUE_OPTIONAL, 'Override the AWS profile to use', 'default'),
        ];
    }

}
