package cmd

import (
	"fmt"
	"strings"

	"github.com/netsells/cli/helpers/cliio"
	"github.com/spf13/cobra"
	"github.com/spf13/pflag"

	"github.com/spf13/viper"
)

var envPrefix = "NETSELLS"

// rootCmd represents the base command when called without any subcommands
var rootCmd = &cobra.Command{
	Use:   "netsells",
	Short: "Easily manage apps and infrastructure",
	PersistentPreRunE: func(cmd *cobra.Command, args []string) error {

		// Run init config on every command so we can do ENV fallbacks
		initConfig(cmd)

		cliio.ConfigureLogLevel(cmd)

		return nil
	},
}

// Execute adds all child commands to the root command and sets flags appropriately.
// This is called by main.main(). It only needs to happen once to the rootCmd.
func Execute() {
	cobra.CheckErr(rootCmd.Execute())
}

func init() {
	rootCmd.PersistentFlags().IntP("verbosity", "v", 0, "Print verbose logs (0-3)")
}

// initConfig reads in config file and ENV variables if set.
func initConfig(cmd *cobra.Command) {
	viper.SetEnvPrefix(envPrefix)

	viper.AddConfigPath(".")
	viper.SetConfigType("yaml")
	viper.SetConfigName(".netsells.yml")

	viper.AutomaticEnv()
	viper.ReadInConfig()

	bindFlags(cmd, viper.GetViper())
}

// Bind each cobra flag to its associated viper configuration (config file and environment variable)
func bindFlags(cmd *cobra.Command, v *viper.Viper) {
	cmd.Flags().VisitAll(func(f *pflag.Flag) {
		// Environment variables can't have dashes in them, so bind them to their equivalent
		// keys with underscores, e.g. --favorite-color to STING_FAVORITE_COLOR
		if strings.Contains(f.Name, "-") {
			envVarSuffix := strings.ToUpper(strings.ReplaceAll(f.Name, "-", "_"))
			v.BindEnv(f.Name, fmt.Sprintf("%s_%s", envPrefix, envVarSuffix))
		}

		// Apply the viper config value to the flag when the flag is not set and viper has a value
		if !f.Changed && v.IsSet(f.Name) {
			val := v.Get(f.Name)
			cmd.Flags().Set(f.Name, fmt.Sprintf("%v", val))
		}
	})
}
