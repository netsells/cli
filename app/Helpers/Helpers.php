<?php

namespace App\Helpers;

class Helpers
{
    public function git(): Git
    {
        return new Git();
    }

    public function checks(): Checks
    {
        return new Checks();
    }

    public function console(): Console
    {
        return new Console();
    }

    public function aws(): Aws
    {
        return new Aws($this);
    }

    public function netsellsFile(): NetsellsFile
    {
        return new NetsellsFile();
    }
}