<?php

namespace App\Commands;

use App\Exceptions\ProcessFailed;

class DockerBuildCommand extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:build';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Builds docker-compose ready for prod';

    public function configure()
    {
        $this->setDefinition(array_merge(
            $this->helpers->aws()->commonDockerOptions(),
            $this->helpers->aws()->commonConsoleOptions(),
        ));
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

        $tag = $this->helpers->docker()->prefixedTag();

        $services = $this->option('service', []);
        $services = !is_array($services) ? [$services] : $services;

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker();

        if (!$loginSuccessful) {
            return 1;
        }

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->line("Building docker images for all services with tag {$tag}");
            if ($this->callBuild($tag)) {
                return $this->info("Docker images built.");
            } else {
                $this->error("Docker images failed to build.");
                return 1;
            }
        }

        // We've been provided services, we'll run the command for each
        $this->line("Building docker images for services with tag {$tag}: " . implode(',', $services));

        foreach ($services as $service) {
            if (!$this->callBuild($tag, $service)) {
                return 1;
            }
        }

        $this->info("Docker images built.");
    }

    protected function callBuild(string $tag, string $service = null): bool
    {
        try {
            $this->helpers->process()->withCommand($this->buildCommandParts($service))
            ->withEnvironmentVars(['TAG' => $tag])
            ->withTimeout(1200) // 20mins
            ->echoLineByLineOutput(true)
            ->run();
        } catch (ProcessFailed $e) {
            $this->error("Unable to build all images, check the above output for reasons why.");
            return false;
        }

        return true;
    }

    protected function buildCommandParts(string $service = null): array
    {
        $composeVersion = $this->helpers->docker()->determineComposeVersion();

        // Conservatively implement 2.0.0 functionality - some params are duplicated to ensure future compatability
        if ($composeVersion >= '2.0.0') {
            return array_filter([
                'docker', 'compose',
                '-f', 'docker-compose.yml',
                '-f', 'docker-compose.prod.yml',
                'build', '--no-cache', $service
            ]);
        }

        return array_filter([
            'docker-compose',
            '-f', 'docker-compose.yml',
            '-f', 'docker-compose.prod.yml',
            'build', '--no-cache', $service
        ]);
    }


}
