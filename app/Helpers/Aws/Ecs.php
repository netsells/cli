<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\Process;
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

    public function authenticateDocker(): bool
    {
        $awsAccountId = $this->aws->getCommand()->option('aws-account-id');
        $awsRegion = $this->aws->getCommand()->option('aws-region');

        try {
            $processOutput = $this->aws->newProcess([
                'ecr', 'get-login-password',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to get docker password from AWS.");
            return false;
        }

        $password = $processOutput;

        try {
            $this->aws->getHelpers()->process()->withCommand([
                'docker', 'login',
                '--username', 'AWS',
                '--password', $password,
                "{$awsAccountId}.dkr.ecr.{$awsRegion}.amazonaws.com"
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to login to docker.");
            return false;
        }

        return true;
    }

    public function getTaskDefinition($name): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'describe-task-definition', "--task-definition={$name}",
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to get task definition [{$name}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function listClusters(): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'list-clusters', '--query', 'clusterArns',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to get clusters from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function listServices(string $clusterName): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'list-services', '--cluster', $clusterName, '--query', 'serviceArns',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to get services [{$clusterName}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function listTasks(string $clusterName, string $serviceName): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'list-tasks', '--cluster', $clusterName, '--service-name', $serviceName, '--query', 'taskArns',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to get tasks [{$clusterName} - {$serviceName}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function describeTasks(string $clusterName, string $serviceName, array $taskIds = []): ?array
    {
        try {
            $processOutput = $this->aws->newProcess(array_merge([
                'ecs', 'describe-tasks', '--cluster', $clusterName, '--query', 'tasks', '--tasks',
            ], $taskIds))
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to describe tasks [{$clusterName} - {$serviceName}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function listContainers(string $clusterName, string $taskId): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'describe-tasks', '--cluster', $clusterName, '--task', $taskId,
                '--query', 'tasks[0].containers[].name',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to get tasks [{$clusterName} - {$taskId}] from AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function registerTaskDefinition(string $taskDefinitionJson): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'register-task-definition', '--cli-input-json', $taskDefinitionJson,
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to register task definition in AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function updateService(string $clusterName, string $serviceName, string $taskDefinition): ?array
    {
        try {
            $processOutput = $this->aws->newProcess([
                'ecs', 'update-service',
                "--cluster={$clusterName}",
                "--service={$serviceName}",
                "--task-definition={$taskDefinition}",
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $this->aws->getCommand()->error("Unable to update service in AWS.");
            return null;
        }

        return json_decode($processOutput, true);
    }

    public function startCommandExecution(string $cluster, string $taskId, string $containerName, string $shellCommand): Process
    {
        return $this->aws->newProcess([
            'ecs', 'execute-command',
            '--cluster', $cluster,
            '--task', $taskId,
            '--container', $containerName,
            '--command', $shellCommand,
            '--interactive',
        ]);
    }
}
