# Proxy plugin update — 2026-10-01

Both plugins are updated to version 1.0.3:

- sing-box: [bsd-box 1.13.14-vincent](https://github.com/Vincent-Loeng/bsd-box/releases/tag/v1.13.14-vincent).
- Mihomo: [clash-meta 1.19.31-vincent](https://github.com/Vincent-Loeng/clash-meta/releases/tag/v1.19.31-vincent).

Build scripts pin the release URL and verify the archive SHA256. They also support downloading a missing local asset and correctly resolve `ABI=native`. Source URLs and hashes are recorded in each plugin's `packaging/freebsd/binary-source.json`.

## Validation

Tested on OPNsense 26.7.5 / FreeBSD 15.1 amd64:

- Package installation, core version and default configuration validation passed.
- Default configurations created `tun_singbox` and `tun_mihomo` with MTU 1500.
- Temporary direct configurations passed SOCKS HTTPS requests (HTTP 200), API version requests and DNS queries (NOERROR).
- Mihomo DNS forwarding through Unbound passed. The upstream test network returned Fake-IP addresses.
- Service restart/stop, TUN and route cleanup, and mutual exclusion passed.
- PHP syntax validation passed for plugin pages and helper scripts.
- Installed binary hashes matched the decompressed release archives.
- Incorrect archive hash rejection and the sing-box missing-asset download branch were tested.

FreeBSD 14 and 15 packages were built and their ABI and manifests checked. FreeBSD 14 runtime testing was not performed. Real proxy nodes, subscription downloads, LAN client transparent forwarding and browser WebUI interactions were not tested; bundled nodes are examples.

Temporary configurations were restored after testing, and both services were left stopped. The original routing table and Unbound model were restored.

The official Mihomo v1.19.32 binary was additionally probed and failed to create TUN on this host with `create NetworkUpdateMonitor: invalid argument`. Published binaries use the Vincent-Loeng repositories requested for this update.
