<?php

namespace App\Helpers;

class Checks {

    public function hasMissingFiles(array $requiredFiles): bool
    {
        return count($this->missingFiles($requiredFiles)) > 0;
    }

    public function missingFiles(array $requiredFiles): array
    {
        $missing = [];

        foreach ($requiredFiles as $requiredFile) {
            if (!file_exists($requiredFile)) {
                $missing[] = $requiredFile;
            }
        }

        return $missing;
    }
}