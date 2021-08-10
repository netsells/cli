<?php

namespace App\Helpers;

class Git extends BaseHelper
{
    public function currentSha(): string
    {
        return trim(shell_exec('git log -1 --pretty=%H'));
    }
}
