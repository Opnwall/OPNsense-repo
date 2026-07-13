# Opnwall OPNsense Community Repository

**Community Plugins for OPNsense**

[![OPNsense](https://img.shields.io/badge/OPNsense-26.x-orange)]()
[![Platform](https://img.shields.io/badge/Platform-amd64-blue)]()
[![License](https://img.shields.io/badge/License-Multiple-green)]()
[![GitHub
Pages](https://img.shields.io/badge/Hosted-GitHub%20Pages-brightgreen)]()

*[简体中文](#简体中文)*

------------------------------------------------------------------------
This is a community software repository for **OPNsense amd64**; all plugins provided here were written by the author.

It provides networking plugins that extend OPNsense with proxy
integrations, DNS enhancements, Dynamic DNS, localization, diagnostics
and system utilities.

## Installation

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

## Remove Repository

``` sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

Installed plugins remain available.

## Plugins

| Package | Version | Description |
| --- | --- | --- |
| `os-ddclient-opnwall` | 1.0.2 | Extended DDClient replacement with Aliyun, Tencent Cloud and IPv6 interface support |
| `os-ddns-go` | 1.0.2 | DDNS-Go dynamic DNS integration |
| `os-lang` | 1.0.2 | Chinese localization updater |
| `os-lucky` | 1.0.2 | Lucky network toolbox integration |
| `os-mihomo` | 1.0.2 | Mihomo proxy integration |
| `os-pftop` | 1.0.2 | pfTop diagnostics page |
| `os-sing-box` | 1.0.2 | sing-box proxy integration |
| `os-staticarp` | 1.0.2 | Static ARP binding integration |
| `os-ttyd` | 1.0.2 | ttyd terminal integration |
| `os-unboundcustom` | 1.0.2 | Safe custom options for Unbound DNS |

## License
These plugins and packages are free as in beer! There is no source code hosted but you may use them for free and forever. Selling these plugins is prohibited! 

## Disclaimer
This repository is **NOT** affiliated with or supported by the OPNsense Project.


# 简体中文

这是一个面向 **OPNsense amd64**的社区软件仓库，所提供的插件均为作者本人编写。

主要包含：

-   🌐 代理插件（Mihomo、sing-box）
-   🛡 DNS 增强
-   ☁️ DDNS
-   🌏 中文语言包
-   🔧 网络工具
-   📊 系统诊断插件

## 安装方法

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

## 删除仓库

``` sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

不会卸载已经安装的插件。

## 插件列表

| 插件 | 版本 | 描述 |
| --- | --- | --- |
| `os-ddclient-opnwall` | 1.0.2 | 增强版 DDClient 替代方案，支持阿里云、腾讯云及 IPv6 接口 |
| `os-ddns-go` | 1.0.2 | DDNS-Go 动态 DNS |
| `os-lang` | 1.0.2 | 中文汉化工具 |
| `os-lucky` | 1.0.2 | Lucky 网络工具箱 |
| `os-mihomo` | 1.0.2 | Mihomo 代理工具 |
| `os-pftop` | 1.0.2 | pfTop 诊断工具 |
| `os-sing-box` | 1.0.2 | sing-box 代理工具 |
| `os-staticarp` | 1.0.2 | ARP 静态绑定工具 |
| `os-ttyd` | 1.0.2 | ttyd 终端程序 |
| `os-unboundcustom` | 1.0.2 | Unbound DNS 自定义选项 |

## 许可声明
这些插件和软件包是免费的！有些虽然没有托管源码，但你可以免费并永久使用，禁止出售这些插件！

##  免责声明
本仓库为社区项目，与 OPNsense 官方无任何关联，也不提供官方支持。
