#!/bin/sh
set -eu
cd "$(dirname "$0")/../../../webbsunitapp"
exec sh .docker/local/start.sh
