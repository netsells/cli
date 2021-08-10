<?php

namespace App\Commands;

use App\Commands\Console\DockerOption;
use App\Commands\Console\InputOption;

class DeployEcsServiceUpdate extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:aws:deploy-update';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Updates task definition and service';

    protected $clusterName;
    protected $serviceName;
    protected $taskDefinitionName;

    public function configure()
    {
        $this->setDefinition(array_merge([
            new DockerOption('tag', null, DockerOption::VALUE_OPTIONAL, 'The tag that should be built with the images. Defaults to the current commit SHA', $this->helpers->git()->currentSha()),
            new DockerOption('tag-prefix', null, DockerOption::VALUE_OPTIONAL, 'The tag prefix that should be built with the images. Defaults to null'),
            new DockerOption('ecs-service', null, DockerOption::VALUE_OPTIONAL, 'The ECS service name'),
            new DockerOption('ecs-cluster', null, DockerOption::VALUE_OPTIONAL, 'The ECS cluster name'),
            new DockerOption('ecs-task-definition', null, DockerOption::VALUE_OPTIONAL, 'The ECS task definition name'),
            new DockerOption('migrate-container', null, DockerOption::VALUE_OPTIONAL, 'The container to run the migration on'),
            new DockerOption('migrate-command', null, DockerOption::VALUE_OPTIONAL, 'The migration command to run'),
            new DockerOption('service', null, DockerOption::VALUE_OPTIONAL | DockerOption::VALUE_IS_ARRAY, 'The service that should be deployed. Not defining this will deploy all services in .netsells.yml', []),
            new InputOption('environment', null, InputOption::VALUE_OPTIONAL, 'The destination environment for the images'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['aws', 'docker-compose'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            return 1;
        }

        $tag = $this->helpers->docker()->prefixedTag($this);

        if (!$this->determineRequiredOptions()) {
            return 1;
        }

        $taskDefinition = $this->helpers->aws()->ecs()->getTaskDefinition($this->taskDefinitionName);

        if (!$taskDefinition) {
            return 1;
        }

        $targetImages = $this->gatherTargetImages();

        $controllingTags = $this->determineControllingTags($taskDefinition, $targetImages);

        foreach ($controllingTags as $controllingTag) {
            $newTag = $this->updateControllingTagWithNewTag($controllingTag, $tag);
            $this->line("Updating images {$controllingTag} to {$newTag} in {$this->taskDefinitionName}");
            $taskDefinition = $this->replaceOldTagWithNew($taskDefinition, $controllingTag, $newTag);
        }

        $taskDefinitionJson = $this->prepareTaskDefinitionForRegister($taskDefinition);

        $newTaskDefinition = $this->helpers->aws()->ecs()->registerTaskDefinition($taskDefinitionJson);

        if (!$newTaskDefinition) {
            return 1;
        }

        $newTaskDefinitionString = $this->prepareNewTaskDefinitionRevisionString($newTaskDefinition);
        $this->line("Task definition updated to revision {$newTaskDefinitionString}");

        $this->helpers->aws()->ecs()->updateService($this->clusterName, $this->serviceName, $newTaskDefinitionString);
        $this->line("Service updated to task definition {$newTaskDefinitionString}");

        $migrateCommand = $this->option('migrate-command');
        $migrateContainer = $this->option('migrate-container');

        if ($migrateCommand && $migrateContainer) {
            $this->error('The migrate option will be deprecated in the next major version.');
            $this->line("Migrate command detected, running as a one-off task.");

            $this->runMigrateCommand($migrateCommand, $newTaskDefinitionString, $migrateContainer);
        }

        $this->info("Successfully deployed to ECS, deployment can be seen at " . $this->generateDeploymentUrl());
    }

    protected function runMigrateCommand($migrateCommand, string $newTaskDefinitionString, string $container): void
    {
        $consts = [
            'LARAVEL_DATABASE_MIGRATIONS' => ['php', 'artisan', 'migrate', '--force'],
        ];

        if (is_string($migrateCommand)) {
            foreach ($consts as $const => $value) {
                if ($migrateCommand === $const) {
                    $migrateCommand = $value;
                    continue;
                }
            }
        }

        $this->helpers->aws()->ecs()->runTaskWithCommand(
            $this,
            $this->clusterName,
            $newTaskDefinitionString,
            $migrateCommand,
            $container
        );
    }

    protected function prepareNewTaskDefinitionRevisionString($newTaskDefinition): string
    {
        $newTaskDefinitionRevision = data_get($newTaskDefinition, 'taskDefinition.revision');
        return "{$this->taskDefinitionName}:{$newTaskDefinitionRevision}";
    }

    protected function replaceOldTagWithNew($taskDefinition, $oldTag, $newTag): array
    {
        $taskDefinitionJson = json_encode($taskDefinition);
        $taskDefinitionJson = str_replace(json_encode($oldTag), json_encode($newTag), $taskDefinitionJson);
        return json_decode($taskDefinitionJson, true);
    }

    protected function prepareTaskDefinitionForRegister($taskDefinition): string
    {
        // ECS does not want any of this back
        $excludeKeys = [
            'taskDefinitionArn',
            'revision',
            'status',
            'requiresAttributes',
            'compatibilities',
            'registeredAt',
            'registeredBy',
        ];

        foreach ($excludeKeys as $key) {
            unset($taskDefinition['taskDefinition'][$key]);
        }

        return json_encode($taskDefinition['taskDefinition']);
    }

    protected function determineControllingTags($taskDefinition, array $targetImages): array
    {
        // Get all images
        $images = data_get($taskDefinition, 'taskDefinition.containerDefinitions.*.image');

        // Seperate out the tags we want from the target images
        return collect($images)
            ->filter(function ($image) use ($targetImages) {
                [$image, $tag] = explode(':', $image);

                return in_array($image, $targetImages);
            })
            ->values()
            ->all();
    }

    protected function generateDeploymentUrl(): string
    {
        $awsRegion = $this->option('aws-region');
        return "https://{$awsRegion}.console.aws.amazon.com/ecs/home#/clusters/{$this->clusterName}/services/{$this->serviceName}/deployments";
    }

    protected function determineRequiredOptions(): bool
    {
        $this->serviceName = $this->option('ecs-service');
        $this->clusterName = $this->option('ecs-cluster');
        $this->taskDefinitionName = $this->option('ecs-task-definition');

        if (empty($this->serviceName) || empty($this->clusterName) || empty($this->taskDefinitionName)) {
            $this->comment("The deploy ECS service update command requires you specify service, cluster and task definition in either the .netsells.yml file or via arguments.");
            return false;
        }

        return true;
    }

    protected function gatherTargetImages(): array
    {
        $imageUrls = $this->helpers->docker()->getImageUrlsForServices($this->option('service'));

        return collect($imageUrls)
            ->map(fn (string $url) => explode(':', $url)[0])
            ->unique()
            ->all();
    }

    protected function updateControllingTagWithNewTag($controllingTag, $newTag): string
    {
        [$image, $tag] = explode(':', $controllingTag);

        return "{$image}:{$newTag}";
    }
}
