<?php

namespace App\Helpers;

use Symfony\Component\Console\Output\OutputInterface;

class Console
{
    public function verbose($message)
    {
        $this->command->getOutput()->writeln($message, OutputInterface::VERBOSITY_VERBOSE);
    }

    public function veryVerbose($message)
    {
        $this->command->getOutput()->writeln($message, OutputInterface::VERBOSITY_VERY_VERBOSE);
    }

    public function debug($message)
    {
        $this->command->getOutput()->writeln($message, OutputInterface::VERBOSITY_DEBUG);
    }
}
