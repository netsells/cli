<?php

namespace App\Commands;

use App\Helpers\Helpers;
use Aws\Sts\Exception\StsException;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class AwsAssumeRole extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:assume-role';

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
        $accounts = collect($this->helpers->aws()->s3()->getJsonFile($this, 'netsells-security-meta', 'accounts.json')['accounts']);

        if ($accounts->count() === 0) {
            $this->error('No accounts available.');
            return 1;
        }

        $accountId = $this->menu("Choose an account to connect to...", array_combine($accounts->pluck('id')->all(), $accounts->pluck('name')->all()))->open();

        $account = $accounts->firstWhere('id', $accountId);
        $accountName = $account['name'];

        $roles = collect($this->helpers->aws()->s3()->getJsonFile($this, 'netsells-security-meta', 'roles.json')['roles'])->pluck('name')->all();

        $role = $this->menu("Choose a role to assume...", array_combine($roles, $roles))->open();

        $callerArn = $this->helpers->aws()->iam()->getCallerArn($this);
        $sessionUser = 'unknown.user';

        if (str_contains($callerArn, 'user/')) {
            $arnParts = explode('user/', $callerArn);
            $sessionUser = $arnParts[1];
        }

        try {
            $envVars = $this->helpers->aws()->iam()->assumeRole($this, $accountId, $role, $sessionUser);
        } catch (StsException $e) {
            if ($e->getAwsErrorCode() == 'AccessDenied') {
                // There's a high chance that MFA is required for this, let's try that.
                $mfaDevice = $this->askForMfaDevice($this);

                if (!$mfaDevice) {
                    $this->info("Access was denied assuming role {$role}. We tried to initiate an MFA session but you have no devices available for user {$sessionUser}.");
                    return 1;
                }

                $mfaCode = $this->askForMfaCode($this);

                if (!$mfaCode) {
                    $this->info("No code provided, exiting.");
                    return 0;
                }

                $envVars = $this->helpers->aws()->iam()->assumeRole($this, $accountId, $role, $sessionUser, $mfaDevice, $mfaCode);
            }
        }

        $assumePrompt = "{$sessionUser}:{$accountName}";

        $envVars['AWS_S3_ENV'] = $account['s3env'];

        $this->info("Now opening a session following you ({$sessionUser}) assuming the role {$role} on {$accountName} ({$accountId}) . Type `exit` to leave this shell.");
        Process::fromShellCommandline("BASH_SILENCE_DEPRECATION_WARNING=1 PS1='\e[32mnscli\e[34m({$assumePrompt})$\e[39m ' bash")
            ->setEnv($envVars)
            ->setTty(Process::isTtySupported())
            ->setIdleTimeout(null)
            ->setTimeout(null)
            ->run(null);
    }

    protected function askForMfaDevice(Command $command): ?string
    {
        $mfaDevices = $this->helpers->aws()->iam()->listMfaDevices($this);

        if (count($mfaDevices) === 0) {
            $this->error('No MFA devices for current user.');
            return null;
        }

        $mfaDevice = $mfaDevices[0];

        if (count($mfaDevices) > 1) {
            $mfaDevice = $this->menu("Choose the MFA device you want to use...", array_combine($mfaDevices, $mfaDevices))
            ->open();
        }

        return $mfaDevice;
    }


    protected function askForMfaCode(Command $command): ?string
    {
        return $this->ask("Please enter the code generated by your MFA device...");
    }
}
