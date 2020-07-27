<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
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

        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecr', 'get-login-password',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get docker password from AWS.");
            return false;
        }

        $password = $processOutput;

        try {
            $this->aws->helpers->process()->withCommand([
                'docker', 'login',
                "--username=AWS",
                "--password={$password}",
                "{$awsAccountId}.dkr.ecr.{$awsRegion}.amazonaws.com"
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to login to docker.");
            return false;
        }

        return true;
    }

    public function getTaskDefinition(Command $command, $name): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'describe-task-definition', "--task-definition={$name}",
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get task definition [{$name}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function registerTaskDefinition(Command $command, string $taskDefinitionJson): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'register-task-definition', "--cli-input-json", $taskDefinitionJson,
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to register task definition in AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function updateService(Command $command, string $clusterName, string $serviceName, string $taskDefinition): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'update-service',
                "--cluster={$clusterName}",
                "--service={$serviceName}",
                "--task-definition={$taskDefinition}",
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to update service in AWS.");
            return null;
        }

        return json_decode($processOutput, true);
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


        try {
            $this->aws->newProcess($command, [
                'ecs', 'run-task',
                "--cluster={$clusterName}",
                "--overrides={$overrides}",
                "--task-definition={$taskDefinition}",
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to start migration task in AWS.");
            return;
        }

        return;
    }
}
