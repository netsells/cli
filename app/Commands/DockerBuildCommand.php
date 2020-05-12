<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;

class DockerBuildCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:build
        {--tag= : The tag that should be built with the images. Defaults to the current commit SHA}
        {--service=* : The service that should be built. Not defining this will build all services (which will probably take longer than it could)}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Builds docker-compose ready for prod.';

    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        parent::__construct();
        $this->helpers = $helpers;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['docker', 'docker-compose'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $requiredFiles = ['docker-compose.yml', 'docker-compose-prod.yml'];

        if ($this->helpers->checks()->checkAndReportMissingFiles($this, $requiredFiles)) {
            return 1;
        }

        $tag = trim($this->option('tag') ?: $this->git->currentSha());
        $services = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('service'),
            NetsellsFile::DOCKER_SERVICES,
            []
        );

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->line("Building docker images for all services with tag {$tag}");
            $this->callBuild($tag);
        }

        // We've been provided services, we'll run the command for each
        $this->line("Building docker images for services with tag {$tag}: " . implode(',', $services));

        foreach ($services as $service) {
            $this->callBuild($tag, $service);
        }

        $this->info("Docker images built.");
    }

    protected function callBuild(string $tag, string $service = null): void
    {
        $process = new Process([
            'docker-compose',
            '-f', 'docker-compose.yml',
            '-f', 'docker-compose-prod.yml',
            'build', '--no-cache', $service
        ], null, [
            'TAG' => $tag,
        ]);

        $process->start();

        foreach ($process as $data) {
            echo $data;
        }
    }
}
