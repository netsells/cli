package helpers

import (
	"os/exec"
)

func GetCurrentSha() string {
	buildOutput, err := exec.Command("git", "log", "-1", "--pretty=format:%H").Output()

	if err != nil {
		return ""
	}

	return string(buildOutput)
}
