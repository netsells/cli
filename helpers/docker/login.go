package docker

import (
	"github.com/netsells/cli/helpers/process"
)

func Login(registryAddress string, username string, password string) (string, error) {
	process := process.NewProcess("docker", "login", "--username", username, "--password", password, registryAddress)
	output, err := process.Run()

	if err != nil {
		return output, err
	}

	return output, nil
}
