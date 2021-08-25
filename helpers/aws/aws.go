package aws

import (
	"context"

	"github.com/aws/aws-sdk-go-v2/aws"
	"github.com/aws/aws-sdk-go-v2/config"
	netsellsConfig "github.com/netsells/cli/helpers/config"
	"github.com/spf13/cobra"
)

func RegisterCommonFlags(cmd *cobra.Command) {
	cmd.Flags().String("aws-region", "", "Override the default AWS region")
	cmd.Flags().String("aws-account-id", "", "Override the default AWS account ID")
	cmd.Flags().String("aws-profile", "", "Override the AWS profile to use")
}

func GetConfig() aws.Config {

	awsConfig := config.WithRegion(netsellsConfig.GetAwsRegion())

	if netsellsConfig.GetAwsProfile() != "" {
		awsConfig = config.WithSharedConfigProfile(netsellsConfig.GetAwsProfile())
	}

	cfg, err := config.LoadDefaultConfig(context.TODO(), awsConfig)

	if err != nil {
		panic(err)
	}

	return cfg
}
