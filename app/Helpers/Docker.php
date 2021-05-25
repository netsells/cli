<?php

namespace App\Helpers;

use App\Helpers\DataObjects\OverridesAndFallbacks;
use LaravelZero\Framework\Commands\Command;

class Docker
{

    /** @var Helpers $helpers */
    public $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    public function prefixedTag(Command $command): string
    {
        $tag = trim($this->helpers->console()->handleOverridesAndFallbacks(
            OverridesAndFallbacks::withConsole($command->option('tag'))
                ->envVar('TAG')
                ->default($this->helpers->git()->currentSha())
        ));

        if ($tagPrefix = $this->helpers->console()->handleOverridesAndFallbacks(OverridesAndFallbacks::withConsole($command->option('tag-prefix'))->envVar('TAG_PREFIX'))) {
            return trim($tagPrefix) . $tag;
        }

        return $tag;
    }
}
