<?php

namespace App\Helpers;

use App\Helpers\DataObjects\OverridesAndFallbacks;

class Console
{
    public function handleOverridesAndFallbacks(OverridesAndFallbacks $values)
    {
        if ($values->console) {
            return $values->console;
        }

        if ($envValue = getenv('NETSELLS_' . $values->envVar)) {
            return $envValue;
        }

        if (is_null($values->netsellsFile)) {
            return $values->default;
        }

        $netsellsFile = new NetsellsFile();

        return $netsellsFile->get($values->netsellsFile, $values->default);
    }
}
