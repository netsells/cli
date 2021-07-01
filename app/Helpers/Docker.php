<?php

namespace App\Helpers;

use Symfony\Component\Yaml\Yaml;
use App\Exceptions\ProcessFailed;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Exception\ParseException;

class Docker extends BaseHelper
{
    /** @var Helpers $helpers */
    private $helpers;

    public function __construct(Command $command, Helpers $helpers)
    {
        parent::__construct($command);
        $this->helpers = $helpers;
    }

    public function tagImages(?string $service, string $sourceTag, array $newTags): bool
    {
        $services = $this->getImageUrlsForServices($service ? [$service] : []);

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

                    $this->command->comment("Tagged " . $serviceImage . $newTag);
                } catch (ProcessFailed $e) {
                    $this->command->error("Unable to tag {$serviceImage}{$sourceTag} as {$serviceImage}{$newTag}");
                    return false;
                }
            }
        }

        return true;
    }

    public function determineTags(): array
    {
        // We'll start with the standard tag
        $tags = [
            $this->prefixedTag($this->command),
        ];

        if (
            !$this->command->option('skip-additional-tags')
        ) {
            // Not skipping, let's add latest and the env
            $tags[] = 'latest';

            if ($environment = $this->command->option('environment')) {
                $tags[] = $environment;
            }
        }

        return $tags;
    }

    public function getImageUrlsForServices(array $services = []): array
    {
        $config = $this->fetchComposeConfig($this->command);
        if (!$config) {
            return [];
        }

        return collect($config['services'])
            ->filter(function (array $serviceData, string $serviceName) use ($services) {
                return isset($serviceData['image']) && (empty($services) || in_array($serviceName, $services));
            })
            ->map(fn (array $serviceData) => $serviceData['image'])
            ->unique()
            ->values()
            ->all();
    }

    public function prefixedTag(): string
    {
        $tag = trim($this->command->option('tag'));

        if ($tagPrefix = $this->command->option('tag-prefix')) {
            return trim($tagPrefix) . $tag;
        }

        if ($tagPrefix = $this->command->option('environment')) {
            return trim($tagPrefix) . '-' . $tag;
        }

        return $tag;
    }

    public function fetchComposeConfig(): ?array
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
            $this->command->error("Unable to get generated config from docker-compose.");
            return null;
        }

        if (!$dockerComposeYml) {
            $this->command->error("Unable to get generated config from docker-compose.");
            return null;
        }

        try {
            $dockerComposeConfig = Yaml::parse($dockerComposeYml);
        } catch (ParseException $exception) {
            $this->command->error("Failed to parse yml from docker-compose output.");
            return null;
        }

        return $dockerComposeConfig;
    }
}
