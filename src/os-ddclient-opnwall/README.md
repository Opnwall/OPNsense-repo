# os-ddclient-opnwall

完整替代 OPNsense 官方 `os-ddclient` 的社区插件，在保留官方 WebUI、服务、
日志和仪表盘集成的基础上增加：

- 阿里云 DNS（`aliyun`）
- 腾讯云 DNSPod（`tencentcloud`），支持查询、创建及修改记录
- `if6` IPv6 接口地址选择，适用于双栈及 PPPoE 场景

## 安装

本包与官方 `os-ddclient` 安装相同文件，安装前必须先删除官方插件：

```sh
pkg delete os-ddclient
pkg install os-ddclient-opnwall
```

安装 Opnwall 社区仓库后，也可在 **系统 > 固件 > 插件** 中安装。

## 编译

将源码复制到 OPNsense/FreeBSD amd64 主机后执行：

```sh
./build.sh
```

输出文件为 `dist/os-ddclient-opnwall.pkg`。可通过 `VERSION=1.1 ./build.sh`
覆盖默认版本。

## 配置

进入 **服务 > 动态 DNS > 设置**，后端选择 `native`。

- 阿里云：用户名填写 AccessKey ID，密码填写 AccessKey Secret。
- 腾讯云：用户名填写 SecretId，密码填写 SecretKey；建议明确填写 Zone。
- 腾讯云免费解析的 TTL 下限通常为 600，本插件会自动将更低值提升为 600。
- `Hostname(s)` 可填写完整域名；腾讯云记录不存在时会自动创建。

## 卸载与切回官方插件

```sh
pkg delete os-ddclient-opnwall
pkg install os-ddclient
```

卸载软件包不会删除 OPNsense 中已有的动态 DNS 配置。
