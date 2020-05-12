<?php

namespace App\Commands;

use App\Helpers\Aws;
use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class DeployEcsServiceUpdate extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:deploy-ecs-service-update';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Updates ECS task definition with new image references and updates the service';

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
        $controllingTag = $this->determineControllingTag($taskDefinition);

        $this->line("Updating all images from {$controllingTag} to {$tag} in {$this->taskDefinitionName}");
        $taskDefinition = $this->replaceOldTagsWithNew($taskDefinition, $controllingTag, $tag);

        $taskDefinitionJson = $this->prepareTaskDefinitionForRegister($taskDefinition);

        $newTaskDefinition = $this->helpers->aws()->ecs()->registerTaskDefinition($this, $taskDefinitionJson);
        $newTaskDefinitionString = $this->prepareNewTaskDefinitionRevisionString($newTaskDefinition);
        $this->line("Task definition updated to revision {$newTaskDefinitionString}");

        $this->helpers->aws()->ecs()->updateService($this, $this->clusterName, $this->serviceName, $newTaskDefinitionString);
        $this->line("Service updated to task definition {$newTaskDefinitionString}");

        $this->info("Successfully deployed to ECS, deployment can be seen at " . $this->generateDeploymentUrl());
    }

    protected function prepareNewTaskDefinitionRevisionString($newTaskDefinition): string
    {
        $newTaskDefinitionRevision = data_get($newTaskDefinition, 'taskDefinition.revision');
        return "{$this->taskDefinitionName}:{$newTaskDefinitionRevision}";
    }

    protected function replaceOldTagsWithNew($taskDefinition, $oldTag, $newTag): array
    {
        $taskDefinitionJson = json_encode($taskDefinition);
        $taskDefinitionJson = str_replace($oldTag, $newTag, $taskDefinitionJson);
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

    protected function determineControllingTag($taskDefinition): string
    {
        // Get all images
        $images = data_get($taskDefinition, 'taskDefinition.containerDefinitions.*.image');

        // Seperate out just the tags
        $oldShas = array_map(function ($image) {
            list($image, $tag) = explode(':', $image);
            return $tag;
        }, $images);

        // Count how many times each happens
        $occurenceCounts = array_count_values($oldShas);

        // Sort by most occured
        arsort($occurenceCounts);

        // Grab the most occured tag
        return array_key_first($occurenceCounts);
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
}
