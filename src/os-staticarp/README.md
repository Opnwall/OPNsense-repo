# Static Binding for OPNsense

OPNsense 静态 ARP/IP 绑定插件，参考 `Static Binding for pfSense` 的核心逻辑实现。

## 功能

- 在 `Services > Static Binding` 提供 WebGUI 管理页面。
- 维护 `IP MAC` 静态绑定列表，并可复制当前系统 ARP 表。
- 支持按接口设置 ARP 应答模式：正常应答、静态应答、取消应答。
- 通过 `configctl staticarp apply/reset/status` 应用和重置配置。
- 安装后注册 OPNsense Menu、ACL 和 configd action，不修改核心系统文件。

## 构建

在 FreeBSD/OPNsense 主机上执行：

```sh
make package
```

生成的包位于：

```text
dist/os-staticarp.pkg
```

如需构建通用 amd64 ABI 包：

```sh
TARGET_ABI=FreeBSD:*:amd64 make package
```

## 安装

```sh
pkg add -f dist/os-staticarp.pkg
```

安装后刷新浏览器，打开 `Services > Static Binding`。

## 注意

启用静态绑定或将接口切换为静态应答前，请先确认当前管理主机已经加入绑定列表，否则可能失去 WebGUI 访问。
