<?php

namespace App\Commands\Console;

use App\Helpers\NetsellsFile;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption as BaseInputOption;

class InputOption extends BaseInputOption
{
    protected const ENV_PREFIX = 'NETSELLS_';
    protected const ENV_ARRAY_SEPARATOR = ',';

    protected $netsellsFilePrefix = '';

    /**
     * Tries to resolve the input value from the env variable, followed by the netsells config file, followed by the configured default value.
     * When the input option is an array, we'll also attempt pluralised version of the input name, e.g. either NETSELLS_SERVICE or NETSELLS_SERVICES.
     * To define multiple values for an input with environment variable, the values should be comma separated and provided as single environment variable.
     * 
     * @return mixed
     */
    public function getDefault()
    {
        $fieldNames = [$this->getName()];

        if ($this->isArray()) {
            $fieldNames[] = Str::plural($this->getName());
        }

        foreach ($fieldNames as $fieldName) {
            $keyName = Str::upper(Str::slug($fieldName, '_'));

            try {
                $value = $this->readEnvOrConfig($keyName);

                if ($this->isArray() && is_string($value)) {
                    return explode(static::ENV_ARRAY_SEPARATOR, $value);
                }

                return $value;
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }

        return parent::getDefault();
    }

    protected function readEnvOrConfig(string $keyName)
    {
        $envVar = static::ENV_PREFIX . $keyName;

        // check if the env var is set
        // so if it is set with falsy value, we'll still return it
        if (($envValue = getenv($envVar)) !== false) {
            return $envValue;
        }

        $constantName = sprintf("%s::%s", NetsellsFile::class, $this->netsellsFilePrefix . $keyName);

        if (defined($constantName)) {
            $fileKeyPath = constant($constantName);

            if (NetsellsFile::getInstance()->has($fileKeyPath)) {
                return NetsellsFile::getInstance()->get($fileKeyPath);
            }
        }

        throw new \InvalidArgumentException('Key doesn\'t exist');
    }
}
