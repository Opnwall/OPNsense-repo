#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-os-staticarp}"
VERSION="${VERSION:-1.0.2}"
ORIGIN="${ORIGIN:-opnsense/os-staticarp}"
COMMENT="${COMMENT:-Static ARP binding integration for OPNsense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/Opnwall/Static-Binding-for-OPNsense}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-tgz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-native}}"
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-"$SCRIPT_DIR/work/freebsd-pkg"}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
PLIST="$WORKDIR/pkg-plist"
DISTDIR="${DISTDIR:-"$SCRIPT_DIR/dist"}"

die() {
	echo "error: $*" >&2
	exit 1
}

need_file() {
	[ -e "$SCRIPT_DIR/$1" ] || die "missing required file: $1"
}

command -v pkg >/dev/null 2>&1 || die "pkg command not found. Run this script on FreeBSD/OPNsense."
command -v tar >/dev/null 2>&1 || die "tar command not found."

need_file "src/etc/rc.conf.d/staticarp"
need_file "src/usr/local/sbin/staticarpctl"
need_file "src/usr/local/etc/rc.d/os-staticarp"
need_file "src/usr/local/opnsense/service/conf/actions.d/actions_staticarp.conf"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Staticarp/Menu/Menu.xml"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Staticarp/ACL/ACL.xml"
need_file "src/usr/local/www/services_staticarp.php"
need_file "packaging/freebsd/+MANIFEST.in"
need_file "packaging/freebsd/+POST_INSTALL"
need_file "packaging/freebsd/+PRE_DEINSTALL"
need_file "packaging/freebsd/+POST_DEINSTALL"
need_file "packaging/freebsd/pkg-descr"

case "$TARGET_ABI" in
	native)
		PKG_ABI="$(pkg config ABI 2>/dev/null || pkg -vv | awk -F'"' '/ABI =/ {print $2; exit}')"
		;;
	FreeBSD:*:amd64)
		PKG_ABI="$TARGET_ABI"
		;;
	*)
		die "unsupported ABI: $TARGET_ABI"
		;;
esac

case "$PKG_ABI" in
	FreeBSD:*:amd64)
		ABI_MAJOR="$(printf '%s\n' "$PKG_ABI" | awk -F: '{print $2}')"
		PKG_ARCH="freebsd:${ABI_MAJOR}:x86:64"
		;;
	*)
		die "unsupported ABI: $PKG_ABI"
		;;
esac

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR" "$METADIR" "$DISTDIR"

copy_tree() {
	src="$1"
	dst="$2"
	mkdir -p "$dst"
	(cd "$src" && tar --exclude '.DS_Store' -cf - .) | (cd "$dst" && tar -xf -)
}

echo "==> Staging files"
copy_tree "$SCRIPT_DIR/src" "$STAGEDIR"

chmod 0644 "$STAGEDIR/etc/rc.conf.d/staticarp"
chmod 0755 \
	"$STAGEDIR/usr/local/sbin/staticarpctl" \
	"$STAGEDIR/usr/local/etc/rc.d/os-staticarp"
chmod 0644 \
	"$STAGEDIR/usr/local/www/services_staticarp.php" \
	"$STAGEDIR/usr/local/opnsense/service/conf/actions.d/actions_staticarp.conf" \
	"$STAGEDIR/usr/local/opnsense/mvc/app/models/OPNsense/Staticarp/Menu/Menu.xml" \
	"$STAGEDIR/usr/local/opnsense/mvc/app/models/OPNsense/Staticarp/ACL/ACL.xml"

echo "==> Generating plist"
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

echo "==> Generating metadata"
sed \
	-e "s#@PKG_NAME@#$PKG_NAME#g" \
	-e "s#@ORIGIN@#$ORIGIN#g" \
	-e "s#@VERSION@#$VERSION#g" \
	-e "s#@COMMENT@#$COMMENT#g" \
	-e "s#@MAINTAINER@#$MAINTAINER#g" \
	-e "s#@WWW@#$WWW#g" \
	-e "s#@ABI@#$PKG_ABI#g" \
	-e "s#@ARCH@#$PKG_ARCH#g" \
	-e "s#@PREFIX@#$PREFIX#g" \
	-e "s#@FLATSIZE@#$FLATSIZE#g" \
	-e "/@DESC@/r $SCRIPT_DIR/packaging/freebsd/pkg-descr" \
	-e "/@DESC@/d" \
	"$SCRIPT_DIR/packaging/freebsd/+MANIFEST.in" > "$METADIR/+MANIFEST"

install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+POST_INSTALL" "$METADIR/+POST_INSTALL"
install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+PRE_DEINSTALL" "$METADIR/+PRE_DEINSTALL"
install -m 0644 "$SCRIPT_DIR/packaging/freebsd/+POST_DEINSTALL" "$METADIR/+POST_DEINSTALL"

echo "==> Creating package for $PKG_ABI"
pkg create -f "$FORMAT" -r "$STAGEDIR" -m "$METADIR" -p "$PLIST" -o "$DISTDIR"

CREATED="$DISTDIR/$PKG_NAME-$VERSION.pkg"
if [ -f "$CREATED" ] && [ "$(basename "$CREATED")" != "$OUTPUT_NAME" ]; then
	mv -f "$CREATED" "$DISTDIR/$OUTPUT_NAME"
fi

echo "==> Package: $DISTDIR/$OUTPUT_NAME"
pkg info -F "$DISTDIR/$OUTPUT_NAME" >/dev/null
echo "==> Verified package metadata"
if command -v sha256 >/dev/null 2>&1; then
	sha256 "$DISTDIR/$OUTPUT_NAME"
fi
