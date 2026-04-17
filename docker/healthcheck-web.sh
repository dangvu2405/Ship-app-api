#!/bin/sh
set -e
http_port="${PORT:-8080}"
curl -fsS "http://127.0.0.1:${http_port}/up" >/dev/null
