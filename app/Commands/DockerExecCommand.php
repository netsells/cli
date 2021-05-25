<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class DockerExecCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:aws:exec';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Runs exec on docker containers in AWS';

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
            new InputOption('ecs-service', null, InputOption::VALUE_OPTIONAL, 'The ECS service name'),
            new InputOption('ecs-cluster', null, InputOption::VALUE_OPTIONAL, 'The ECS cluster name'),
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

        $cluster = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('ecs-cluster'),
            NetsellsFile::DOCKER_ECS_CLUSTER,
            []
        );

        $service = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('ecs-service'),
            NetsellsFile::DOCKER_ECS_SERVICE,
            []
        );

        /**
         * TODO: Check for responses mid-deploy, check for responses with multiple instances
         */
        $taskArns = $this->helpers->aws()->ecs()->listTasks($this, $cluster, $service);
        $tasks = $this->helpers->aws()->ecs()->getTasks($this, $cluster, $taskArns);

        if (count($tasks) == 0) {
            $this->error("No tasks found.");
            return 1;
        }

        if (count($tasks) > 1) {

            $menuOptions = collect($tasks)->map(function ($task) {
                return $task['taskDefinitionArn'];
            })->toArray();

            $chosenTask = $this->menu("Found more than one task, which one do you want to connect to?", $menuOptions)->open();
        }

        dd($cluster, $service, $tasks);
    }

    protected function callPush(string $tag, string $service = null): bool
    {
         try {
            $this->helpers->process()->withCommand([
                'docker', 'compose',
                '-f', 'docker-compose.yml',
                '-f', 'docker-compose.prod.yml',
                'push', $service
            ])
            ->withEnvironmentVars(['TAG' => $tag])
            ->withTimeout(1200) // 20mins
            ->echoLineByLineOutput(true)
            ->run();
        } catch (ProcessFailed $e) {
            $this->error("Unable to push all items to AWS, check the above output for reasons why.");
            return false;
        }

        return true;
    }
}
