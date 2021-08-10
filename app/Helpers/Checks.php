<?php

namespace App\Helpers;

use Closure;
use InvalidArgumentException;

class Checks extends BaseHelper
{
    public const REPORT_FILES = 'files';
    public const REPORT_BINARIES = 'binaries';

    protected function checkAndReportMissing($type, $requiredItems): bool
    {
        $missingCheckMethod = 'missing' . ucfirst($type);

        if (!method_exists($this, $missingCheckMethod)) {
            throw new InvalidArgumentException("Method {$missingCheckMethod} does not exist on Checks class");
        }

        $missingItems = $this->$missingCheckMethod($requiredItems);

        if (count($missingItems)  > 0) {
            $this->reportMissing($type, $missingItems);
        }

        return count($missingItems) > 0;
    }

    public function checkAndReportMissingFiles($requiredItems): bool
    {
        return $this->checkAndReportMissing('files', $requiredItems);
    }

    public function checkAndReportMissingBinaries($requiredItems): bool
    {
        return $this->checkAndReportMissing('binaries', $requiredItems);
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

    public function reportMissing($type, $items): void
    {
        (new CheckReporter($this->command))->reportMissing($type, $items);
    }
}
