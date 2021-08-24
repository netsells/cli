package cliio

import (
	"fmt"
	"os"

	"github.com/spf13/cobra"
)

type LogLevel struct {
	Verbose     bool
	VeryVerbose bool
	Debug       bool
}

var Level LogLevel

func LogVerbose(message string) {
	if Level.Verbose {
		fmt.Println(message)
	}
}

func LogVerbosef(format string, a ...interface{}) {
	LogVerbose(fmt.Sprintf(format, a...))
}

func LogVeryVerbose(message string) {
	if Level.VeryVerbose {
		fmt.Println(message)
	}
}

func LogVeryVerbosef(format string, a ...interface{}) {
	LogVeryVerbose(fmt.Sprintf(format, a...))
}

func LogDebug(message string) {
	if Level.Debug {
		fmt.Println(message)
	}
}

func LogDebugf(format string, a ...interface{}) {
	LogDebug(fmt.Sprintf(format, a...))
}

func ConfigureLogLevel(cmd *cobra.Command) {
	var verbosity, _ = cmd.Flags().GetInt("verbosity")

	switch verbosity {
	case 0:
	case 1:
		Level.Verbose = true
	case 2:
		Level.VeryVerbose = true
	case 3:
		Level.Debug = true
	default:
		ErrorStepf("invalid verbosity level (%d). Valid levels are 0-3", verbosity)
		os.Exit(1)
	}
}
