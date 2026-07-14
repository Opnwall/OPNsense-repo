#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-os-ttyd}"
VERSION="${VERSION:-1.0.2}"
ORIGIN="${ORIGIN:-opnsense/os-ttyd}"
COMMENT="${COMMENT:-ttyd terminal for OPNsense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/tsl0922/ttyd}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-txz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-native}}"
OUTPUT_NAME="${OUTPUT_NAME:-os-ttyd.pkg}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WORKDIR="${WORKDIR:-"$SCRIPT_DIR/work/freebsd-pkg"}"
STAGEDIR="$WORKDIR/stage"
METADIR="$WORKDIR/meta"
RUNTIMEDIR="$WORKDIR/runtime"
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

need_file "src/etc/rc.conf.d/ttyd"
need_file "src/usr/local/etc/rc.d/os-ttyd"
need_file "src/usr/local/etc/lighttpd_webgui/conf.d/ttyd.conf"
need_file "src/usr/local/www/diag_ttyd.php"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Ttyd/Menu/Menu.xml"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Ttyd/ACL/ACL.xml"
need_file "src/usr/local/opnsense/service/conf/actions.d/actions_ttyd.conf"
need_file "packaging/freebsd/+MANIFEST.in"
need_file "packaging/freebsd/+POST_INSTALL"
need_file "packaging/freebsd/+PRE_DEINSTALL"
need_file "packaging/freebsd/+POST_DEINSTALL"
need_file "packaging/freebsd/pkg-descr"

case "$TARGET_ABI" in
	native)
		PKG_ABI="$(pkg config ABI)"
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

VENDOR_DIR="$SCRIPT_DIR/vendor/freebsd${ABI_MAJOR}-amd64"
for package in libuv libwebsockets ttyd; do
	[ -f "$VENDOR_DIR/${package}.pkg" ] || die "missing runtime package: $VENDOR_DIR/${package}.pkg"
done

rm -rf "$WORKDIR"
mkdir -p "$STAGEDIR" "$METADIR" "$RUNTIMEDIR" "$DISTDIR"

copy_tree() {
	src="$1"
	dst="$2"
	mkdir -p "$dst"
	(cd "$src" && tar --exclude '.DS_Store' -cf - .) | (cd "$dst" && tar -xf -)
}

copy_from_runtime() {
	path="$1"
	dst="${2:-$path}"
	if [ -e "$RUNTIMEDIR$path" ]; then
		mkdir -p "$STAGEDIR$(dirname "$dst")"
		cp -R -P -p "$RUNTIMEDIR$path" "$STAGEDIR$dst"
	fi
}

echo "==> Extracting bundled ttyd runtime for FreeBSD ${ABI_MAJOR}"
for package in libuv libwebsockets ttyd; do
	tar -xf "$VENDOR_DIR/${package}.pkg" -C "$RUNTIMEDIR"
done

copy_from_runtime /usr/local/bin/ttyd /usr/local/os-ttyd/bin/ttyd
copy_from_runtime /usr/local/lib/libuv.so /usr/local/os-ttyd/lib/libuv.so
copy_from_runtime /usr/local/lib/libuv.so.1 /usr/local/os-ttyd/lib/libuv.so.1
copy_from_runtime /usr/local/lib/libuv.so.1.0.0 /usr/local/os-ttyd/lib/libuv.so.1.0.0
copy_from_runtime /usr/local/lib/libwebsockets-evlib_uv.so /usr/local/os-ttyd/lib/libwebsockets-evlib_uv.so
copy_from_runtime /usr/local/lib/libwebsockets.so /usr/local/os-ttyd/lib/libwebsockets.so
copy_from_runtime /usr/local/lib/libwebsockets.so.19 /usr/local/os-ttyd/lib/libwebsockets.so.19
copy_from_runtime /usr/local/lib/libwebsockets.so.21 /usr/local/os-ttyd/lib/libwebsockets.so.21
if [ -d "$RUNTIMEDIR/usr/local/share/licenses" ]; then
	for license_dir in "$RUNTIMEDIR"/usr/local/share/licenses/libuv-* \
	    "$RUNTIMEDIR"/usr/local/share/licenses/libwebsockets-* \
	    "$RUNTIMEDIR"/usr/local/share/licenses/ttyd-*; do
		[ -d "$license_dir" ] || continue
		copy_tree "$license_dir" "$STAGEDIR/usr/local/os-ttyd/share/licenses/$(basename "$license_dir")"
	done
fi
copy_from_runtime /usr/local/share/man/man1/ttyd.1.gz /usr/local/os-ttyd/share/man/man1/ttyd.1.gz

echo "==> Staging OPNsense integration files"
copy_tree "$SCRIPT_DIR/src/etc" "$STAGEDIR/etc"
copy_tree "$SCRIPT_DIR/src/usr" "$STAGEDIR/usr"

chmod 0644 "$STAGEDIR/etc/rc.conf.d/ttyd"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/os-ttyd"
chmod 0755 "$STAGEDIR/usr/local/os-ttyd/bin/ttyd"
chmod 0644 \
	"$STAGEDIR/usr/local/etc/lighttpd_webgui/conf.d/ttyd.conf" \
	"$STAGEDIR/usr/local/www/diag_ttyd.php" \
	"$STAGEDIR/usr/local/opnsense/mvc/app/models/OPNsense/Ttyd/Menu/Menu.xml" \
	"$STAGEDIR/usr/local/opnsense/mvc/app/models/OPNsense/Ttyd/ACL/ACL.xml" \
	"$STAGEDIR/usr/local/opnsense/service/conf/actions.d/actions_ttyd.conf"

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
