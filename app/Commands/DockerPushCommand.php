<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class DockerPushCommand extends Command
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
    protected $description = 'Pushes docker-compose created images to ECR.';

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
            new InputOption('tag', null, InputOption::VALUE_OPTIONAL, 'The tag that should be built with the images. Defaults to the current commit SHA'),
            new InputOption('service', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The service that should be pushed. Not defining this will push all services'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['docker', 'docker-compose', 'aws'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $requiredFiles = ['docker-compose.yml', 'docker-compose.prod.yml'];

        if ($this->helpers->checks()->checkAndReportMissingFiles($this, $requiredFiles)) {
            return 1;
        }

        $tag = trim($this->option('tag') ?: $this->helpers->git()->currentSha());
        $services = $this->helpers->console()->handleOverridesAndFallbacks(
            $this->option('service'),
            NetsellsFile::DOCKER_SERVICES,
            []
        );

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker($this);

        if (!$loginSuccessful) {
            return 1;
        }

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->line("Pushing docker images for all services with tag {$tag}");
            $this->callPush($tag);
        }

        // We've been provided services, we'll run the command for each
        $this->line("Pushing docker images for services with tag {$tag}: " . implode(',', $services));

        foreach ($services as $service) {
            $this->callPush($tag, $service);
        }

        $this->info("Docker images pushed.");
    }

    protected function callPush(string $tag, string $service = null): void
    {
        $process = new Process([
            'docker-compose',
            '-f', 'docker-compose.yml',
            '-f', 'docker-compose.prod.yml',
            'push', $service
        ], null, [
            'TAG' => $tag,
        ], null, 1200); // 20min timeout

        $process->start();

        foreach ($process as $data) {
            echo $data;
        }
    }
}
