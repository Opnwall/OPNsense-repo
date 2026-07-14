#!/bin/sh
# Sing-Box 在线订阅转换脚本

#################### 初始化 ####################
Server_Dir=$(cd "$(dirname "$0")" && pwd)
ENV_SING_BOX_URL="${SING_BOX_URL:-}"
ENV_CLASH_URL="${CLASH_URL:-}"
[ -f "$Server_Dir/env" ] && . "$Server_Dir/env"
SING_BOX_URL="${ENV_SING_BOX_URL:-${SING_BOX_URL:-${ENV_CLASH_URL:-${CLASH_URL:-}}}}"

API_BASE="https://subconverters.com/sub"
CURL_TLS_ARGS=""
[ "${INSECURE_TLS:-0}" = "1" ] && CURL_TLS_ARGS="-k"
CURL_DNS_ARGS=""
if curl --dns-servers 127.0.0.1 --version >/dev/null 2>&1; then
    CURL_DNS_ARGS="--dns-servers 127.0.0.1"
fi

Conf_Dir=$(cd "$Server_Dir/.." && pwd)
Temp_Dir="$Server_Dir/temp"
Legacy_Conf_Dir="$Server_Dir/conf"
FORMAL_CONFIG="${SING_BOX_FORMAL_CONFIG:-/usr/local/etc/sing-box/config.json}"
SING_BOX_BIN="${SING_BOX_BIN:-/usr/local/bin/sing-box}"
SING_BOX_SERVICE_CMD="${SING_BOX_SERVICE_CMD:-service sing-box restart}"
LOCK_DIR="${SING_BOX_LOCK_DIR:-/var/run/sing-box-sub.sh.lock}"
LOCK_HELD=0
BACKUP_CONFIG=""
FORMAL_TMP=""
mkdir -p "$Temp_Dir"

TMP_SINGBOX=$(mktemp "$Temp_Dir/sub.XXXXXX") || {
    echo "错误：创建临时文件失败：TMP_SINGBOX"
    exit 1
}
TMP_TEMPLATE=$(mktemp "$Temp_Dir/template.XXXXXX") || {
    echo "错误：创建临时文件失败：TMP_TEMPLATE"
    exit 1
}
TMP_OUTPUT=$(mktemp "$Temp_Dir/config.XXXXXX") || {
    echo "错误：创建临时文件失败：TMP_OUTPUT"
    exit 1
}
OUTPUT_FILE="$Conf_Dir/config.json"
cleanup_tmp_files() {
    rm -f "$TMP_SINGBOX" "$TMP_TEMPLATE" "$TMP_OUTPUT"
    [ -n "$FORMAL_TMP" ] && rm -f "$FORMAL_TMP"
    [ "$LOCK_HELD" = "1" ] && rm -f "$LOCK_DIR/pid" 2>/dev/null || true
    [ "$LOCK_HELD" = "1" ] && rmdir "$LOCK_DIR" 2>/dev/null || true
}
trap cleanup_tmp_files EXIT INT TERM

if mkdir "$LOCK_DIR" 2>/dev/null; then
    LOCK_HELD=1
    printf '%s\n' "$$" > "$LOCK_DIR/pid" 2>/dev/null || true
else
    echo "错误：订阅任务已在运行，请勿重复执行。"
    [ -f "$LOCK_DIR/pid" ] && echo "当前锁持有进程 PID: $(cat "$LOCK_DIR/pid" 2>/dev/null)"
    exit 9
fi

# 清理上次失败残留文件
rm -f "$Temp_Dir/sub.json" "$Temp_Dir/template.json" "$Legacy_Conf_Dir/config.json"

for cmd in curl jq perl; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "错误：缺少依赖命令 $cmd，请先安装。"
        exit 1
    fi
done

# 订阅地址校验
[ -z "$SING_BOX_URL" ] && {
    echo "错误：未设置 SING_BOX_URL 环境变量"
    exit 1
}

URL_SCHEME_CHECK=$(printf "%s" "$SING_BOX_URL" | tr '[:upper:]' '[:lower:]')
case "$URL_SCHEME_CHECK" in
    http://*|https://*) ;;
    *)
        echo "错误：SING_BOX_URL 必须以 http:// 或 https:// 开头"
        exit 1
        ;;
esac

ENCODED_URL=$(printf "%s" "$SING_BOX_URL" | jq -s -R -r @uri)

# 构造 Sing-Box 转换地址
API_URL="${API_BASE}?target=singbox&url=${ENCODED_URL}&insert=false&emoji=true&list=false&expand=true"

echo ""
echo "下载转换配置..."
echo ""

if curl -fL $CURL_TLS_ARGS -sS $CURL_DNS_ARGS --retry 3 -m 15 -o "$TMP_SINGBOX" "$API_URL"; then
    echo "下载成功：$TMP_SINGBOX"
    echo ""
elif curl -fL $CURL_TLS_ARGS -sS --retry 2 -m 15 --socks5-hostname 127.0.0.1:7892 -o "$TMP_SINGBOX" "$API_URL"; then
    echo "通过 SOCKS5 代理下载成功：$TMP_SINGBOX"
    echo ""
else
    echo "下载失败！"
    echo ""
    exit 1
fi

if [ ! -s "$TMP_SINGBOX" ]; then
    echo "下载结果为空，订阅内容无效。"
    exit 1
fi

if head -n 20 "$TMP_SINGBOX" | grep -Eiq '^(<!doctype html|<html|<head|<body)'; then
    echo "检测到返回内容为 HTML 页面，订阅可能失效或被拦截。"
    echo "前 20 行内容如下："
    head -n 20 "$TMP_SINGBOX"
    exit 1
fi

# 验证 JSON 合法性
if ! jq empty "$TMP_SINGBOX" >/dev/null 2>&1; then
    echo "错误：下载的配置不是有效 JSON："
    cat "$TMP_SINGBOX"
    exit 1
fi

# 写入到目标目录
echo "写入配置..."
echo ""

# 模板路径
TEMPLATE_FILE="${SING_BOX_TEMPLATE_FILE:-$Server_Dir/template.json}"

# 验证模板存在
if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "模板文件不存在：$TEMPLATE_FILE"
    exit 1
fi

# 预处理模板：去掉 BOM 与常见注释，避免 jq 解析失败
perl -0777 -pe 's/^\x{EF}\x{BB}\x{BF}//; s#(?s)/\*.*?\*/##g; s#(^|\s)//.*\$##mg; s#(^|\s)\#.*\$##mg' \
    "$TEMPLATE_FILE" > "$TMP_TEMPLATE"

# 验证模板 JSON 合法性
if ! jq empty "$TMP_TEMPLATE" >/dev/null 2>&1; then
    echo "错误：模板文件不是有效 JSON：$TEMPLATE_FILE"
    echo "已预处理后的临时模板：$TMP_TEMPLATE"
    exit 1
fi

if ! jq -s '
  def normalize_proxy_refs:
    walk(
      if type == "object" then
        (if .outbound? == "select" then .outbound = "proxy" else . end)
        | (if .detour? == "select" then .detour = "proxy" else . end)
        | (if .download_detour? == "select" then .download_detour = "proxy" else . end)
      else
        .
      end
    );

  def fix_known_subscription_typos:
    walk(
      if type == "object" and .tls.server_name? == "www.speedtet.net" then
        .tls.server_name = "www.speedtest.net"
      else
        .
      end
    );

  def is_node:
    (.type != "direct" and .type != "block" and .type != "dns" and .type != "blackhole" and .type != "selector" and .type != "urltest");

  . as $docs |
  (($docs[1].outbounds // []) | map(select(is_node))) as $nodes |
  ($docs[0] * {
    outbounds: (
      [
        {
          tag: "proxy",
          type: "selector",
          outbounds: (["auto"] + ($nodes | map(.tag)))
        },
        {
          tag: "auto",
          type: "urltest",
          url: "https://www.gstatic.com/generate_204",
          interval: "3m",
          tolerance: 150,
          interrupt_exist_connections: true,
          outbounds: ($nodes | map(.tag))
        },
        {
          tag: "direct",
          type: "direct"
        }
      ] + $nodes
    )
  })
  | normalize_proxy_refs
  | fix_known_subscription_typos
' "$TMP_TEMPLATE" "$TMP_SINGBOX" > "$TMP_OUTPUT"; then
    echo "错误：合并模板与订阅配置失败。"
    echo "模板临时文件：$TMP_TEMPLATE"
    echo "订阅临时文件：$TMP_SINGBOX"
    exit 1
fi

# 验证生成后的配置
if ! jq empty "$TMP_OUTPUT" >/dev/null 2>&1; then
    echo "错误：生成后的配置不是有效 JSON：$TMP_OUTPUT"
    exit 1
fi

if ! "$SING_BOX_BIN" check -c "$TMP_OUTPUT" >/dev/null 2>&1; then
    echo "错误：生成后的 sing-box 配置校验失败，未覆盖正式配置。"
    "$SING_BOX_BIN" check -c "$TMP_OUTPUT" 2>&1 || true
    exit 1
fi

# 复制配置文件到主目录
echo "替换配置..."
if [ -f "$FORMAL_CONFIG" ]; then
    BACKUP_CONFIG="${FORMAL_CONFIG}.bak.sub_$(date +%Y%m%d_%H%M%S)"
    cp "$FORMAL_CONFIG" "$BACKUP_CONFIG" || {
        echo "错误：备份正式配置失败：$FORMAL_CONFIG"
        exit 1
    }
    echo "正式配置已备份：$BACKUP_CONFIG"
fi

CONFIG_SOURCE="$TMP_OUTPUT"
if [ "$OUTPUT_FILE" != "$FORMAL_CONFIG" ]; then
    if ! cp "$TMP_OUTPUT" "$OUTPUT_FILE"; then
        echo "错误：写入订阅配置失败：$OUTPUT_FILE"
        exit 1
    fi
    CONFIG_SOURCE="$OUTPUT_FILE"
fi

FORMAL_TMP=$(mktemp "$Temp_Dir/config.formal.XXXXXX") || {
    echo "错误：创建正式配置临时文件失败。"
    exit 1
}
cp "$CONFIG_SOURCE" "$FORMAL_TMP" || {
    echo "错误：写入正式配置临时文件失败：$FORMAL_TMP"
    exit 1
}
chmod 600 "$FORMAL_TMP" 2>/dev/null || true
mv "$FORMAL_TMP" "$FORMAL_CONFIG" || {
    echo "错误：替换主配置失败。"
    exit 1
}
FORMAL_TMP=""
echo ""

# 静默重启 sing-box
echo "重启sing-box..."
if ! sh -c "$SING_BOX_SERVICE_CMD" >/dev/null 2>&1; then
    echo "错误：sing-box 重启失败，开始回滚正式配置。"
    if [ -n "$BACKUP_CONFIG" ] && [ -s "$BACKUP_CONFIG" ]; then
        cp "$BACKUP_CONFIG" "$FORMAL_CONFIG" || {
            echo "错误：回滚失败，请手动恢复：$BACKUP_CONFIG"
            exit 1
        }
        chmod 600 "$FORMAL_CONFIG" 2>/dev/null || true
        if sh -c "$SING_BOX_SERVICE_CMD" >/dev/null 2>&1; then
            echo "已回滚到旧配置并恢复 sing-box 服务。"
        else
            echo "已回滚旧配置，但 sing-box 服务仍启动失败，请手动检查 /var/log/sing-box.log。"
        fi
    else
        echo "未找到可用备份，无法自动回滚。"
    fi
    exit 1
fi
echo ""

# 删除临时配置文件
echo "删除临时文件..."
rm -f "$Temp_Dir/sub.json" "$Temp_Dir/template.json" "$Legacy_Conf_Dir/config.json" "$TMP_SINGBOX" "$TMP_TEMPLATE"
echo ""

# 优先获取防火墙的 LAN，其次 OPT1，最后回退到私网地址
LAN_IP=$(
  /usr/local/bin/php -r '
    require_once("/etc/inc/config.inc");
    require_once("/etc/inc/interfaces.inc");

    $candidates = array("lan", "opt1");

    foreach ($candidates as $name) {
        $if = get_real_interface($name);
        if (!empty($if)) {
            $ip = get_interface_ip($if);
            if (!empty($ip)) {
                echo $ip;
                exit;
            }
        }
    }
  ' 2>/dev/null
)

# 如果接口函数没有取到，再回退到本机私网地址
[ -n "$LAN_IP" ] || LAN_IP=$(
  ifconfig 2>/dev/null | awk '
    /^[[:alnum:]_.:-]+:/ {
      iface=$1
      sub(/:.*/, "", iface)
      skip=(iface ~ /^(lo|tun|pflog|pfsync|enc|gif|gre|stf|ovpn|wg)/)
      next
    }
    /inet / {
      ip=$2
      if (!skip &&
          ip != "127.0.0.1" &&
          ip !~ /^169\.254\./ &&
          ip ~ /^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/) {
        print ip
        exit
      }
    }
  '
)
# 如果还是取不到，就给一个兜底提示
if [ -n "$LAN_IP" ]; then
  echo "仪表盘访问地址: http://${LAN_IP}:9091/ui"
else
  echo "仪表盘访问地址: 未能自动获取 LAN/OPT1 地址"
fi

DASHBOARD_SECRET=$(jq -r '.experimental.clash_api.secret // ""' "$FORMAL_CONFIG" 2>/dev/null)
if [ -n "$DASHBOARD_SECRET" ] && [ "$DASHBOARD_SECRET" != "null" ]; then
  echo "仪表盘访问密钥: ${DASHBOARD_SECRET}"
else
  echo "仪表盘访问密钥: 未设置"
fi
echo ""
