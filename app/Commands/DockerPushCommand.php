<?php

namespace App\Commands;

use App\Helpers\Git;
use App\Helpers\Checks;
use App\Helpers\NetsellsFile;
use LaravelZero\Framework\Commands\Command;

class DockerPushCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:push
        {--tag= : The tag that should be built with the images. Defaults to the current commit SHA}
        {--service=* : The service that should be pushed. Not defining this will push all services}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Pushes docker-compose created images to the NS AWS account.';

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
        $requiredBinaries = ['docker', 'docker-compose', 'aws'];

        if ($this->checks->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $requiredFiles = ['docker-compose.yml', 'docker-compose-prod.yml'];

        if ($this->checks->checkAndReportMissingFiles($this, $requiredFiles)) {
            return 1;
        }

        $tag = trim($this->option('tag') ?: $this->git->currentSha());
        $services = $this->option('service') ?: $this->netsellsFile->get(NetsellsFile::DOCKER_SERVICES, []);

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->info("Pushing docker images for all services with tag {$tag}");
            $this->callPush($tag);
        }

        // We've been provided services, we'll run the command for each
        $this->info("Pushing docker images for services with tag {$tag}: " . implode(',', $services));

        foreach ($services as $service) {
            $this->callPush($tag, $service);
        }
    }

    protected function callPush(string $tag, string $service = null): string
    {
        putenv("TAG={$tag}");
        return shell_exec("
            docker-compose \
                -f docker-compose.yml \
                -f docker-compose-prod.yml \
                push {$service}
        ");
    }
}
