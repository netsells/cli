package sts

import (
	"context"

	"github.com/aws/aws-sdk-go-v2/service/sts"
	"github.com/netsells/cli/helpers/aws"
	"github.com/netsells/cli/helpers/cliio"
)

func GetCallerIdentity() (*sts.GetCallerIdentityOutput, error) {
	ctx := context.Background()

	client := sts.NewFromConfig(aws.GetConfig())

	input := &sts.GetCallerIdentityInput{}

	output, err := client.GetCallerIdentity(ctx, input)

	if err != nil {
		cliio.ErrorStepf("Failed to get caller identity: %s", err.Error())

		return nil, err
	}

	return output, nil
}

func GetCallerArn() string {
	identity, err := GetCallerIdentity()

	if err == nil {
		return *identity.Arn
	}

	return ""
}
