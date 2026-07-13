# 🧱 Opnwall OPNsense Community Repository

**Community Plugins for OPNsense**

[![OPNsense](https://img.shields.io/badge/OPNsense-26.x-orange)]()
[![Platform](https://img.shields.io/badge/Platform-amd64-blue)]()
[![License](https://img.shields.io/badge/License-Multiple-green)]()
[![GitHub
Pages](https://img.shields.io/badge/Hosted-GitHub%20Pages-brightgreen)]()

*English \| [简体中文](#简体中文)*

------------------------------------------------------------------------

# English

**Opnwall** is a community-maintained package repository for **OPNsense
amd64**.

It provides networking plugins that extend OPNsense with proxy
integrations, DNS enhancements, Dynamic DNS, localization, diagnostics
and system utilities.

> **Disclaimer**
>
> This repository is **NOT** affiliated with or supported by the
> OPNsense Project.

## ✨ Features

-   Native OPNsense plugin repository
-   GitHub Pages hosted
-   Automatic repository generation
-   GitHub Actions deployment
-   Easy installation and updates

## 📦 Installation

``` sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/OPNsense-repo/opnwall.conf

pkg update -f
```

Open:

    System
    └── Firmware
        └── Plugins

Install any package beginning with `os-`.

## 🔄 Update

``` sh
pkg update -f
```

## ❌ Remove Repository

``` sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

Installed plugins remain available.

## 📋 Plugins

| Package | Version | Description |
| --- | --- | --- |
| `os-ddclient-opnwall` | 1.0.1 | Extended DDClient replacement with Aliyun, Tencent Cloud and IPv6 interface support |
| `os-ddns-go` | 1.0.1 | DDNS-Go dynamic DNS integration |
| `os-lang` | 1.0.1 | Chinese localization updater |
| `os-lucky` | 1.0.1 | Lucky network toolbox integration |
| `os-mihomo` | 1.0.1 | Mihomo proxy integration |
| `os-pftop` | 1.0.1 | pfTop diagnostics page |
| `os-sing-box` | 1.0.1 | sing-box proxy integration |
| `os-staticarp` | 1.0.1 | Static ARP binding integration |
| `os-ttyd` | 1.0.1 | ttyd terminal integration |
| `os-unboundcustom` | 1.0.1 | Safe custom options for Unbound DNS |

# 简体中文

**Opnwall** 是一个面向 **OPNsense amd64**
的社区软件仓库，为官方仓库提供更多实用插件。

主要包含：

-   🌐 代理插件（Mihomo、sing-box）
-   🛡 DNS 增强
-   ☁️ DDNS
-   🌏 中文语言包
-   🔧 网络工具
-   📊 系统诊断插件

> **免责声明**
>
> 本仓库为社区项目，与 OPNsense 官方无任何关联，也不提供官方支持。

## ✨ 特性

-   原生 OPNsense 插件
-   GitHub Pages 软件仓库
-   自动生成 pkg 仓库
-   GitHub Actions 自动发布
-   支持在线升级

## 📦 安装

``` sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/OPNsense-repo/opnwall.conf

pkg update -f
```

然后进入：

    系统
    └── 固件
        └── 插件

安装所有 `os-` 开头的软件包。

## 🔄 更新

``` sh
pkg update -f
```

## ❌ 删除仓库

``` sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

不会卸载已经安装的插件。

## 📦 插件列表

| 插件 | 版本 | 描述 |
| --- | --- | --- |
| `os-ddclient-opnwall` | 1.0.1 | 增强版 DDClient 替代方案，支持阿里云、腾讯云及 IPv6 接口 |
| `os-ddns-go` | 1.0.1 | DDNS-Go 动态 DNS 集成 |
| `os-lang` | 1.0.1 | 中文汉化工具 |
| `os-lucky` | 1.0.1 | Lucky 网络工具箱集成 |
| `os-mihomo` | 1.0.1 | Mihomo 代理工具 |
| `os-pftop` | 1.0.1 | pfTop 诊断工具 |
| `os-sing-box` | 1.0.1 | sing-box 代理工具 |
| `os-staticarp` | 1.0.1 | ARP静态绑定工具 |
| `os-ttyd` | 1.0.1 | ttyd 终端程序 |
| `os-unboundcustom` | 1.0.1 | Unbound DNS 自定义选项 |

## 🤝 Contributing / 贡献

欢迎提交 Issue 与 Pull Request。

