#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-os-easytier}"
VERSION="${VERSION:-1.0.1}"
ORIGIN="${ORIGIN:-opnsense/os-easytier}"
COMMENT="${COMMENT:-EasyTier mesh VPN integration for OPNsense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/EasyTier/EasyTier}"
PREFIX="/usr/local"
ABI="${ABI:-native}"
OUTPUT_NAME="${OUTPUT_NAME:-os-easytier.pkg}"
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-$SCRIPT_DIR/work/freebsd-pkg}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-$SCRIPT_DIR/dist}"

die(){ echo "error: $*" >&2; exit 1; }
command -v pkg >/dev/null 2>&1 || die "pkg is required; build on FreeBSD or OPNsense"
command -v sha256 >/dev/null 2>&1 || die "sha256 is required"

case "$ABI" in
  native) PKG_ABI="$(pkg config ABI)" ;;
  universal) PKG_ABI="FreeBSD:*:amd64" ;;
  FreeBSD:*:amd64) PKG_ABI="$ABI" ;;
  *) die "unsupported ABI: $ABI" ;;
esac
ABI_MAJOR="$(printf '%s' "$PKG_ABI" | awk -F: '{print $2}')"
[ "$ABI_MAJOR" = "*" ] || [ "$ABI_MAJOR" = "15" ] || die "only FreeBSD 15 amd64 is supported"
PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR"
(cd "$SCRIPT_DIR/src" && tar --exclude '.DS_Store' --exclude '._*' -cf - .) | (cd "$STAGEDIR" && tar -xf -)
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/easytier" "$STAGEDIR/usr/local/sbin/easytier-core" "$STAGEDIR/usr/local/sbin/easytier-cli"
find "$STAGEDIR" -type f | sed "s#^$STAGEDIR##" | sort > "$PLIST"
FLATSIZE=0
while IFS= read -r file; do size="$(wc -c < "$STAGEDIR$file" | tr -d ' ')"; FLATSIZE=$((FLATSIZE + size)); done < "$PLIST"

sed -e "s#@PKG_NAME@#$PKG_NAME#g" -e "s#@ORIGIN@#$ORIGIN#g" -e "s#@VERSION@#$VERSION#g" \
  -e "s#@COMMENT@#$COMMENT#g" -e "s#@MAINTAINER@#$MAINTAINER#g" -e "s#@WWW@#$WWW#g" \
  -e "s#@ABI@#$PKG_ABI#g" -e "s#@ARCH@#$PKG_ARCH#g" -e "s#@PREFIX@#$PREFIX#g" \
  -e "s#@FLATSIZE@#$FLATSIZE#g" -e "/@DESC@/r $SCRIPT_DIR/packaging/freebsd/pkg-descr" -e "/@DESC@/d" \
  "$SCRIPT_DIR/packaging/freebsd/+MANIFEST.in" > "$METADIR/+MANIFEST"
for hook in +POST_INSTALL +PRE_DEINSTALL +POST_DEINSTALL; do install -m 0644 "$SCRIPT_DIR/packaging/freebsd/$hook" "$METADIR/$hook"; done

pkg create -e -f tgz -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"
mv -f "$DISTDIR/$PKG_NAME-$VERSION.pkg" "$DISTDIR/$OUTPUT_NAME"
pkg info -F "$DISTDIR/$OUTPUT_NAME" >/dev/null
echo "Package: $DISTDIR/$OUTPUT_NAME"
sha256 "$DISTDIR/$OUTPUT_NAME"
