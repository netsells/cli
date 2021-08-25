package config

import (
	"github.com/netsells/cli/helpers"
	"github.com/netsells/cli/helpers/cliio"
	"github.com/spf13/viper"
)

func GetTag() string {
	return getString("tag", "", "")
}

func GetTaxPrefix() string {
	return getString("tag-prefix", "", "")
}

func GetEnvironment() string {
	return getString("environment", "", "")
}

func GetAwsRegion() string {
	return getString("aws-region", "docker.aws.region", "eu-west-2")
}

func GetAwsProfile() string {
	return getString("aws-profile", "", "")
}

func GetAwsAccountId() string {
	return getString("aws-account-id", "docker.aws.account-id", "422860057079")
}

func GetDockerServices() []string {
	return getStringArray("services", "docker.services")
}

func getString(flag string, filePath string, defaultValue string) string {
	v := viper.GetViper()

	cliio.LogDebugf("Fetching config for flag %s", flag)

	// Try from cli argument
	value, _ := helpers.GetCmd().Flags().GetString(flag)

	if value != "" {
		cliio.LogDebugf("Got value for flag %s - %s", flag, value)
		return value
	}

	if filePath != "" {
		cliio.LogDebugf("Now trying the netsells file in path %s", filePath)

		pathValue := v.GetString(filePath)

		if pathValue != "" {
			cliio.LogDebugf("Got %s from file path %s", pathValue, filePath)
			return pathValue
		}
	}

	cliio.LogDebugf("Unable to get value from flag or netsells file, falling back to default: %s", defaultValue)

	return defaultValue
}

func getStringArray(flag string, filePath string) []string {
	v := viper.GetViper()

	// Try from cli argument
	values, _ := helpers.GetCmd().Flags().GetStringArray(flag)

	if len(values) > 0 {
		return values
	}

	if filePath == "" {
		return []string{}
	}

	return v.GetStringSlice(filePath)
}
