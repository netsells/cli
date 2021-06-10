<?php

namespace App\Commands\Console;

use App\Helpers\NetsellsFile;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption as BaseInputOption;

class InputOption extends BaseInputOption
{
    protected $netsellsFilePrefix;

    public function getDefault()
    {
        // Determine key for env vars etc
        $keyName = Str::upper(Str::slug($this->getName(), '_'));
        $envVar = 'NETSELLS_' . $keyName;

        if ($envValue = getenv($envVar)) {
            return $envValue;
        }

        $constantName = sprintf("%s::%s", NetsellsFile::class, $this->netsellsFilePrefix . $keyName);

        if (defined($constantName)) {
            return (new NetsellsFile())->get(constant($constantName));
        }

        return parent::getDefault();
    }

}
