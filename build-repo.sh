#!/bin/sh

set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
REPO_ROOT="${REPO_ROOT:-$SCRIPT_DIR/repo}"

die()
{
    echo "error: $*" >&2
    exit 1
}

command -v pkg >/dev/null 2>&1 || die "pkg command not found; run on FreeBSD or OPNsense"
[ "$#" -gt 0 ] || die "usage: $0 package.pkg [package.pkg ...]"

for package in "$@"; do
    [ -f "$package" ] || die "package not found: $package"
    name="$(pkg query -F "$package" '%n')"
    version="$(pkg query -F "$package" '%v')"
    abi="$(pkg query -F "$package" '%q')"
    case "$name" in
        os-*) ;;
        *) echo "warning: $name is a package, but will not be listed as an OPNsense plugin" >&2 ;;
    esac
    case "$abi" in
        FreeBSD:\*:amd64)
            abi="$(pkg config ABI)"
            ;;
        FreeBSD:*:amd64) ;;
        *) die "unsupported package ABI for $name: $abi" ;;
    esac
    destination="$REPO_ROOT/$abi/All"
    mkdir -p "$destination"
    find "$destination" -type f -name "$name-*.pkg" -delete
    cp -f "$package" "$destination/$name-$version.pkg"
    echo "==> Added $name-$version to $abi"
done

for abi_dir in "$REPO_ROOT"/FreeBSD:*:amd64; do
    [ -d "$abi_dir/All" ] || continue
    echo "==> Building repository metadata: $(basename "$abi_dir")"
    pkg repo "$abi_dir"
    pkg update -f -r "$(basename "$abi_dir")" >/dev/null 2>&1 || true
done

echo "==> Repository ready: $REPO_ROOT"
