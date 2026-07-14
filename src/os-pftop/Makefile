PKG_NAME?=	os-pftop
OUTPUT_NAME?=	os-pftop.pkg
VERSION?=	1.0
ABI?=		FreeBSD:*:amd64

.PHONY: package clean

package:
	ABI="$(ABI)" PKG_NAME="$(PKG_NAME)" VERSION="$(VERSION)" OUTPUT_NAME="$(OUTPUT_NAME)" ./build.sh

clean:
	rm -rf work/freebsd-pkg
