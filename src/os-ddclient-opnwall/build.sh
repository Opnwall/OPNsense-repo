#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-os-ddclient-opnwall}"
VERSION="${VERSION:-1.0.2}"
ORIGIN="${ORIGIN:-opnwall/os-ddclient-opnwall}"
COMMENT="${COMMENT:-Extended Dynamic DNS client for OPNsense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/Opnwall/OPNsense-dyndns}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-txz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-FreeBSD:*:amd64}}"
OUTPUT_NAME="${OUTPUT_NAME:-os-ddclient-opnwall.pkg}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-"$SCRIPT_DIR/work/freebsd-pkg"}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-"$SCRIPT_DIR/dist"}"

die() { echo "error: $*" >&2; exit 1; }
command -v pkg >/dev/null 2>&1 || die "pkg command not found; run on FreeBSD/OPNsense"

case "$TARGET_ABI" in
  native) PKG_ABI="$(pkg config ABI)" ;;
  FreeBSD:*:amd64) PKG_ABI="$TARGET_ABI" ;;
  *) die "unsupported ABI: $TARGET_ABI" ;;
esac
ABI_MAJOR="$(printf '%s\n' "$PKG_ABI" | awk -F: '{print $2}')"
PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR"
(cd "$SCRIPT_DIR/src" && tar --exclude '.DS_Store' --exclude '._*' --exclude '__pycache__' -cf - .) | (cd "$STAGEDIR" && tar -xf -)

mkdir -p "$STAGEDIR/usr/local/opnsense/version"
cat > "$STAGEDIR/usr/local/opnsense/version/ddclient-opnwall" <<EOF
{"product_abi":"26.7","product_arch":"amd64","product_email":"$MAINTAINER","product_id":"$PKG_NAME","product_name":"ddclient-opnwall","product_tier":"4","product_version":"$VERSION","product_website":"$WWW"}
EOF

find "$STAGEDIR" \( -type f -o -type l \) | sed "s#^$STAGEDIR##" | sort > "$PLIST"
FLATSIZE="$(find "$STAGEDIR" -type f -exec stat -f %z {} \; | awk '{s += $1} END {print s + 0}')"

sed -e "s#@PKG_NAME@#$PKG_NAME#g" -e "s#@ORIGIN@#$ORIGIN#g" \
  -e "s#@VERSION@#$VERSION#g" -e "s#@COMMENT@#$COMMENT#g" \
  -e "s#@MAINTAINER@#$MAINTAINER#g" -e "s#@WWW@#$WWW#g" \
  -e "s#@ABI@#$PKG_ABI#g" -e "s#@ARCH@#$PKG_ARCH#g" \
  -e "s#@PREFIX@#$PREFIX#g" -e "s#@FLATSIZE@#$FLATSIZE#g" \
  -e "/@DESC@/r $SCRIPT_DIR/packaging/freebsd/pkg-descr" -e "/@DESC@/d" \
  "$SCRIPT_DIR/packaging/freebsd/+MANIFEST.in" > "$METADIR/+MANIFEST"

for script in +PRE_INSTALL +POST_INSTALL +PRE_DEINSTALL +POST_DEINSTALL; do
  install -m 0755 "$SCRIPT_DIR/packaging/freebsd/$script" "$METADIR/$script"
done

pkg create -f "$FORMAT" -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"
CREATED="$DISTDIR/$PKG_NAME-$VERSION.pkg"
[ ! -f "$CREATED" ] || mv -f "$CREATED" "$DISTDIR/$OUTPUT_NAME"
pkg info -F "$DISTDIR/$OUTPUT_NAME"
