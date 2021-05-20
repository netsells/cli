<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
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
            new InputOption('tag', null, InputOption::VALUE_OPTIONAL, 'The tag that should be built with the images. Defaults to the current commit SHA'),
            new InputOption('service', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The service that should be pushed. Not defining this will push all services'),
            new InputOption('environment', null, InputOption::VALUE_OPTIONAL, 'The environment to look for the image urls', 'prod'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['docker', 'aws'];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            return 1;
        }

        $environmentFile = $this->envDockerComposeFileName($this->option('environment'));

        $this->line("Taking docker repository URLs from {$environmentFile}");
        $requiredFiles = ['docker-compose.yml', $environmentFile];

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
            if (!$this->callPush($tag, $service)) {
                return 1;
            }
        }

        $this->info("Docker images pushed.");
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

    protected function envDockerComposeFileName(string $environment): string
    {
        return "docker-compose.{$environment}.yml";
    }
}
