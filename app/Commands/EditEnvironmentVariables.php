<?php

namespace App\Commands;

use App\Commands\Console\InputOption;
use App\Helpers\Helpers;
use Illuminate\Support\Str;
use SebastianBergmann\Diff\Differ;

class EditEnvironmentVariables extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'aws:ecs:manage-env';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Edits environment variables for a system';

    /** @var Helpers $helpers */
    protected $helpers;

    private array $envFiles = [];

    private string $fileName;

    private string $fileContents;

    private string $editor;

    public function configure()
    {
        $this->setDefinition(array_merge([
            new InputOption('s3-bucket-name', null, InputOption::VALUE_OPTIONAL, 'The bucket holding the files with environment variables.'),
        ], $this->helpers->aws()->commonConsoleOptions()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->editor = env('EDITOR', '/usr/bin/vi');

        $requiredBinaries = ['aws', $this->editor];

        if ($this->helpers->checks()->checkAndReportMissingBinaries($requiredBinaries)) {
            exit(1);
        }

        $bucket = $this->selectBucket();

        if (!$bucket) {
            $this->error('An S3 bucket for the environment variable files must be specified. Calling aws:assume-role will do this automatically for you.');
            exit(1);
        }

        $this->processUpdate($bucket);

        exit(0);
    }

    private function selectBucket(): ?string
    {
        $bucket = $this->option('s3-bucket-name') ?? getenv('AWS_S3_ENV');

        if ($bucket) {
            return $bucket;
        }

        $buckets = $this->helpers->aws()->s3()->listBuckets();

        if (!$buckets) {
            return null;
        }

        return $this->menu('Choose a bucket to open...', array_combine($buckets, $buckets))->open();
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
        $files = $this->helpers->aws()->s3()->listFiles($bucket);

        if ($files) {
            $this->envFiles = collect($files)
                ->pluck('Key')
                ->map(function ($filename) {
                    return $filename;
                })
                ->reject(function ($filename) {
                    return !Str::endsWith($filename, '.env');
                })
                ->toArray();
        }

        $additionalMenuOptions = ['Create new file'];
        $callerArn = $this->helpers->aws()->iam()->getCallerArn();
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
            $this->fileContents = (string)$this->helpers->aws()->s3()->getFile($bucket, $this->fileName)->get('Body');
        } elseif ($menuItem == count($this->envFiles)) {
            $this->fileName = $this->ask('Enter name of new file (must have an extension of .env)');
            if (!Str::endsWith($this->fileName, '.env')) {
                exit(1);
            }
            $this->fileContents = '';
        } else {
            $deleteMenuItem = $this->menu("Choose a file to edit", $this->envFiles)->open();
            if ($deleteMenuItem !== null) {
                if ($this->confirm("Are you sure you wish to delete {$this->envFiles[$deleteMenuItem]}?")) {
                    $this->helpers->aws()->s3()->deleteFile($bucket, $this->envFiles[$deleteMenuItem]);
                    $this->info('File deleted.');
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
                pcntl_exec($this->editor, ['/tmp/' . $this->fileName]);
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

        if ($diff == "--- Original\n+++ New\n") {
            $this->info('No changes made.');
            exit(0);
        }

        $this->info("Changes made\n");
        $this->comment('---------------------------------------------');
        echo $diff;
        $this->comment('---------------------------------------------');
        $this->newLine();

        if ($this->confirm('Do you want to upload the changes?')) {
            $this->helpers->aws()->s3()->putFile($bucket, $this->fileName, '/tmp/' . $this->fileName);
            $this->info('Changes saved.');
        }
    }
}
