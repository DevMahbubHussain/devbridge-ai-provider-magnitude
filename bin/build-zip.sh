#!/usr/bin/env bash
#
# Builds the WordPress.org release zip.
#
# Copies the plugin without the files listed in .distignore, then zips it under a
# single top-level folder named after the plugin slug. The result is written to
# dist/<slug>-<version>.zip. Run it from anywhere.

set -euo pipefail

slug="devbridge-ai-provider-magnitude"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
main_file="${root}/${slug}.php"

version="$(grep -E '^ \* Version:' "${main_file}" | head -1 | sed -E 's/^ \* Version:[[:space:]]*//')"
stable_tag="$(grep -E '^Stable tag:' "${root}/readme.txt" | head -1 | sed -E 's/^Stable tag:[[:space:]]*//')"
constant="$(grep -E "define\( 'DEVBRIDGE_AI_PROVIDER_MAGNITUDE_VERSION'" "${main_file}" | sed -E "s/.*', '([^']+)'.*/\1/")"

if [[ -z "${version}" || "${version}" != "${stable_tag}" || "${version}" != "${constant}" ]]; then
	echo "Version mismatch: header='${version}' readme stable tag='${stable_tag}' constant='${constant}'." >&2
	exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

mkdir -p "${work}/${slug}" "${root}/dist"
rsync -a --exclude-from="${root}/.distignore" --exclude='.DS_Store' "${root}/" "${work}/${slug}/"

# Make file modes predictable: directories 755, files 644.
find "${work}/${slug}" -type d -exec chmod 755 {} +
find "${work}/${slug}" -type f -exec chmod 644 {} +

zip_path="${root}/dist/${slug}-${version}.zip"
rm -f "${zip_path}"
( cd "${work}" && find "${slug}" -type f | LC_ALL=C sort | zip -q -X "${zip_path}" -@ )

echo "Built ${zip_path}"
