package helpers

import (
	"os"
	"testing"
)

func setGitDir(t *testing.T, dir string) {
	old_GIT_DIR := os.Getenv("GIT_DIR")
	os.Setenv("GIT_DIR", dir)
	t.Cleanup(func() {
		os.Setenv("GIT_DIR", old_GIT_DIR)
	})
}

func TestShaNoRepo(t *testing.T) {

	// This is not the simple.git folder on purpose
	setGitDir(t, "./fixtures")

	sha := GetCurrentSha()
	if sha != "" {
		t.Errorf("Expected empty string, got %s", sha)
	}
}

func TestShaRepo(t *testing.T) {

	setGitDir(t, "./fixtures/simple.git")

	sha := GetCurrentSha()
	if sha == "" {
		t.Error("Expected non-empty string")
	}

	if sha != "6f1a2405cace1633d89a79c74c65f22fe78f9659" {
		t.Errorf("Expected 6f1a2405cace1633d89a79c74c65f22fe78f9659, got %s", sha)
	}
}
