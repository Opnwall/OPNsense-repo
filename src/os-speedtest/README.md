# Speedtest for OPNsense

`os-speedtest` adds **Diagnostics > Speedtest** to OPNsense. It supports
outbound-interface selection, manual server refresh and selection, configurable
parallel connections, multilingual output, and persistent test results.

Build on FreeBSD or OPNsense:

```sh
./build.sh
pkg add -f dist/os-speedtest.pkg
```

The package bundles the MIT-licensed `speedtest-go` 1.7.10 engine.
