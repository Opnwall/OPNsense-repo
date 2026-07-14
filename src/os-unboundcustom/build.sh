#!/bin/sh

set -eu

PKG_NAME="${PKG_NAME:-os-unboundcustom}"
VERSION="${VERSION:-1.0.2}"
ORIGIN="${ORIGIN:-opnsense/os-unboundcustom}"
COMMENT="${COMMENT:-Safe custom options for Unbound DNS}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://pfchina.org/}"
ABI="${ABI:-universal}"
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-$SCRIPT_DIR/work/freebsd-pkg}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-$SCRIPT_DIR/dist}"

die()
{
    echo "error: $*" >&2
    exit 1
}

command -v pkg >/dev/null 2>&1 || die "pkg command not found; run this script on FreeBSD or OPNsense"
command -v tar >/dev/null 2>&1 || die "tar command not found"
[ -d "$SCRIPT_DIR/src/opnsense" ] || die "missing source directory: src/opnsense"
[ -f "$SCRIPT_DIR/pkg-descr" ] || die "missing pkg-descr"

case "$ABI" in
    universal)
        PKG_ABI='FreeBSD:*:amd64'
        PKG_ARCH='freebsd:*:x86:64'
        ;;
    native)
        PKG_ABI="$(pkg config ABI 2>/dev/null || pkg -vv | awk -F\" '/ABI =/ {print $2; exit}')"
        case "$PKG_ABI" in
            FreeBSD:*:amd64) ;;
            *) die "unsupported native ABI: $PKG_ABI" ;;
        esac
        ABI_MAJOR="$(printf '%s\n' "$PKG_ABI" | awk -F: '{print $2}')"
        PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"
        ;;
    FreeBSD:*:amd64)
        PKG_ABI="$ABI"
        ABI_MAJOR="$(printf '%s\n' "$PKG_ABI" | awk -F: '{print $2}')"
        PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"
        ;;
    *)
        die "unsupported ABI: $ABI (use universal, native, or FreeBSD:<major>:amd64)"
        ;;
esac

echo "==> Cleaning build workspace"
rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR/usr/local/opnsense" "$METADIR" "$DISTDIR"

echo "==> Staging plugin files"
(cd "$SCRIPT_DIR/src/opnsense" && tar --exclude '.DS_Store' --exclude '._*' -cf - .) |
    (cd "$STAGEDIR/usr/local/opnsense" && tar -xf -)
chmod 0755 "$STAGEDIR/usr/local/opnsense/scripts/OPNsense/Unboundcustom/apply.sh"
mkdir -p "$STAGEDIR/usr/local/opnsense/version"
{
    printf '{\n'
    printf '    "product_abi": "26.7",\n'
    printf '    "product_arch": "amd64",\n'
    printf '    "product_email": "%s",\n' "$MAINTAINER"
    printf '    "product_id": "%s",\n' "$PKG_NAME"
    printf '    "product_name": "unboundcustom",\n'
    printf '    "product_tier": "4",\n'
    printf '    "product_version": "%s",\n' "$VERSION"
    printf '    "product_website": "%s"\n' "$WWW"
    printf '}\n'
} > "$STAGEDIR/usr/local/opnsense/version/unboundcustom"

echo "==> Generating package file list"
find "$STAGEDIR" \( -type f -o -type l \) | sed "s#^$STAGEDIR##" | sort > "$PLIST"

FLATSIZE=0
while IFS= read -r file; do
    if [ -L "$STAGEDIR$file" ]; then
        size=0
    else
        size="$(wc -c < "$STAGEDIR$file" | tr -d ' ')"
    fi
    FLATSIZE=$((FLATSIZE + size))
done < "$PLIST"

echo "==> Generating package metadata for $PKG_ABI"
{
    printf 'name: "%s"\n' "$PKG_NAME"
    printf 'origin: "%s"\n' "$ORIGIN"
    printf 'version: "%s"\n' "$VERSION"
    printf 'comment: "%s"\n' "$COMMENT"
    printf 'maintainer: "%s"\n' "$MAINTAINER"
    printf 'www: "%s"\n' "$WWW"
    printf 'abi: "%s"\n' "$PKG_ABI"
    printf 'arch: "%s"\n' "$PKG_ARCH"
    printf 'prefix: "/usr/local"\n'
    printf 'flatsize: %s\n' "$FLATSIZE"
    printf 'licenselogic: "single"\n'
    printf 'licenses: [ "BSD2CLAUSE" ]\n'
    printf 'categories: [ "dns" ]\n'
    printf 'annotations: {\n'
    printf '  product_abi: "26.7",\n'
    printf '  product_arch: "amd64",\n'
    printf '  product_email: "%s",\n' "$MAINTAINER"
    printf '  product_id: "%s",\n' "$PKG_NAME"
    printf '  product_name: "unboundcustom",\n'
    printf '  product_tier: "4",\n'
    printf '  product_version: "%s",\n' "$VERSION"
    printf '  product_website: "%s"\n' "$WWW"
    printf '}\n'
    printf 'desc: <<EOD\n'
    cat "$SCRIPT_DIR/pkg-descr"
    printf '\nEOD\n'
} > "$METADIR/+MANIFEST"

install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+POST_INSTALL" "$METADIR/+POST_INSTALL"
install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+PRE_DEINSTALL" "$METADIR/+PRE_DEINSTALL"
install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+POST_DEINSTALL" "$METADIR/+POST_DEINSTALL"

echo "==> Creating package"
pkg create -f tgz -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"

CREATED="$DISTDIR/$PKG_NAME-$VERSION.pkg"
[ -f "$CREATED" ] || die "expected package was not created: $CREATED"
if [ "$(basename "$CREATED")" != "$OUTPUT_NAME" ]; then
    mv -f "$CREATED" "$DISTDIR/$OUTPUT_NAME"
fi

PACKAGE="$DISTDIR/$OUTPUT_NAME"
pkg info -F "$PACKAGE" >/dev/null
echo "==> Package verified: $PACKAGE"
if command -v sha256 >/dev/null 2>&1; then
    sha256 "$PACKAGE"
elif command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$PACKAGE"
fi
