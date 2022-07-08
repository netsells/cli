<?php

namespace App\Helpers;

class Git extends BaseHelper
{
    public function currentSha(): string
    {
        // attempt to run git, but suppress any errors
        return trim((string) shell_exec('git --no-pager log -1 --pretty=%H 2> /dev/null'));
    }
}
