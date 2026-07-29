#!/usr/bin/env bash
set -euo pipefail

attempts="${NPM_CI_ATTEMPTS:-3}"
[[ "$attempts" =~ ^[1-9][0-9]*$ ]] || {
	echo "NPM_CI_ATTEMPTS must be a positive integer." >&2
	exit 64
}

for ((attempt = 1; attempt <= attempts; attempt++)); do
	if npm ci --no-audit --no-fund --prefer-offline; then
		exit 0
	fi

	if ((attempt == attempts)); then
		echo "npm ci failed after ${attempts} attempts." >&2
		exit 1
	fi

	delay=$((attempt * 10))
	echo "npm ci attempt ${attempt}/${attempts} failed; retrying in ${delay}s." >&2
	sleep "$delay"
done
