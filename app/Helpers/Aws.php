<?php

namespace App\Helpers;

use App\Commands\Console\DockerOption;
use App\Helpers\Aws\S3;
use App\Helpers\Aws\Ec2;
use App\Helpers\Aws\Ecs;
use App\Helpers\Aws\Iam;
use App\Helpers\Aws\Ssm;
use App\Commands\Console\InputOption;
use Aws\Credentials\CredentialProvider;
use LaravelZero\Framework\Commands\Command;

class Aws extends BaseHelper
{
    public const DEFAULT_REGION = 'eu-west-2';
    public const DEFAULT_ACCOUNT_ID = '422860057079';

    /** @var Helpers $helpers */
    private $helpers;

    public function __construct(Command $command, Helpers $helpers)
    {
        parent::__construct($command);
        $this->helpers = $helpers;
    }

    public function getHelpers(): Helpers
    {
        return $this->helpers;
    }

    public function getCommand(): ?Command
    {
        return $this->command;
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

    public function newProcess(array $args = []): Process
    {
        return $this->helpers->process()
            ->withCommand(array_merge(['aws'], $args, $this->standardCliArguments()));
    }

    public function standardSdkArguments(): array
    {
        return [
            'region' => $this->command->option('aws-region'),
            'profile' => $this->command->option('aws-profile'),
            'version' => 'latest',
        ];

        if (!empty(getenv('AWS_ACCESS_KEY_ID')) && !empty(getenv('AWS_SECRET_ACCESS_KEY')) && !empty(getenv('AWS_SESSION_TOKEN'))) {
            unset($arguments['profile']);
            $arguments['credentials'] = CredentialProvider::env();
        }

        return $arguments;
    }

    public function standardCliArguments(): array
    {
        $awsRegion = $this->command->option('aws-region');

        $return = [
            "--region={$awsRegion}",
        ];

        $awsProfile = $this->command->option('aws-profile');

        if ($awsProfile) {
            $return[] = "--profile={$awsProfile}";
        }

        return $return;
    }

    public function commonConsoleOptions(): array
    {
        return [
            new DockerOption('aws-region', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS region', Aws::DEFAULT_REGION),
            new DockerOption('aws-account-id', null, InputOption::VALUE_OPTIONAL, 'Override the default AWS account ID', Aws::DEFAULT_ACCOUNT_ID),
            new InputOption('aws-profile', null, InputOption::VALUE_OPTIONAL, 'Override the AWS profile to use'),
        ];
    }

    public function commonDockerOptions(): array
    {
        return [
            new DockerOption('tag', null, DockerOption::VALUE_OPTIONAL, 'The tag that should be used for the images. Defaults to the current commit SHA or latest.', $this->helpers->git()->currentSha() ?: 'latest'),
            new DockerOption('tag-prefix', null, DockerOption::VALUE_OPTIONAL, 'The tag prefix that should be used for the images. Defaults to null'),
            new DockerOption('service', null, DockerOption::VALUE_OPTIONAL | DockerOption::VALUE_IS_ARRAY, 'One or more services that should be dealt with. Not defining this will fallback to services defined in the .netsells.yml config file, and if not defined, will consider all services.', []),
            new InputOption('environment', null, InputOption::VALUE_OPTIONAL, 'The destination environment for the images'),
        ];
    }
}
