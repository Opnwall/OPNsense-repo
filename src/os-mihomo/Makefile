PKG_NAME?=	os-mihomo
VERSION?=	1.0.2
ABI?=		universal

.PHONY: package install clean

package:
	ABI="$(ABI)" PKG_NAME="$(PKG_NAME)" VERSION="$(VERSION)" ./build.sh

install: package
	pkg add -f "dist/$(PKG_NAME).pkg"

clean:
	rm -rf work/freebsd-pkg
