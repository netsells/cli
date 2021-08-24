package helpers

import (
	"os"
	"os/exec"

	"github.com/netsells/cli/helpers/cliio"
)

// Check binary is installed
func IsMissingBinary(binary string) bool {
	_, err := exec.LookPath(binary)

	return err != nil
}

func IsMissingFile(file string) bool {
	_, err := os.Stat(file)

	return os.IsNotExist(err)
}

func CheckAndReportMissingBinaries(requiredBinaries []string) {
	var missing []string

	for _, binary := range requiredBinaries {
		if IsMissingBinary(binary) {
			missing = append(missing, binary)
		}
	}

	if len(missing) > 0 {
		reportMissing("binaries", missing)
	}
}

func CheckAndReportMissingFiles(requiredFiles []string) {
	var missing []string

	for _, file := range requiredFiles {
		if IsMissingFile(file) {
			missing = append(missing, file)
		}
	}

	if len(missing) > 0 {
		reportMissing("files", missing)
	}
}

func reportMissing(missingType string, missing []string) {
	cliio.ErrorStepf("Cannot run due to missing required %s:", missingType)
	for _, missingItem := range missing {
		cliio.ErrorStepf("  %s", missingItem)
	}
	os.Exit(1)
}
