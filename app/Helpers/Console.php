<?php

namespace App\Helpers;

class Console
{
    public function handleOverridesAndFallbacks($consoleArg, string $netsellsFileKey = null, $default = null)
    {
        if ($consoleArg) {
            return $consoleArg;
        }

        if (is_null($netsellsFileKey)) {
            return $default;
        }

        $netsellsFile = new NetsellsFile();

        return $netsellsFile->get($netsellsFileKey, $default);
    }
}