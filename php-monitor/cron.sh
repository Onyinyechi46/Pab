#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

php "$SCRIPT_DIR/monitor.php" >> "$SCRIPT_DIR/monitor.log" 2>&1
