#!/bin/sh
set -eu

PKG_NAME="${PKG_NAME:-os-lucky}"
VERSION="${VERSION:-1.0.2}"
ORIGIN="${ORIGIN:-opnsense/os-lucky}"
COMMENT="${COMMENT:-Lucky network toolbox integration for OPNsense}"
MAINTAINER="${MAINTAINER:-https://github.com/Opnwall/}"
WWW="${WWW:-https://github.com/gdy666/lucky}"
PREFIX="${PREFIX:-/usr/local}"
FORMAT="${FORMAT:-tgz}"
TARGET_ABI="${TARGET_ABI:-${ABI:-native}}"
OUTPUT_NAME="${OUTPUT_NAME:-${PKG_NAME}.pkg}"
LUCKY_VERSION="${LUCKY_VERSION:-2.27.2}"
LUCKY_ASSET="${LUCKY_ASSET:-lucky_${LUCKY_VERSION}_freebsd_x86_64.tar.gz}"
LUCKY_DOWNLOAD_URL="${LUCKY_DOWNLOAD_URL:-https://github.com/gdy666/lucky/releases/download/v${LUCKY_VERSION}/${LUCKY_ASSET}}"
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
if ! command -v fetch >/dev/null 2>&1 && ! command -v curl >/dev/null 2>&1; then
	die "fetch or curl command not found."
fi

need_file "src/etc/rc.conf.d/lucky"
need_file "src/usr/local/bin/$LUCKY_ASSET"
need_file "src/usr/local/etc/rc.d/os-lucky"
need_file "src/usr/local/opnsense/service/conf/actions.d/actions_lucky.conf"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Lucky/Menu/Menu.xml"
need_file "src/usr/local/opnsense/mvc/app/models/OPNsense/Lucky/ACL/ACL.xml"
need_file "src/usr/local/www/lucky.php"
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
mkdir -p "$STAGEDIR/usr/local/bin" "$METADIR" "$DISTDIR" "$DOWNLOADDIR"

copy_tree() {
	src="$1"
	dst="$2"
	mkdir -p "$dst"
	(cd "$src" && tar --exclude '.DS_Store' --exclude "$LUCKY_ASSET" -cf - .) | (cd "$dst" && tar -xf -)
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

prepare_lucky_binary() {
	local_asset="$SCRIPT_DIR/src/usr/local/bin/$LUCKY_ASSET"
	archive="$DOWNLOADDIR/$LUCKY_ASSET"
	extract_dir="$DOWNLOADDIR/extract"
	binary_dst="$DOWNLOADDIR/lucky"

	if [ -f "$local_asset" ]; then
		echo "==> Using local asset $local_asset"
		cp -f "$local_asset" "$archive"
	else
		echo "==> Downloading $LUCKY_DOWNLOAD_URL"
		download_file "$LUCKY_DOWNLOAD_URL" "$archive"
	fi

	rm -rf "$extract_dir"
	mkdir -p "$extract_dir"
	tar -xzf "$archive" -C "$extract_dir"
	found="$(find "$extract_dir" -type f -name lucky | head -1)"
	[ -n "$found" ] || die "lucky binary not found in $archive"
	cp -f "$found" "$binary_dst"
	chmod 0755 "$binary_dst"
	[ -s "$binary_dst" ] || die "binary is empty: $archive"
}

echo "==> Staging files"
copy_tree "$SCRIPT_DIR/src" "$STAGEDIR"
prepare_lucky_binary
install -m 0755 "$DOWNLOADDIR/lucky" "$STAGEDIR/usr/local/bin/lucky"

chmod 0644 "$STAGEDIR/etc/rc.conf.d/lucky"
chmod 0755 "$STAGEDIR/usr/local/etc/rc.d/os-lucky"
chmod 0644 \
	"$STAGEDIR/usr/local/www/lucky.php" \
	"$STAGEDIR/usr/local/opnsense/service/conf/actions.d/actions_lucky.conf" \
	"$STAGEDIR/usr/local/opnsense/mvc/app/models/OPNsense/Lucky/Menu/Menu.xml" \
	"$STAGEDIR/usr/local/opnsense/mvc/app/models/OPNsense/Lucky/ACL/ACL.xml"

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
