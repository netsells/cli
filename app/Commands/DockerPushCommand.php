<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Exceptions\ProcessFailed;
use App\Commands\Console\DockerOption;
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
    protected $description = 'Pushes docker-compose created images to ECR';

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
            new DockerOption('tag', null, DockerOption::VALUE_OPTIONAL, 'The tag that should be built with the images. Defaults to the current commit SHA', $this->helpers->git()->currentSha()),
            new DockerOption('tag-prefix', null, DockerOption::VALUE_OPTIONAL, 'The tag prefix that should be built with the images. Defaults to null'),
            new InputOption('environment', null, InputOption::VALUE_OPTIONAL, 'The destination environment for the images'),
            new DockerOption('skip-additional-tags', null, DockerOption::VALUE_NONE, 'Skips the latest and environment tags'),
            new DockerOption('service', null, DockerOption::VALUE_OPTIONAL | DockerOption::VALUE_IS_ARRAY, 'The service that should be pushed. Not defining this will push all services', []),
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

        $services = $this->option('service');

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker($this);

        if (!$loginSuccessful) {
            return 1;
        }

        $tags = $this->helpers->docker()->determineTags($this);

        if (count($services) == 0) {
            // Generic full file build as we have no services
            $this->line("Pushing docker images for all services with tags " . implode(', ', $tags));
            $this->callPush($tags);
        }

        // We've been provided services, we'll run the command for each
        $this->line(sprintf(
            "Pushing docker images for services with tags %s: %s",
            implode(', ', $tags),
            implode(',', $services)
        ));

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
        $sourceTag = $this->helpers->docker()->prefixedTag($this);
        if (!$this->helpers->docker()->tagImages($this, $service, $sourceTag, $tags)) {
            return 1;
        }

        foreach ($tags as $tag) {
            try {
                $this->helpers->process()->withCommand([
                    'docker-compose',
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
        }

        return true;
    }
}
