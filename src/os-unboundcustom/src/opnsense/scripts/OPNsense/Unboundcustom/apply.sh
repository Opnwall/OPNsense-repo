#!/bin/sh

set -u

fragment='/usr/local/etc/unbound.opnsense.d/custom-options.conf'
runtime_fragment='/var/unbound/etc/custom-options.conf'
backup="${fragment}.unboundcustom-backup"
runtime_backup="${runtime_fragment}.unboundcustom-backup"
lock='/tmp/unboundcustom.apply.lock'

json_result()
{
    status="$1"
    code="$2"
    detail="${3:-}"
    /usr/local/bin/php -r 'echo json_encode(["status" => $argv[1], "code" => $argv[2], "detail" => $argv[3]], JSON_UNESCAPED_SLASHES), PHP_EOL;' "$status" "$code" "$detail"
}

if ! mkdir "$lock" 2>/dev/null; then
    json_result failed busy
    exit 0
fi
trap 'rmdir "$lock" 2>/dev/null || true' EXIT HUP INT TERM

had_fragment=0
if [ -f "$fragment" ]; then
    had_fragment=1
    cp -p "$fragment" "$backup"
else
    rm -f "$backup"
fi

had_runtime_fragment=0
if [ -f "$runtime_fragment" ]; then
    had_runtime_fragment=1
    cp -p "$runtime_fragment" "$runtime_backup"
else
    rm -f "$runtime_backup"
fi

if ! /usr/local/sbin/configctl template reload 'OPNsense/Unboundcustom' >/tmp/unboundcustom-template.log 2>&1; then
    result_code='template_failed'
    result_detail="$(cat /tmp/unboundcustom-template.log)"
elif ! /usr/bin/install -o unbound -g unbound -m 0640 "$fragment" "$runtime_fragment" 2>/tmp/unboundcustom-template.log; then
    result_code='stage_failed'
    result_detail="$(cat /tmp/unboundcustom-template.log)"
elif ! check_output=$(cd /var/unbound && /usr/local/sbin/unbound-checkconf unbound.conf 2>&1); then
    result_code='validation_failed'
    result_detail="$check_output"
else
    rm -f "$backup" "$runtime_backup" /tmp/unboundcustom-template.log
    if restart_output=$(/usr/local/sbin/configctl unbound restart 2>&1); then
        json_result ok success
        exit 0
    fi
    result_code='restart_failed'
    result_detail="$restart_output"
fi

if [ "$had_fragment" -eq 1 ] && [ -f "$backup" ]; then
    mv -f "$backup" "$fragment"
else
    rm -f "$fragment" "$backup"
fi
if [ "$had_runtime_fragment" -eq 1 ] && [ -f "$runtime_backup" ]; then
    mv -f "$runtime_backup" "$runtime_fragment"
else
    rm -f "$runtime_fragment" "$runtime_backup"
fi
rm -f /tmp/unboundcustom-template.log
json_result failed "$result_code" "$result_detail"
exit 0
