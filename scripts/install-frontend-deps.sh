#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ "${1:-}" == "--rebuild" ]]; then
  # Rebuild images that define `build:` (backend, worker, scheduler). Frontend uses a pulled Node image.
  docker compose build
  shift
fi

# Writes to ./frontend/node_modules on the host (bind mount). No local Node required.
exec docker compose run --rm --no-deps frontend npm install "$@"
