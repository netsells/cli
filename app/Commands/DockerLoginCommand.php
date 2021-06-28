<?php

namespace App\Commands;

class DockerLoginCommand extends BaseCommand
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

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            return 1;
        }

        $loginSuccessful = $this->helpers->aws()->ecs()->authenticateDocker();

        if (!$loginSuccessful) {
            return 1;
        }

        $this->info("Logged into docker.");
    }
}
