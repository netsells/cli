package cliio

import (
	"fmt"
	"os"

	"github.com/fatih/color"
	"github.com/manifoldco/promptui"
)

type DefaultQuestion struct {
	Question string
	Default  string
}

func CommentText(format string, a ...interface{}) string {
	return color.New(color.FgYellow).Sprintf(format, a...)
}

func Step(text string) {
	step(text, color.FgBlue)
}

func Stepf(format string, a ...interface{}) {
	Step(fmt.Sprintf(format, a...))
}

func SuccessfulStep(text string) {
	step(text, color.FgGreen)
}

func SuccessfulStepf(format string, a ...interface{}) {
	SuccessfulStep(fmt.Sprintf(format, a...))
}

func WarnStep(text string) {
	step(text, color.FgYellow)
}

func WarStepf(format string, a ...interface{}) {
	WarnStep(fmt.Sprintf(format, a...))
}

func ErrorStep(text string) {
	step(text, color.FgRed)
}

func ErrorStepf(format string, a ...interface{}) {
	ErrorStep(fmt.Sprintf(format, a...))
}

func FatalStep(text string) {
	step(text, color.FgRed)
	os.Exit(1)
}

func FatalStepf(format string, a ...interface{}) {
	ErrorStep(fmt.Sprintf(format, a...))
	os.Exit(1)
}

func Lines(lines []string) {
	for _, line := range lines {
		Line(line)
	}
}

func AskStep(question DefaultQuestion) {
	ask(question, formatStepOutput(question.Question, color.FgYellow))
}

func ConfirmStep(question DefaultQuestion) {
	confirm(question, formatStepOutput(question.Question, color.FgYellow))
}

func ask(question DefaultQuestion, text string) (string, error) {
	prompt := promptui.Prompt{
		Label:   text,
		Default: question.Default,
	}

	result, err := prompt.Run()

	if err != nil {
		fmt.Printf("Prompt failed %v\n", err)
		return "", err
	}

	return result, nil
}

func confirm(question DefaultQuestion, text string) (string, error) {
	prompt := promptui.Prompt{
		Label:     text,
		Default:   question.Default,
		IsConfirm: true,
	}

	result, err := prompt.Run()

	if err != nil {
		fmt.Printf("Prompt failed %v\n", err)
		return "", err
	}

	return result, nil
}

func formatStepOutput(text string, colour color.Attribute) string {
	colourString := color.New(colour)
	bold := color.New(color.Bold)

	return colourString.Sprintf("==>") + " " + bold.Sprintf(text)
}

func step(text string, colour color.Attribute) {
	Line(formatStepOutput(text, colour))
}

func Line(text string) {
	println(text)
}
