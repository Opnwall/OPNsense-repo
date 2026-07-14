#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-os-mihomo}"
VERSION="${VERSION:-1.0.2}"
ORIGIN="${ORIGIN:-opnsense/os-mihomo}"
COMMENT="${COMMENT:-Mihomo proxy integration for OPNsense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/MetaCubeX/mihomo}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-tgz}"
ABI="${ABI:-universal}"
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"
MIHOMO_ASSET="${MIHOMO_ASSET:-clash-meta-freebsd-amd64.xz}"
MIHOMO_DOWNLOAD_URL="${MIHOMO_DOWNLOAD_URL:-https://github.com/Vincent-Loeng/clash-meta/releases/latest/download/$MIHOMO_ASSET}"
DOWNLOAD_TIMEOUT="${DOWNLOAD_TIMEOUT:-300}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-"$SCRIPT_DIR/work/freebsd-pkg"}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-"$SCRIPT_DIR/dist"}"
DOWNLOADDIR="$WORKDIR/downloads"

die() {
    echo "error: $*" >&2
    exit 1
}

need_file() {
    [ -e "$SCRIPT_DIR/$1" ] || die "missing required file: $1"
}

command -v pkg >/dev/null 2>&1 || die "pkg command not found. Run this script on FreeBSD/OPNsense."
command -v tar >/dev/null 2>&1 || die "tar command not found."
command -v xz >/dev/null 2>&1 || die "xz command not found."
command -v sha256 >/dev/null 2>&1 || die "sha256 command not found."
if ! command -v fetch >/dev/null 2>&1 && ! command -v curl >/dev/null 2>&1; then
    die "fetch or curl command not found."
fi

need_file "src/usr/local/etc/mihomo/config.yaml"
need_file "src/usr/local/etc/mihomo/sub/env"
need_file "src/usr/local/etc/mihomo/sub/sub.sh"
need_file "src/usr/local/etc/mihomo/sub/template_config.yaml"
need_file "src/usr/local/etc/rc.d/mihomo"
need_file "src/etc/rc.conf.d/mihomo"
need_file "src/usr/local/opnsense/service/conf/actions.d/actions_mihomo.conf"
need_file "src/usr/local/etc/inc/plugins.inc.d/mihomo.inc"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Mihomo/Menu/Menu.xml"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Mihomo/ACL/ACL.xml"
need_file "src/usr/local/www/mihomo.php"
need_file "src/usr/local/www/mihomo_logs.php"
need_file "src/usr/local/www/mihomo_sub.php"
need_file "src/usr/local/www/mihomo_sub_log.php"
need_file "src/usr/bin/mihomo_sub"
need_file "src/usr/local/bin/$MIHOMO_ASSET"
need_file "packaging/freebsd/+MANIFEST.in"
need_file "packaging/freebsd/+POST_INSTALL"
need_file "packaging/freebsd/+PRE_DEINSTALL"
need_file "packaging/freebsd/+POST_DEINSTALL"
need_file "packaging/freebsd/pkg-descr"

case "$ABI" in
    universal)
        PKG_ABI="FreeBSD:*:amd64"
        PKG_ARCH="freebsd:*:x86:64"
        ;;
    native)
        PKG_ABI="$(pkg config ABI)"
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
        die "unsupported ABI: $ABI"
        ;;
esac
unset ABI || true

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR" "$DOWNLOADDIR"

copy_tree() {
    src="$1"
    dst="$2"
    mkdir -p "$dst"
    (cd "$src" && tar --exclude '.DS_Store' --exclude '._*' --exclude '*.xz' -cf - .) | (cd "$dst" && tar -xf -)
}

download_file() {
    download_url="$1"
    download_dst="$2"
    if command -v curl >/dev/null 2>&1; then
        curl -fL --retry 3 --retry-all-errors --retry-delay 2 --connect-timeout 30 --max-time "$DOWNLOAD_TIMEOUT" -o "$download_dst" "$download_url"
    else
        fetch -T "$DOWNLOAD_TIMEOUT" -q -o "$download_dst" "$download_url"
    fi
}

unpack_binary() {
    archive="$1"
    binary_dst="$2"
    tmp="$binary_dst.tmp"

    rm -f "$tmp" "$binary_dst"
    if xz -t "$archive" >/dev/null 2>&1; then
        xz -dc "$archive" > "$tmp"
    else
        cp "$archive" "$tmp"
    fi
    mv -f "$tmp" "$binary_dst"
    chmod 0755 "$binary_dst"
    [ -s "$binary_dst" ] || die "binary is empty: $archive"
}

prepare_binary() {
    asset="$1"
    binary_url="$2"
    binary_dst="$3"
    local_asset="$SCRIPT_DIR/src/usr/local/bin/$asset"
    archive="$binary_dst.download"

    mkdir -p "$DOWNLOADDIR"
    if [ -f "$local_asset" ]; then
        echo "==> Using local asset $local_asset"
        unpack_binary "$local_asset" "$binary_dst"
    else
        echo "==> Downloading $binary_url"
        rm -f "$archive"
        download_file "$binary_url" "$archive"
        unpack_binary "$archive" "$binary_dst"
    fi
}

echo "==> Staging files"
copy_tree "$SCRIPT_DIR/src" "$STAGEDIR"
prepare_binary "$MIHOMO_ASSET" "$MIHOMO_DOWNLOAD_URL" "$DOWNLOADDIR/mihomo"
mkdir -p "$STAGEDIR/usr/local/bin"
install -m 0755 "$DOWNLOADDIR/mihomo" "$STAGEDIR/usr/local/bin/mihomo"
chmod 0755 "$STAGEDIR/usr/local/etc/mihomo/sub/sub.sh"
chmod 0755 "$STAGEDIR/usr/bin/mihomo_sub"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/mihomo"
chmod 0755 "$STAGEDIR/usr/local/opnsense/scripts/mihomo/setup_unbound.php"

echo "==> Generating plist"
find "$STAGEDIR" -type f | sed "s#^$STAGEDIR##" | sort > "$PLIST"

FLATSIZE=0
while IFS= read -r file; do
    size="$(wc -c < "$STAGEDIR$file" | tr -d ' ')"
    FLATSIZE=$((FLATSIZE + size))
done < "$PLIST"

echo "==> Generating metadata"
{
    printf 'name: "%s"\n' "$PKG_NAME"
    printf 'origin: "%s"\n' "$ORIGIN"
    printf 'version: "%s"\n' "$VERSION"
    printf 'comment: "%s"\n' "$COMMENT"
    printf 'maintainer: "%s"\n' "$MAINTAINER"
    printf 'www: "%s"\n' "$WWW"
    printf 'abi: "%s"\n' "$PKG_ABI"
    printf 'arch: "%s"\n' "$PKG_ARCH"
    printf 'prefix: "%s"\n' "$PREFIX"
    printf 'flatsize: %s\n' "$FLATSIZE"
    printf 'deps: {\n'
    printf '    jq: { origin: "textproc/jq", version: ">=0" }\n'
    printf '    curl: { origin: "ftp/curl", version: ">=0" }\n'
    printf '}\n'
    printf 'desc: <<EOD\n'
    cat "$SCRIPT_DIR/packaging/freebsd/pkg-descr"
    printf '\nEOD\n'
    printf 'files: {\n'
    while IFS= read -r file; do
        checksum="$(sha256 -q "$STAGEDIR$file")"
        printf '    "%s": "1$%s"\n' "$file" "$checksum"
    done < "$PLIST"
    printf '}\n'
    printf 'scripts: {\n'
    printf '    "post-install": <<EOS\n'
    cat "$SCRIPT_DIR/packaging/freebsd/+POST_INSTALL"
    printf '\nEOS\n'
    printf '    "pre-deinstall": <<EOS\n'
    cat "$SCRIPT_DIR/packaging/freebsd/+PRE_DEINSTALL"
    printf '\nEOS\n'
    printf '    "post-deinstall": <<EOS\n'
    cat "$SCRIPT_DIR/packaging/freebsd/+POST_DEINSTALL"
    printf '\nEOS\n'
    printf '}\n'
} > "$METADIR/+MANIFEST"
cp "$METADIR/+MANIFEST" "$METADIR/+COMPACT_MANIFEST"

echo "==> Creating package for $PKG_ABI"
PKGROOT="$WORKDIR/package-root"
TARLIST="$WORKDIR/pkg-tarlist"
rm -rf "$PKGROOT"
mkdir -p "$PKGROOT"
install -m 0644 "$METADIR/+COMPACT_MANIFEST" "$PKGROOT/+COMPACT_MANIFEST"
install -m 0644 "$METADIR/+MANIFEST" "$PKGROOT/+MANIFEST"
copy_tree "$STAGEDIR" "$PKGROOT"
{
    printf '%s\n' '+COMPACT_MANIFEST' '+MANIFEST'
    find "$PKGROOT" -type f ! -name '+COMPACT_MANIFEST' ! -name '+MANIFEST' |
        sed "s#^$PKGROOT/##" |
        sort
} > "$TARLIST"
tar -cPzf "$DISTDIR/$OUTPUT_NAME" \
    -C "$PKGROOT" \
    -s ',^etc,/etc,' \
    -s ',^usr,/usr,' \
    -T "$TARLIST"

echo "==> Package: $DISTDIR/$OUTPUT_NAME"
pkg info -F "$DISTDIR/$OUTPUT_NAME" >/dev/null
echo "==> Verified package metadata"
if command -v sha256 >/dev/null 2>&1; then
    sha256 "$DISTDIR/$OUTPUT_NAME"
fi
