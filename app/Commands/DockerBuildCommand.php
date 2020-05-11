<?php

namespace App\Commands;

use App\Helpers\Git;
use App\Helpers\Checks;
use App\Helpers\NetsellsFile;
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

    /** @var Git $git */
    protected $git;

    /** @var Checks $checks */
    protected $checks;

    /** @var NetsellsFile $netsellsFile */
    protected $netsellsFile;

    public function __construct(Git $git, Checks $checks, NetsellsFile $netsellsFile)
    {
        parent::__construct();

        $this->git = $git;
        $this->checks = $checks;
        $this->netsellsFile = $netsellsFile;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredFiles = ['docker-compose.yml', 'docker-compose-prod.yml'];

        if ($this->checks->hasMissingFiles($requiredFiles)) {
            $this->reportMissingFiles($requiredFiles);
            return 1;
        }

        $tag = trim($this->option('tag') ?: $this->git->currentSha());
        $services = $this->option('service') ?: $this->netsellsFile->get(NetsellsFile::DOCKER_SERVICES, []);

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->info("Building docker images for all services with tag {$tag}");
            $this->callBuild($tag);
        }

        // We've been provided services, we'll run the command for each
        $this->info("Building docker images for services with tag {$tag}: " . implode(',', $services));

        foreach ($services as $service) {
            $this->callBuild($tag, $service);
        }
    }

    protected function reportMissingFiles($requiredFiles): void
    {
        $this->comment("Cannot build for docker due to missing required file(s): ");
        foreach ($this->checks->missingFiles($requiredFiles) as $missingFile) {
            $this->comment("- {$missingFile}");
        }
    }

    protected function callBuild(string $tag, string $service = null): string
    {
        putenv("TAG={$tag}");
        return shell_exec("
            docker-compose \
                -f docker-compose.yml \
                -f docker-compose-prod.yml \
                build --no-cache {$service}
        ");
    }
}
