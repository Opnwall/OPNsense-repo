# Opnwall OPNsense Community Repository

Community packages and plugins for OPNsense amd64 systems.

## Install on OPNsense

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/opnsense-repo/opnwall.conf
pkg update -f
```

Then open **System > Firmware > Plugins**. Packages whose names start with
`os-` are displayed as plugins.

Remove the repository configuration without uninstalling installed plugins:

```sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

## Available plugins

| Package | Version | Description |
| --- | --- | --- |
| `os-unboundcustom` | 1.2.5 | Safe custom options for Unbound DNS |

## Maintainer workflow

Run `build-repo.sh` on an amd64 FreeBSD or OPNsense host:

```sh
./build-repo.sh /path/to/os-unboundcustom.pkg
```

The script validates every package, groups it below `repo/${ABI}`, and runs
`pkg repo`. Commit and push the resulting repository metadata and packages.
The included GitHub Actions workflow dereferences repository symlinks and
deploys the complete static repository to GitHub Pages.

This is an independent community repository and is not supported by the
OPNsense project. Test packages before using them in production.
