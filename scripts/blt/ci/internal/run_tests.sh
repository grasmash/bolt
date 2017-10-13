#!/usr/bin/env bash

set -ev

# Ensure that at least one module gets enabled in CI env.
yaml-cli update:value blt/project.yml modules.ci.enable.0 views_ui

# Build codebase, validate, install Drupal, run basic tests.
${BLT_DIR}/vendor/bin/robo tests --no-vm --environment=ci --no-create-project
