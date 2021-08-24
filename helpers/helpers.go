package helpers

import "github.com/spf13/cobra"

var command cobra.Command

func SetCmd(cmd *cobra.Command) {
	command = *cmd
}

func GetCmd() *cobra.Command {
	return &command
}
