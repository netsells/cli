<?php

namespace App\Helpers;

use Closure;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;

class Checks
{
    public const REPORT_FILES = 'files';
    public const REPORT_BINARIES = 'binaries';

    protected function checkAndReportMissing(Command $command, $type, $requiredItems): bool
    {
        $missingCheckMethod = 'missing' . ucfirst($type);

        if (!method_exists($this, $missingCheckMethod)) {
            throw new InvalidArgumentException("Method {$missingCheckMethod} does not exist on Checks class");
        }

        $missingItems = $this->$missingCheckMethod($requiredItems);

        if (count($missingItems)  > 0) {
            $this->reportMissing($command, $type, $missingItems);
        }

        return count($missingItems) > 0;
    }

    public function checkAndReportMissingFiles(Command $command, $requiredItems): bool
    {
        return $this->checkAndReportMissing($command, 'files', $requiredItems);
    }

    public function checkAndReportMissingBinaries(Command $command, $requiredItems): bool
    {
        return $this->checkAndReportMissing($command, 'binaries', $requiredItems);
    }

    public function missingFiles(array $requiredFiles): array
    {
        return $this->missingCheck($requiredFiles, function ($file) {
            return !file_exists($file);
        });
    }

    public function missingBinaries(array $requiredbinaries): array
    {
        return $this->missingCheck($requiredbinaries, function ($binary) {
            $return = shell_exec(sprintf("which %s", escapeshellarg($binary)));

            return empty($return);
        });
    }

    protected function missingCheck(array $items, Closure $isMissingCheck): array
    {
        return array_filter($items, $isMissingCheck);
    }

    public function reportMissing(Command $command, $type, $items): void
    {
        (new CheckReporter($command))->reportMissing($type, $items);
    }
}