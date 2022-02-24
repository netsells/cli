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

    protected static $instance = null;

    public function __construct()
    {
        $this->detectAndParseNetsellsFile();
    }

    public static function getInstance(): self
    {
        if (static::$instance) {
            return static::$instance;
        }

        return static::$instance = new static();
    }

    public function get($keyPath, $default = null)
    {
        if (!$this->hasValidNetsellsFile()) {
            return $default;
        }

        return Arr::get($this->fileData, $keyPath, $default);
    }

    public function has($keyPath): bool
    {
        if (!$this->hasValidNetsellsFile()) {
            return false;
        }

        return Arr::has($this->fileData, $keyPath);
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
            $this->fileData = null;
        }
    }

    protected function hasValidNetsellsFile(): bool
    {
        return $this->fileData !== null;
    }
}
