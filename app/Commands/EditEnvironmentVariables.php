<?php

namespace App\Commands;

use App\Helpers\Helpers;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use SebastianBergmann\Diff\Differ;
use Symfony\Component\Console\Input\InputOption;

class EditEnvironmentVariables extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:edit-environment-variables';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Edits environment variables for a system';

    /** @var Helpers $helpers */
    protected $helpers;

    protected $clusterName;
    protected $serviceName;
    protected $taskDefinitionName;

    private array $envFiles;

    private string $fileName;

    private string $fileContents;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
        parent::__construct();
    }

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('client-system', null, InputOption::VALUE_OPTIONAL, 'The client to edit the environment variables for'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $requiredBinaries = ['aws', config('app.editor')];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($this, $requiredBinaries)) {
            exit(1);
        }

        $bucket = $this->option('client-system') ?? getenv('AWS_S3_ENV');

        if (!$bucket) {
            $this->error('An S3 bucket for the environment variable files must be specified.');
            exit(1);
        }

        $this->processUpdate($bucket);

        exit(0);
    }

    private function processUpdate(string $bucket): void
    {
        $menuItem = $this->getMenuItem($bucket);

        if ($menuItem === null) {
            exit(0);
        }

        $this->getFile($menuItem, $bucket);

        $newContents = $this->editFile();

        $this->checkDiffAndUpload($newContents, $bucket);

        exit(0);
    }

    private function getMenuItem(string $bucket): ?int
    {
        $this->envFiles = collect($this->helpers->aws()->s3()->listFiles($this, $bucket))
            ->pluck('Key')
            ->map(function ($filename) {
                return $filename;
            })
            ->reject(function ($filename) {
                return !Str::endsWith($filename, '.env');
            })
            ->toArray();

        $additionalMenuOptions = ['Create new file'];
        $callerArn = $this->helpers->aws()->iam()->getCallerArn($this);
        $callerArnParts = explode('/', $callerArn);

        if ($callerArnParts[1] == 'NetsellsSecurityOps') {
            $additionalMenuOptions[] = 'Delete file';
        }

        return $this->menu("Choose a file to edit", array_merge($this->envFiles, $additionalMenuOptions))->open();
    }

    private function getFile(int $menuItem, string $bucket): void
    {
        if ($menuItem < count($this->envFiles)) {
            $this->fileName = $this->envFiles[$menuItem];
            $this->fileContents = (string)$this->helpers->aws()->s3()->getFile($this, $bucket, $this->fileName)->get('Body');
        } elseif ($menuItem == count($this->envFiles)) {
            $this->fileName = $this->ask('Enter name of new file (must have an extension of .env)');
            if (!Str::endsWith($this->fileName, '.env')) {
                exit(1);
            }
            $this->fileContents = '';
        } else {
            $deleteMenuItem = $this->menu("Choose a file to edit",$this->envFiles)->open();
            if ($deleteMenuItem !== null) {
                if ($this->confirm("Are you sure you wish to delete {$this->envFiles[$deleteMenuItem]}?")) {
                    $this->helpers->aws()->s3()->deleteFile($this, $bucket, $this->envFiles[$deleteMenuItem]);
                }
            }
            exit(0);
        }

        file_put_contents('/tmp/' . $this->fileName, $this->fileContents);
    }

    private function editFile(): string
    {
        switch ($pid = pcntl_fork()) {
            case -1:
                die('Unable to run editor');
            case 0:
                pcntl_exec(config('app.editor'), ['/tmp/' . $this->fileName]);
                break;
            default:
                pcntl_waitpid($pid, $status);
                break;
        }

        return file_get_contents('/tmp/' . $this->fileName);
    }

    private function checkDiffAndUpload(string $newContents, string $bucket): void
    {
        $diff = (new Differ())->diff($this->fileContents, $newContents);

        if ($diff == '') {
            $this->info('No changes made.');
            exit(0);
        }

        $this->info("Changes made\n\n");

        echo($diff);

        if ($this->confirm('Do you want to upload the changes? (Y/N)')) {
            $this->helpers->aws()->s3()->putFile($this, $bucket, $this->fileName, '/tmp/' . $this->fileName);
        }
    }
}
