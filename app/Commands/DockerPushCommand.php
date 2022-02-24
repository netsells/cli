<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;
use App\Commands\Console\DockerOption;

class DockerPushCommand extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:aws:push';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Pushes docker-compose created images to ECR';

    public function configure()
    {
        $this->setDefinition(array_merge([
            new DockerOption('skip-additional-tags', null, DockerOption::VALUE_NONE, 'Skips the latest and environment tags'),
        ], $this->helpers->aws()->commonDockerOptions(), $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['docker', 'docker-compose', 'aws'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            return 1;
        }

        $requiredFiles = ['docker-compose.yml', 'docker-compose.prod.yml'];

        if ($this->helpers->checks()->checkAndReportMissingFiles($requiredFiles)) {
            return 1;
        }

        $services = $this->option('service');

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker();

        if (!$loginSuccessful) {
            return 1;
        }

        $tags = $this->helpers->docker()->determineTags();

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->line("Pushing docker images for all services with tags " . implode(', ', $tags));
            if (!$this->callPush($tags)) {
                $this->error("Unable to push images.");
                return 1;
            }
        } else {
            // We've been provided services, we'll run the command for each
            $this->line(sprintf(
                "Pushing docker images for services with tags %s: %s",
                implode(', ', $tags),
                implode(',', $services)
            ));
        }

        foreach ($services as $service) {
            if (!$this->callPush($tags, $service)) {
                return 1;
            }
        }

        $this->info("Docker images pushed.");
    }

    protected function callPush(array $tags, string $service = null): bool
    {
        // We need to make the new tags first
        $sourceTag = $this->helpers->docker()->prefixedTag();
        if (!$this->helpers->docker()->tagImages($service, $sourceTag, $tags)) {
            return false;
        }

        foreach ($tags as $tag) {
            try {
                $this->helpers->process()->withCommand(array_filter([
                    'docker-compose',
                    '-f', 'docker-compose.yml',
                    '-f', 'docker-compose.prod.yml',
                    'push', $service
                ]))
                ->withEnvironmentVars(['TAG' => $tag])
                ->withTimeout(1200) // 20mins
                ->echoLineByLineOutput(true)
                ->run();
            } catch (ProcessFailed $e) {
                $this->error("Unable to push all items to AWS, check the above output for reasons why.");
                return false;
            }
        }

        return true;
    }
}
