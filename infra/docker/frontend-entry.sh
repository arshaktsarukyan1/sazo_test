#!/bin/sh
set -e
uid="${DOCKER_UID:-1000}"
gid="${DOCKER_GID:-1000}"

if [ "$(id -u)" = "0" ]; then
  mkdir -p /app/node_modules /app/.next
  # Stale root-owned trees (old Compose or host sudo) break npm as non-root.
  chown -R "${uid}:${gid}" /app/node_modules /app/.next 2>/dev/null || true
  exec su-exec "${uid}:${gid}" "$@"
fi

exec "$@"
