<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class DockerLoginCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker:aws:login';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Logs into docker via the AWS account';

    /** @var Helpers $helpers */
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
        parent::__construct();
    }

    public function configure()
    {
        $this->setDefinition($this->helpers->aws()->commonConsoleOptions());
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

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker($this);

        if (!$loginSuccessful) {
            return 1;
        }

        $this->info("Logged into docker.");
    }
}
