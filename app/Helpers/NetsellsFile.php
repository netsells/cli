<?php

namespace App\Helpers;

use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class NetsellsFile
{
    public const DOCKER_SERVICES = 'docker.services';
    public const DOCKER_AWS_REGION = 'docker.aws.region';
    public const DOCKER_AWS_ACCOUNT_ID = 'docker.aws.account-id';
    public const DOCKER_ECS_SERVICE = 'docker.aws.ecs.service';
    public const DOCKER_ECS_CLUSTER = 'docker.aws.ecs.cluster';
    public const DOCKER_ECS_TASK_DEFINITION = 'docker.aws.ecs.task-definition';

    protected $fileName = '.netsells.yml';
    protected $fileData = null;

    public function __construct()
    {
        $this->detectAndParseNetsellsFile();
    }

    protected function detectAndParseNetsellsFile(): void
    {
        if (!file_exists($this->fileName)) {
            return;
        }

        $fileContents = file_get_contents($this->fileName);

        try {
            $this->fileData = Yaml::parse($fileContents);
        } catch (ParseException $exception) {
            // Leave $this->fileData null
        }
    }

    protected function hasValidNetsellsFile(): bool
    {
        return is_null($this->fileData);
    }

    public function get($keyPath, $default = null)
    {
        return Arr::get($this->fileData, $keyPath, $default);
    }
}