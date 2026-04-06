#!/usr/bin/env bash
# Copy Laravel `public/` into cPanel `public_html` after deployment.
# Usage (on the server, from the app root):
#   bash scripts/cpanel-sync-public.sh /home/USERNAME/public_html
set -euo pipefail

dest="${1:?Usage: $0 /path/to/public_html}"

if [[ ! -d "public" ]]; then
  echo "Run this from the Laravel project root (public/ not found)." >&2
  exit 1
fi

# Replace contents of public_html with the built public assets and index.php.
rsync -a --delete public/ "${dest%/}/"
