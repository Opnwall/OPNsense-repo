# Unbound Custom Options for OPNsense

An updated replacement for `os-unboundcustom-maxit`, tested with OPNsense 26.1.

## Safety behaviour

The plugin saves settings, generates its dedicated include fragment, stages the
runtime copy below `/var/unbound/etc`, validates the complete
`/var/unbound/unbound.conf`, and only then restarts Unbound. On a validation or
template error it restores both copies of the previous fragment, so a bad edit
does not interrupt the running resolver.

Custom directives still require knowledge of `unbound.conf`. They are inserted
verbatim and can override or conflict with settings managed by OPNsense.

All user-visible strings follow the standard OPNsense gettext format. The
plugin does not modify `OPNsense.mo` and contains no built-in language
selection. Add the entries listed in `translations/OPNsense-unboundcustom.pot`
to the desired system language catalog; untranslated strings remain English.

## Source layout

Files under `src/` mirror their locations below `/usr/local` on OPNsense. The
top-level Makefile follows the standard OPNsense plugins build framework.

## Standalone build

Run the build on an amd64 FreeBSD or OPNsense system with `pkg` available:

```sh
chmod +x build.sh
./build.sh
```

The resulting package is written to `dist/os-unboundcustom.pkg`. The default
package uses the portable `FreeBSD:*:amd64` ABI. Use `ABI=native ./build.sh` to
bind it to the build host's FreeBSD major version, or override other metadata,
for example `VERSION=1.2.9 OUTPUT_NAME=os-unboundcustom-1.2.9.pkg ./build.sh`.

## Apply without the UI

```sh
configctl unboundcustom apply
```
