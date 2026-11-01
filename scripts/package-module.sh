#!/usr/bin/env bash
set -euo pipefail

usage() {
    printf 'Usage: bash scripts/package-module.sh <module> <version> [output-directory]\n' >&2
    exit 2
}

[[ $# -ge 2 && $# -le 3 ]] || usage

module=$1
version=$2
output_dir=${3:-dist}

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
    printf 'Invalid version: %s\n' "$version" >&2
    usage
fi

case "$module" in
    nhanhoa_cloud|nhanhoa_dns|nhanhoa_email|nhanhoa_hosting|nhanhoa_ssl)
        hostbill_module_type=Hosting
        ;;
    nhanhoa_domain)
        hostbill_module_type=Domain
        ;;
    *)
        printf 'Unknown module: %s\n' "$module" >&2
        usage
        ;;
esac

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
module_dir="$repo_root/$module"
entrypoint="$module_dir/class.$module.php"

if [[ ! -f "$entrypoint" || ! -f "$module_dir/README.md" || ! -f "$module_dir/LICENSE" ]]; then
    printf 'Module is missing its entrypoint, README.md, or LICENSE: %s\n' "$module" >&2
    exit 1
fi

if [[ "$output_dir" != /* ]]; then
    output_dir="$repo_root/$output_dir"
fi
mkdir -p "$output_dir"
output_dir=$(cd "$output_dir" && pwd)

package_root=$(mktemp -d)
trap 'rm -rf "$package_root"' EXIT

install_path="includes/modules/$hostbill_module_type/$module"
mkdir -p "$package_root/$install_path"
cp -a "$module_dir/." "$package_root/$install_path/"

archive_name="$module-$version.zip"
archive_path="$output_dir/$archive_name"
(cd "$package_root" && zip -qr "$archive_path" "$install_path")

if ! unzip -Z1 "$archive_path" | awk -v prefix="$install_path/" 'index($0, prefix) != 1 { bad = 1 } END { exit bad }'; then
    printf 'Archive contains files outside its expected install path.\n' >&2
    exit 1
fi

(cd "$output_dir" && sha256sum "$archive_name" > "$archive_name.sha256")
printf '%s\n' "$archive_path" "$archive_path.sha256"