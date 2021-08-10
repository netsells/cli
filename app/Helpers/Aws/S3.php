<?php

namespace App\Helpers\Aws;

use App\Helpers\Aws;
use Aws\Result;
use Aws\S3\S3Client;
use LaravelZero\Framework\Commands\Command;

class S3
{
    /** @var Aws $aws */
    protected $aws;

    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
    }

    public function listFiles(string $bucketName): array
    {
        $client = new S3Client($this->aws->standardSdkArguments());

        $response = $client->listObjectsV2(['Bucket' => $bucketName]);

        return $response->get('Contents');
    }

    public function getFile(string $bucketName, string $path): Result
    {
        $client = new S3Client($this->aws->standardSdkArguments());

        $response = $client->getObject([
            'Bucket' => $bucketName,
            'Key' => $path,
        ]);

        return $response;
    }

    public function getJsonFile(string $bucketName, string $path): array
    {
        $response = $this->getFile($bucketName, $path);

        return json_decode($response->get('Body'), true);
    }

    public function putFile(string $bucketName, string $path, string $tempFile): Result
    {
        $client = new S3Client($this->aws->standardSdkArguments());

        $response = $client->putObject([
            'Bucket' => $bucketName,
            'Key' => $path,
            'SourceFile' => $tempFile,
        ]);

        return $response;
    }

    public function deleteFile(string $bucketName, string $fileName): Result
    {
        $client = new S3Client($this->aws->standardSdkArguments());

        return $client->deleteObject([
            'Bucket' => $bucketName,
            'Key' => $fileName,
        ]);
    }
}
