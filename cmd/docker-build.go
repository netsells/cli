package cmd

import (
	"os"

	"github.com/netsells/cli/helpers"
	"github.com/netsells/cli/helpers/aws/ecr"
	"github.com/netsells/cli/helpers/cliio"
	"github.com/netsells/cli/helpers/config"
	"github.com/netsells/cli/helpers/docker"
	"github.com/netsells/cli/helpers/process"
	"github.com/spf13/cobra"
)

type CallBuildContext struct {
	Tag     string
	Service string
}

var dockerBuildCmd = &cobra.Command{
	Use:   "docker:build",
	Short: "Builds docker-compose ready for prod",
	Run:   runDockerBuildCmd,
}

func init() {
	rootCmd.AddCommand(dockerBuildCmd)

	dockerBuildCmd.Flags().String("aws-region", "", "AWS region")
	dockerBuildCmd.Flags().String("tag", helpers.GetCurrentSha(), "The tag that should be built with the images. Defaults to the current commit SHA")
	dockerBuildCmd.Flags().String("tag-prefix", "", "The tag prefix that should be built with the images. Defaults to null")
	dockerBuildCmd.Flags().String("environment", "", "The destination environment for the images")
	dockerBuildCmd.Flags().StringArray("services", []string{}, "The service that should be built. Not defining this will push all services")
}

func runDockerBuildCmd(cmd *cobra.Command, args []string) {
	helpers.SetCmd(cmd)

	if config.GetTag() == "" {
		cliio.ErrorStep("No tag set or available from git. Cannot proceed.")
		os.Exit(1)
	}

	helpers.CheckAndReportMissingBinaries([]string{"docker"})
	helpers.CheckAndReportMissingFiles([]string{"docker-compose.yml", "docker-compose.prod.yml"})

	prefixedTag := docker.DockerPrefixedTag()
	services := config.GetDockerServices()

	err := ecr.AuthenticateDocker()
	if err != nil {
		cliio.FatalStep(err.Error())
	}

	if len(services) == 0 {
		cliio.Stepf("Building docker images for all services with tag %s", prefixedTag)

		success := callBuild(CallBuildContext{
			Tag: prefixedTag,
		})

		if success {
			cliio.SuccessfulStep("Docker images built.")
			os.Exit(0)
		}

		os.Exit(1)
	}

	cliio.Stepf("Building docker images for services with tag %s: %v", prefixedTag, services)

	for _, service := range services {
		success := callBuild(CallBuildContext{
			Tag:     prefixedTag,
			Service: service,
		})

		if !success {
			os.Exit(1)
		}
	}

	cliio.SuccessfulStep("Docker images built.")
	os.Exit(0)
}

func callBuild(context CallBuildContext) bool {
	parts := []string{
		"-f", "docker-compose.yml",
		"-f", "docker-compose.prod.yml",
		"build", "--no-cache",
	}

	if context.Service != "" {
		parts = append(parts, context.Service)
	}

	process := process.NewProcess("docker-compose", parts...)
	process.SetEnv("TAG", context.Tag)
	process.EchoLineByLine = true
	_, err := process.Run()

	if err != nil {
		cliio.ErrorStep("Unable to build all images, check the above output for reasons why.")
		return false
	}

	return true
}
