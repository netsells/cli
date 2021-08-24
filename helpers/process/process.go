package process

import (
	"bytes"
	"errors"
	"fmt"
	"io"
	"os"
	"os/exec"
)

type Process struct {
	Cmd *exec.Cmd

	EchoOnFailure  bool
	EchoLineByLine bool
}

func NewProcess(command string, args ...string) *Process {
	cmd := exec.Command(command, args...)
	env := os.Environ()

	cmd.Env = env

	return &Process{
		Cmd:           cmd,
		EchoOnFailure: true,
	}
}

func (process Process) SetEnv(key string, value string) {
	process.Cmd.Env = append(process.Cmd.Env, key+"="+value)
}

func (process Process) Run() (string, error) {

	var outputBuffer bytes.Buffer
	var multiWriter io.Writer

	if process.EchoLineByLine {
		multiWriter = io.MultiWriter(&outputBuffer, os.Stdout)
	} else {
		multiWriter = io.MultiWriter(&outputBuffer)
	}

	process.Cmd.Stdout = multiWriter
	process.Cmd.Stderr = multiWriter

	process.Cmd.Start()

	exitError := process.Cmd.Wait()

	var e *exec.ExitError
	if exitError != nil && errors.As(exitError, &e) {
		if process.EchoOnFailure {
			fmt.Println(outputBuffer.String())
		}

		return outputBuffer.String(), exitError
	}

	return outputBuffer.String(), nil
}
