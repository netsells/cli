<?php

namespace App\Commands;

use App\Commands\Console\DockerOption;
use App\Exceptions\ProcessFailed;
use Symfony\Component\Console\Input\InputOption;

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
        $this->setDefinition(array_merge([
            new DockerOption('tag', null, DockerOption::VALUE_OPTIONAL, 'The tag that should be built with the images. Defaults to the current commit SHA', $this->helpers->git()->currentSha()),
            new DockerOption('tag-prefix', null, DockerOption::VALUE_OPTIONAL, 'The tag prefix that should be built with the images. Defaults to null'),
            new InputOption('environment', null, InputOption::VALUE_OPTIONAL, 'The destination environment for the images'),
            new DockerOption('service', null, DockerOption::VALUE_OPTIONAL | DockerOption::VALUE_IS_ARRAY, 'The service that should be built. Not defining this will push all services', []),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['docker', 'docker-compose'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            return 1;
        }

        $requiredFiles = ['docker-compose.yml', 'docker-compose.prod.yml'];

        if ($this->helpers->checks()->checkAndReportMissingFiles($requiredFiles)) {
            return 1;
        }

        $tag = $this->helpers->docker()->prefixedTag();

        $services = $this->option('service');

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker();

        if (!$loginSuccessful) {
            return 1;
        }

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->line("Building docker images for all services with tag {$tag}");
            $this->callBuild($tag);
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
            $this->helpers->process()->withCommand([
                'docker-compose',
                '-f', 'docker-compose.yml',
                '-f', 'docker-compose.prod.yml',
                'build', '--no-cache', $service
            ])
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
}
