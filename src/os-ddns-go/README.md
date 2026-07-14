# DDNS-Go for OPNsense

这是一个用于 OPNsense 的 DDNS-Go 集成包，提供 WebGUI 菜单、服务管理、开机自启和标准 `pkg` 打包支持。

## 项目结构

- `src/` 按 OPNsense 实际安装路径组织文件。
- `src/usr/local/bin/ddns-go` 是本地内置的 FreeBSD amd64 二进制文件。
- `src/usr/local/etc/rc.d/os-ddns-go` 使用 `daemon(8)` 管理 DDNS-Go 服务。
- `src/usr/local/opnsense/service/conf/actions.d/actions_ddnsgo.conf` 注册 `configctl ddnsgo` 动作。
- `src/usr/local/opnsense/mvc/app/models/OPNsense/Ddnsgo/` 注册菜单和 ACL。
- `src/usr/local/www/services_ddnsgo.php` 提供 OPNsense WebGUI 管理页面。
- `packaging/freebsd/` 保存 FreeBSD/pkg 打包元数据和安装、卸载 hook。

## 编译

请在 FreeBSD 或 OPNsense 主机上编译：

```sh
make package
```

默认使用当前系统 ABI。也可以指定通用 amd64 ABI：

```sh
ABI=universal make package
```

编译时会优先使用本地二进制：

- 如果 `src/usr/local/bin/ddns-go` 存在，则直接打包该本地文件。
- 如果本地文件不存在，`build.sh` 会从 GitHub 获取 DDNS-Go 最新版本，并下载 FreeBSD x86_64 版本后打包。

## 安装

```sh
./install.sh
```

安装完成后，刷新 OPNsense WebGUI，进入 `服务 > DDNS-Go`。

默认配置：

- Web 端口：`9876`
- 配置文件：`/usr/local/etc/ddns-go/config.yaml`
- 首次安装默认账号：`admin`
- 首次安装默认密码：`admin`

如果目标系统已经存在 `/usr/local/etc/ddns-go/config.yaml`，安装脚本不会覆盖已有配置。

## 卸载

```sh
./uninstall.sh
```

卸载时会停止服务，并清理以下文件：

- `/etc/rc.conf.d/ddnsgo`
- `/var/run/ddnsgo.pid`
- `/var/log/ddnsgo.log`
- `/usr/local/etc/ddns-go`

## WebGUI

WebGUI 页面提供：

- 服务状态显示
- 启动、停止、重启控制
- 访问地址显示
- 配置文件在线编辑
- 运行日志查看
