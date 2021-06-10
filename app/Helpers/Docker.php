<?php

namespace App\Helpers;

use Symfony\Component\Yaml\Yaml;
use App\Exceptions\ProcessFailed;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Exception\ParseException;

class Docker
{

    /** @var Helpers $helpers */
    public $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    public function tagImages(Command $command, string $service = null, string $sourceTag, array $newTags): bool
    {
        if ($service) {
            $services = [
                $this->getImageUrlForService($command, $service),
            ];
        } else {
            $services = $this->getImageUrlsForAllServices($command);
        }

        foreach ($services as $serviceImage) {
            foreach ($newTags as $newTag) {

                if ($newTag === $sourceTag) {
                    // No point tagging the same thing
                    continue;
                }

                try {
                    $this->helpers->process()->withCommand([
                        'docker', 'tag',
                        $serviceImage . $sourceTag, $serviceImage . $newTag
                    ])
                    ->echoLineByLineOutput(true)
                    ->run();

                    $command->comment("Tagged " . $serviceImage . $newTag);
                } catch (ProcessFailed $e) {
                    $command->error("Unable to tag {$serviceImage}{$sourceTag} as {$serviceImage}{$newTag}");
                    return false;
                }
            }
        }

        return true;
    }

    public function determineTags(Command $command): array
    {
        // We'll start with the standard tag
        $tags = [
            $this->prefixedTag($command),
        ];

        if (
            !$command->option('skip-additional-tags')
        ) {
            // Not skipping, let's add latest and the env
            $tags[] = 'latest';

            if ($environment = $command->option('environment')) {
                $tags[] = $environment;
            }
        }

        return $tags;
    }

    public function getImageUrlsForAllServices(Command $command): ?array
    {
        $config = $this->fetchComposeConfig($command);

        if (!$config) {
            return null;
        }

        return collect($config['services'])
            ->transform(function ($serviceData, $serviceName) {
                return $serviceName;
            })
            ->filter()
            ->all();
    }

    public function getImageUrlForService(Command $command, $service): ?string
    {
        $config = $this->fetchComposeConfig($command);

        if (!$config) {
            return null;
        }

        return collect($config['services'])
            ->filter(function ($serviceData, $serviceName) use ($service) {
                return ($serviceName == $service);
            })
            ->transform(function ($serviceData, $serviceName) {
                // We're only updating services that have an image attached
                if (!isset($serviceData['image'])) {
                    return null;
                }

                return $serviceData['image'];
            })
            ->first();
    }

    public function prefixedTag(Command $command): string
    {
        $tag = trim($command->option('tag'));

        if ($tagPrefix = $command->option('tag-prefix')) {
            return trim($tagPrefix) . $tag;
        }

        if ($tagPrefix = $command->option('environment')) {
            return trim($tagPrefix) . '-' . $tag;
        }

        return $tag;
    }

    public function fetchComposeConfig(Command $command): ?array
    {
        try {
            $dockerComposeYml = $this->helpers->process()->withCommand([
                'docker-compose',
                '-f', 'docker-compose.yml',
                '-f', 'docker-compose.prod.yml',
                '--log-level', 'ERROR',
                'config',
            ])
            ->run();
        } catch (ProcessFailed $e) {
            $command->error("Unable to get generated config from docker-compose.");
            return null;
        }

        if (!$dockerComposeYml) {
            $command->error("Unable to get generated config from docker-compose.");
            return null;
        }

        try {
            $dockerComposeConfig = Yaml::parse($dockerComposeYml);
        } catch (ParseException $exception) {
            $command->error("Failed to parse yml from docker-compose output.");
            return null;
        }

        return $dockerComposeConfig;
    }
}
