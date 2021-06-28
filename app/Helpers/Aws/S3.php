<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use App\Helpers\Process;
use App\Exceptions\ProcessFailed;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class S3
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function getFile(Command $command, string $bucketName, string $path): Result
    {
        $client = new S3Client($this->aws->standardSdkArguments($command));

        $response = $client->getObject([
            'Bucket' => $bucketName,
            'Key' => $path,
        ]);

        return $response;
    }

    public function getJsonFile(Command $command, string $bucketName, string $path): array
    {
        $response = $this->getFile($command, $bucketName, $path);

        return json_decode($response->get('Body'), true);
    }
}
