#!/usr/bin/env bash
set -euo pipefail

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: this script must be run inside a Git repository." >&2
  exit 1
fi

COMMIT_REF="${1:-HEAD}"
OUTPUT_DIR="${2:-./commit-files}"

mkdir -p "$OUTPUT_DIR"

while IFS= read -r file; do
  [ -z "$file" ] && continue
  target="$OUTPUT_DIR/$file"
  mkdir -p "$(dirname "$target")"
  git show "${COMMIT_REF}:${file}" > "$target"
  echo "Exported: $target"
done < <(git diff-tree --no-commit-id --name-only -r "$COMMIT_REF")

echo "Done. Full files from $COMMIT_REF are available in: $OUTPUT_DIR"
