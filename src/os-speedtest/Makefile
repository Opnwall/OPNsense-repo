PKG_NAME?= os-speedtest
VERSION?= 1.0.1
TARGET_ABI?= native

.PHONY: package install clean

package:
	TARGET_ABI="$(TARGET_ABI)" PKG_NAME="$(PKG_NAME)" VERSION="$(VERSION)" ./build.sh

install: package
	pkg add -f "dist/$(PKG_NAME).pkg"

clean:
	rm -rf work/freebsd-pkg
