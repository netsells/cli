<?php

namespace App\Helpers\DataObjects;

class OverridesAndFallbacks
{
    public $console;
    public $envVar;
    public $netsellsFile;
    public $default;

    public function console($value): self
    {
        $this->console = $value;

        return $this;
    }

    public function envVar(string $value): self
    {
        $this->envVar = $value;

        return $this;
    }

    public function netsellsFile(string $value): self
    {
        $this->netsellsFile = $value;

        return $this;
    }

    public function default($value): self
    {
        $this->default = $value;

        return $this;
    }

    public static function withConsole($value): self
    {
        $values = new static();
        $values->console($value);

        return $values;
    }
}
