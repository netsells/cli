<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;
use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AwsEcsConnect extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ecs:connect';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Connect to an docker container in ECS via SSM';

    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
        parent::__construct();
    }

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('cluster', null, InputOption::VALUE_OPTIONAL, 'The cluster name'),
            new InputOption('service', null, InputOption::VALUE_OPTIONAL, 'The service name'),
            new InputOption('task', null, InputOption::VALUE_OPTIONAL, 'The task ID'),
            new InputOption('container', null, InputOption::VALUE_OPTIONAL, 'Container name, ie php'),
            new InputOption('command', "/bin/bash", InputOption::VALUE_OPTIONAL, 'The command to execute'),
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

        $cluster = $this->option('cluster') ?: $this->askForCluster();

        if (!$cluster) {
            $this->error('No cluster provided.');
            return 1;
        }

        $service = $this->option('service') ?: $this->askForService($cluster);

        if (!$service) {
            $this->error('No service provided.');
            return 1;
        }

        $task = $this->option('task') ?: $this->askForTask($cluster, $service);

        if (!$task) {
            $this->error('No task provided.');
            return 1;
        }

        $container = $this->option('container') ?: $this->askForContainer($cluster, $service, $task);

        if (!$container) {
            $this->error('No container provided.');
            return 1;
        }

        $shellCommand = $this->option('command') ?: $this->askForCommand($cluster, $service, $task, $container);

        if (!$shellCommand) {
            $this->error('No command provided.');
            return 1;
        }

        $command = $this->helpers->aws()->ecs()->startCommandExecution($this, $cluster, $task, $container, $shellCommand);

        $command->withTimeout(null)
            ->withProcessModifications(function ($process) {
                $process->setTty(Process::isTtySupported());
                $process->setIdleTimeout(null);
            })
            ->run();
    }

    protected function sendReRunHelper($rebuildOptions): void
    {
        $this->info("You can run this command again without having to go through options using this:");
        $this->info(' ');
        $this->comment("netsells aws:ssm:connect " . implode(' ', $rebuildOptions));
        $this->info(' ');
    }

    protected function appendResolvedArgument($array, $key, $localValue = null): array
    {
        if ($this->option($key) || $localValue) {
            $array[] = "--{$key}";
            $array[] = $this->option($key) ?: $localValue;
        }

        return $array;
    }

    protected function askForCluster()
    {
        $clusters = $this->helpers->aws()->ecs()->listClusters($this);

        if (is_null($clusters)) {
            $this->error("Could not get clusters.");
            return;
        }

        if ($cluster = $this->helpers->netsellsFile()->get(NetsellsFile::DOCKER_ECS_CLUSTER)) {
            $clusters = array_map(function ($cluster) {
                return $this->lastPartArn($cluster);
            }, $clusters);

            if (in_array($cluster, $clusters)) {
                return $cluster;
            }

            $this->error("Unable to find cluster defined in the .netsells.yml file [{$cluster}]");
        }

        // No point asking if we only have 1
        if (count($clusters) === 1) {
            return $clusters[0];
        }

        // Make the menu have nicer names
        $clusters = array_map(function ($service) {
            $parts = explode('/', $service);
            unset($parts[0]);

            return implode('/', $parts);
        }, $clusters);

        // We actually want the nice name for the cluster so we use it twice
        $clusters = array_combine($clusters, $clusters);

        return $this->menu("Choose a cluster to connect to...", $clusters)->open();
    }

    protected function askForService($cluster)
    {
        $services = $this->helpers->aws()->ecs()->listServices($this, $cluster);

        if (is_null($services)) {
            $this->error("Could not get services.");
            return;
        }

        if ($service = $this->helpers->netsellsFile()->get(NetsellsFile::DOCKER_ECS_SERVICE)) {
            $services = array_map(function ($service) {
                return $this->lastPartArn($service);
            }, $services);

            if (in_array($service, $services)) {
                return $service;
            }

            $this->error("Unable to find service defined in the .netsells.yml file [{$service}] in the cluster [{$cluster}]");
        }

        // No point asking if we only have 1
        if (count($services) === 1) {
            return $services[0];
        }

        // Make the menu have nicer names
        $serviceNames = array_map(function ($service) {
            $parts = explode('/', $service);
            unset($parts[0]);
            unset($parts[1]);

            return implode('/', $parts);
        }, $services);

        $services = array_combine($serviceNames, $serviceNames);

        return $this->menu("Choose a service to connect to [{$cluster}]...", $services)->open();
    }

    protected function askForTask($cluster, $service)
    {
        $tasks = $this->helpers->aws()->ecs()->listTasks($this, $cluster, $service);

        if (is_null($tasks)) {
            $this->error("Could not get tasks.");
            return;
        }

        // No point asking if we only have 1
        if (count($tasks) === 1) {
            return $tasks[0];
        }

        // Make the menu have nicer names
        $taskId = array_map(function ($service) {
            $parts = explode('/', $service);
            unset($parts[0]);
            unset($parts[1]);

            return implode('/', $parts);
        }, $tasks);

        $tasks = array_combine($taskId, $taskId);

        return $this->menu("Choose a task to connect to... [{$cluster} > {$service}]", $tasks)->open();
    }

    protected function askForContainer($cluster, $service, $task)
    {
        $containers = $this->helpers->aws()->ecs()->listContainers($this, $cluster, $task);

        if (is_null($containers)) {
            $this->error("Could not get containers.");
            return;
        }

        // No point asking if we only have 1
        if (count($containers) === 1) {
            return $containers[0];
        }

        $containers = array_combine($containers, $containers);

        return $this->menu("Choose a container to connect to... [{$cluster} > {$service} > {$task}]", $containers)->open();
    }

    protected function askForCommand($cluster, $service, $task, $container)
    {
        $service = $this->lastPartArn($service);
        $task = $this->lastPartArn($task);

        return $this->ask("What command should be run on the container? [{$cluster} > {$service} > {$task} > {$container}]", "/bin/bash");
    }

    private function lastPartArn($arn): string
    {
        $parts = explode('/', $arn);
        return end($parts);
    }
}
