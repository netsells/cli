<?php

namespace App\Commands;

use App\Helpers\Helpers;
use App\Helpers\NetsellsFile;
use App\Exceptions\ProcessFailed;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

class AwsMfaLogin extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:mfa:login';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Handles MFA Login';

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
        $mfaDevices = $this->helpers->aws()->iam()->listMfaDevices($this);

        if (count($mfaDevices) === 0) {
            $this->error('No MFA devices for current user.');
            return 1;
        }

        $mfaDevice = $mfaDevices[0];

        if (count($mfaDevices) > 1) {
            $mfaDevice = $this->menu("Choose the MFA device you want to use...", array_combine($mfaDevices, $mfaDevices))
                ->open();
        }

        $code = $this->ask("Please enter the code generated by your MFA device...");

        $envVars = $this->helpers->aws()->iam()->authenticateWithMfaDevice($this, $mfaDevice, $code);

        $this->info("Now opening a session following your MFA authentication. Type `exit` to leave this shell.");
        Process::fromShellCommandline("BASH_SILENCE_DEPRECATION_WARNING=1 PS1='\e[32mnscli\e[34m(mfa-authd)$\e[39m ' bash")
            ->setEnv($envVars)
            ->setTty(Process::isTtySupported())
            ->setIdleTimeout(null)
            ->setTimeout(null)
            ->run(null);
    }
}
