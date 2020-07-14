<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\NetsellsFile;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;

class Ecs
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function authenticateDocker(Command $command): bool
    {
        $awsAccountId = $this->aws->helpers->console()->handleOverridesAndFallbacks($command->option('aws-account-id'), NetsellsFile::DOCKER_AWS_ACCOUNT_ID, Aws::DEFAULT_ACCOUNT_ID);
        $awsRegion = $this->aws->helpers->console()->handleOverridesAndFallbacks($command->option('aws-region'), NetsellsFile::DOCKER_AWS_REGION, Aws::DEFAULT_REGION);

        $process = $this->aws->newProcess($command, [
            'ecr', 'get-login-password',
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {
            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to get docker password from AWS.");
            return false;
        }

        $password = $process->getOutput();

        $process = new Process([
            'docker', 'login',
            "--username=AWS",
            "--password={$password}",
            "{$awsAccountId}.dkr.ecr.{$awsRegion}.amazonaws.com"
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {
            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to login to docker.");
            return false;
        }

        return true;
    }

    public function getTaskDefinition(Command $command, $name): ?array
    {
        $process = $this->aws->newProcess($command, [
            'ecs', 'describe-task-definition', "--task-definition={$name}",
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {
            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to get task definition [{$name}] from AWS.");
            return null;
        }

        return json_decode($process->getOutput(), true);
    }

    public function registerTaskDefinition(Command $command, string $taskDefinitionJson): ?array
    {
        $process = $this->aws->newProcess($command, [
            'ecs', 'register-task-definition', "--cli-input-json", $taskDefinitionJson,
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {
            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to register task definition in AWS.");
            return null;
        }

        return json_decode($process->getOutput(), true);
    }

    public function updateService(Command $command, string $clusterName, string $serviceName, string $taskDefinition): ?array
    {
        $process = $this->aws->newProcess($command, [
            'ecs', 'update-service',
            "--cluster={$clusterName}",
            "--service={$serviceName}",
            "--task-definition={$taskDefinition}",
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {
            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to update service in AWS.");
            return null;
        }

        return json_decode($process->getOutput(), true);
    }

    public function runTaskWithCommand(Command $command, string $clusterName, string $taskDefinition, array $migrateCommand, string $container): void
    {
        $overrides = json_encode([
            'containerOverrides' => [
                [
                    'name' => $container,
                    'command' => $migrateCommand,
                ]
            ]
        ]);

        $process = $this->aws->newProcess($command, [
            'ecs', 'run-task',
            "--cluster={$clusterName}",
            "--overrides={$overrides}",
            "--task-definition={$taskDefinition}",
        ]);

        $process->start();
        $process->wait();

        if ($process->getExitCode() !== 0) {
            foreach ($process as $data) {
                echo $data;
            }

            $command->error("Unable to start migration task in AWS.");
            return;
        }

        return;
    }
}
