<?php

namespace App\Commands;

use App\Helpers\Aws;
use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use Symfony\Component\Yaml\Yaml;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;

class DeployEcsServiceUpdate extends Command
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

    /** @var Helpers $helpers */
    protected $helpers;

    protected $clusterName;
    protected $serviceName;
    protected $taskDefinitionName;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
        parent::__construct();
    }

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('tag', null, InputOption::VALUE_OPTIONAL, 'The tag that should be built with the images. Defaults to the current commit SHA'),
            new InputOption('ecs-service', null, InputOption::VALUE_OPTIONAL, 'The ECS service name'),
            new InputOption('ecs-cluster', null, InputOption::VALUE_OPTIONAL, 'The ECS cluster name'),
            new InputOption('ecs-task-definition', null, InputOption::VALUE_OPTIONAL, 'The ECS task definition name'),
            new InputOption('migrate-container', null, InputOption::VALUE_OPTIONAL, 'The container to run the migration on'),
            new InputOption('migrate-command', null, InputOption::VALUE_OPTIONAL, 'The migration command to run'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['aws'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $tag = trim($this->option('tag') ?: $this->helpers->git()->currentSha());

        if (!$this->determineRequiredOptions()) {
            return 1;
        }

        $taskDefinition = $this->helpers->aws()->ecs()->getTaskDefinition($this, $this->taskDefinitionName);

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

        $newTaskDefinition = $this->helpers->aws()->ecs()->registerTaskDefinition($this, $taskDefinitionJson);

        if (!$newTaskDefinition) {
            return 1;
        }

        $newTaskDefinitionString = $this->prepareNewTaskDefinitionRevisionString($newTaskDefinition);
        $this->line("Task definition updated to revision {$newTaskDefinitionString}");

        $this->helpers->aws()->ecs()->updateService($this, $this->clusterName, $this->serviceName, $newTaskDefinitionString);
        $this->line("Service updated to task definition {$newTaskDefinitionString}");

        $migrateCommand = $this->helpers->console()->handleOverridesAndFallbacks($this->option('migrate-command'), NetsellsFile::DOCKER_ECS_MIGRATE_COMMAND);
        $migrateContainer = $this->helpers->console()->handleOverridesAndFallbacks($this->option('migrate-container'), NetsellsFile::DOCKER_ECS_MIGRATE_CONTAINER);

        if ($migrateCommand && $migrateContainer) {
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
        unset($taskDefinition['taskDefinition']['taskDefinitionArn']);
        unset($taskDefinition['taskDefinition']['revision']);
        unset($taskDefinition['taskDefinition']['status']);
        unset($taskDefinition['taskDefinition']['requiresAttributes']);
        unset($taskDefinition['taskDefinition']['compatibilities']);

        return json_encode($taskDefinition['taskDefinition']);
    }

    protected function determineControllingTags($taskDefinition, array $targetImages): array
    {
        // Get all images
        $images = data_get($taskDefinition, 'taskDefinition.containerDefinitions.*.image');

        // Seperate out just the tags
        return array_filter($images, function ($image) use ($targetImages) {
            list($image, $tag) = explode(':', $image);

            return in_array($image, $targetImages);
        });
    }

    protected function generateDeploymentUrl(): string
    {
        $awsRegion = $this->helpers->console()->handleOverridesAndFallbacks($this->option('aws-region'), NetsellsFile::DOCKER_AWS_REGION, Aws::DEFAULT_REGION);
        return "https://{$awsRegion}.console.aws.amazon.com/ecs/home#/clusters/{$this->clusterName}/services/{$this->serviceName}/deployments";
    }

    protected function determineRequiredOptions(): bool
    {
        $this->serviceName = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('ecs-service'),
            NetsellsFile::DOCKER_ECS_SERVICE
        );

        $this->clusterName = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('ecs-cluster'),
            NetsellsFile::DOCKER_ECS_CLUSTER
        );

        $this->taskDefinitionName = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('ecs-task-definition'),
            NetsellsFile::DOCKER_ECS_TASK_DEFINITION
        );

        if (empty($this->serviceName) || empty($this->clusterName) || empty($this->taskDefinitionName)) {
            $this->comment("The deploy ECS service update command requires you specify service, cluster and task definition in either the .netsells.yml file or via arguments.");
            return false;
        }

        return true;
    }

    protected function gatherTargetImages(): array
    {
        $files = ['docker-compose.yml', 'docker-compose.prod.yml'];
        $combinedYml = [];

        foreach ($files as $file) {
            try {
                $combinedYml = array_merge_recursive($combinedYml, Yaml::parse(file_get_contents($file)));
            } catch (ParseException $exception) {
                //
            }
        }

        return array_filter(array_values(array_map(function ($service) {
            if (!isset($service['image'])) {
                return null;
            }

            $parts = explode(':', $service['image']);
            return $parts[0];
        }, $combinedYml['services'])));
    }

    protected function updateControllingTagWithNewTag($controllingTag, $newTag): string
    {
        list($image, $tag) = explode(':', $controllingTag);

        return "{$image}:{$newTag}";
    }
}
