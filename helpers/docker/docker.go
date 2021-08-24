package docker

import "github.com/netsells/cli/helpers/config"

func DockerPrefixedTag() string {
	tag := config.GetTag()

	tagPrefix := config.GetTaxPrefix()

	if tagPrefix != "" {
		return tagPrefix + tag
	}

	environmentTagPrefix := config.GetEnvironment()

	if environmentTagPrefix != "" {
		return environmentTagPrefix + "-" + tag
	}

	return tag
}
