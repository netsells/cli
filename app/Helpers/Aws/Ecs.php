<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\Process;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
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
                '--username', 'AWS',
                '--password', $password,
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

    public function listClusters(Command $command): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'list-clusters',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get clusters from AWS.");
            return null;
        }

        return data_get(json_decode($processOutput, true), 'clusterArns');
    }

    public function listServices(Command $command, string $clusterName): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'list-services', "--cluster", $clusterName,
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get services [{$clusterName}] from AWS.");
            return null;
        }

        return data_get(json_decode($processOutput, true), 'serviceArns');
    }

    public function listTasks(Command $command, string $clusterName, string $serviceName): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'list-tasks', "--cluster", $clusterName, "--service-name", $serviceName,
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get tasks [{$clusterName} - {$serviceName}] from AWS.");
            return null;
        }

        return data_get(json_decode($processOutput, true), 'taskArns');
    }

    public function listContainers(Command $command, string $clusterName, string $taskId): ?array
    {
        try {
            $processOutput = $this->aws->newProcess($command, [
                'ecs', 'describe-tasks', "--cluster", $clusterName, '--task', $taskId,
                '--query', 'tasks[0].containers[].name',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get tasks [{$clusterName} - {$taskId}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function getTasks(Command $command, string $clusterName, array $taskArns): ?array
    {
        $commands = array_merge([
            'ecs', 'describe-tasks', "--cluster", $clusterName, "--tasks",
        ], $taskArns);

        try {
            $processOutput = $this->aws->newProcess($command, $commands)
            ->run();
        } catch (ProcessFailed $e) {
            // $command->error("Unable to get tasks [{$taskArns}] from AWS.");
            return null;
        }

        return data_get(json_decode($processOutput, true), 'tasks');
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

    public function startCommandExecution(Command $command, string $cluster, string $taskId, string $containerName, string $shellCommand): Process
    {
        return $this->aws->newProcess($command, [
            'ecs', 'execute-command',
            '--cluster', $cluster,
            '--task', $taskId,
            '--container', $containerName,
            '--command', $shellCommand,
            '--interactive',
        ]);
    }
}
