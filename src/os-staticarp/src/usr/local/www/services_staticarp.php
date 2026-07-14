<?php
/*
 * Static Binding for OPNsense.
 */

$allowautocomplete = true;
require_once("guiconfig.inc");

const STATICARP_CONFIG_DIR = "/usr/local/etc/staticarp";
const STATICARP_SETTINGS_FILE = STATICARP_CONFIG_DIR . "/settings.conf";
const STATICARP_ENTRIES_FILE = STATICARP_CONFIG_DIR . "/entries.conf";
const STATICARP_INTERFACES_FILE = STATICARP_CONFIG_DIR . "/interfaces.conf";

function staticarp_lang()
{
    if (is_readable('/conf/config.xml')) {
        $config_xml = @file_get_contents('/conf/config.xml');
        if (is_string($config_xml) && preg_match('/<language>([^<]+)<\/language>/', $config_xml, $matches)) {
            $language = strtolower(str_replace('-', '_', trim($matches[1])));
            if (in_array($language, ['zh_cn', 'zh_hans_cn'], true)) {
                return 'zh_Hans';
            }
            if (in_array($language, ['zh_tw', 'zh_hant_tw', 'zh_hk', 'zh_hant_hk'], true)) {
                return 'zh_Hant';
            }
        }
    }

    return 'en';
}

function staticarp_t($text)
{
    static $map = [
        'zh_Hans' => [
            'Services' => '服务',
            'Static Binding' => '静态绑定',
            'Interface Settings' => '接口设置',
            'Interface' => '接口',
            'Device' => '设备',
            'IP Address' => 'IP 地址',
            'MAC Address' => 'MAC 地址',
            'Status' => '状态',
            'Reply Mode' => '应答模式',
            'Script' => '脚本',
            'Normal Reply' => '正常应答',
            'Static Reply' => '静态应答',
            'No Reply' => '取消应答',
            'Binding Configuration' => '绑定配置',
            'Enable static ARP binding' => '启用静态 ARP 绑定',
            'When enabled, entries from the binding list are loaded into the system ARP table and each interface reply mode is applied.' => '启用后，将绑定列表加载到系统 ARP 表，并应用接口的 ARP 模式。',
            'Binding List' => '绑定列表',
            'Binding records' => '绑定记录',
            'One entry per line, in IP MAC format.' => '每行一条记录，格式为 IP MAC。',
            'Current ARP Table' => '当前 ARP 表',
            'Entries currently learned by the system. Copy them to the binding list and edit as needed.' => '当前系统学习到的 ARP 条目，可复制到绑定列表后按需删改。',
            'Copy Current ARP Table' => '复制当前 ARP 表',
            'Save' => '保存',
            'Reset' => '重置',
            'Running configuration saved and applied.' => '配置已保存并应用。',
            'Configuration saved, but static binding is disabled.' => '配置已保存，但静态绑定未启用。',
            'Static binding has been applied.' => '静态绑定已应用。',
            'Static binding has been reset.' => '静态绑定已重置。',
            'The binding list contains an invalid IP address: %s' => '列表包含错误的 IP 地址：%s',
            'The binding list contains an invalid MAC address: %s' => '列表包含错误的 MAC 地址：%s',
            'The binding list is empty. Static binding cannot be enabled.' => '绑定列表为空，不能启用静态绑定。',
            'Download Windows ARP helper script' => '下载 Windows ARP 辅助脚本',
            'up' => 'up',
            'down' => 'down',
        ],
        'zh_Hant' => [
            'Services' => '服務',
            'Static Binding' => '靜態綁定',
            'Interface Settings' => '介面設定',
            'Interface' => '介面',
            'Device' => '裝置',
            'IP Address' => 'IP 位址',
            'MAC Address' => 'MAC 位址',
            'Status' => '狀態',
            'Reply Mode' => '回應模式',
            'Script' => '指令碼',
            'Normal Reply' => '正常回應',
            'Static Reply' => '靜態回應',
            'No Reply' => '取消回應',
            'Binding Configuration' => '綁定設定',
            'Enable static ARP binding' => '啟用靜態 ARP 綁定',
            'When enabled, entries from the binding list are loaded into the system ARP table and each interface reply mode is applied.' => '啟用後，將綁定清單載入系統 ARP 表，並套用介面的 ARP 模式。',
            'Binding List' => '綁定清單',
            'Binding records' => '綁定記錄',
            'One entry per line, in IP MAC format.' => '每行一筆記錄，格式為 IP MAC。',
            'Current ARP Table' => '目前 ARP 表',
            'Entries currently learned by the system. Copy them to the binding list and edit as needed.' => '系統目前學習到的 ARP 項目，可複製到綁定清單後依需要修改。',
            'Copy Current ARP Table' => '複製目前 ARP 表',
            'Save' => '儲存',
            'Reset' => '重設',
            'Running configuration saved and applied.' => '執行設定已儲存並套用。',
            'Configuration saved, but static binding is disabled.' => '設定已儲存，但靜態綁定未啟用。',
            'Static binding has been applied.' => '靜態綁定已套用。',
            'Static binding has been reset.' => '靜態綁定已重設。',
            'The binding list contains an invalid IP address: %s' => '清單包含錯誤的 IP 位址：%s',
            'The binding list contains an invalid MAC address: %s' => '清單包含錯誤的 MAC 位址：%s',
            'The binding list is empty. Static binding cannot be enabled.' => '綁定清單為空，無法啟用靜態綁定。',
            'Download Windows ARP helper script' => '下載 Windows ARP 輔助指令碼',
            'up' => 'up',
            'down' => 'down',
        ],
    ];

    $lang = staticarp_lang();
    return $map[$lang][$text] ?? $text;
}

function staticarp_sync_menu_label()
{
    $menu_file = '/usr/local/opnsense/mvc/app/models/OPNsense/Staticarp/Menu/Menu.xml';
    if (!is_readable($menu_file) || !is_writable($menu_file)) {
        return;
    }

    $label = staticarp_t('Static Binding');
    $xml = file_get_contents($menu_file);
    if (!is_string($xml)) {
        return;
    }

    $escaped_label = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $updated = preg_replace('/(<Staticarp\b[^>]*\bVisibleName=")[^"]*(")/', '${1}' . $escaped_label . '$2', $xml, 1);
    if (!is_string($updated) || $updated === $xml) {
        return;
    }

    file_put_contents($menu_file, $updated, LOCK_EX);
    @unlink('/var/lib/php/tmp/opnsense_menu_cache.xml');
}

function staticarp_ensure_config_dir()
{
    if (!is_dir(STATICARP_CONFIG_DIR)) {
        mkdir(STATICARP_CONFIG_DIR, 0755, true);
    }
}

function staticarp_read_enabled()
{
    if (!is_readable(STATICARP_SETTINGS_FILE)) {
        return false;
    }

    $contents = file_get_contents(STATICARP_SETTINGS_FILE);
    return preg_match('/^enabled=YES$/m', (string)$contents) === 1;
}

function staticarp_read_entries()
{
    if (!is_readable(STATICARP_ENTRIES_FILE)) {
        return '';
    }

    return trim((string)file_get_contents(STATICARP_ENTRIES_FILE));
}

function staticarp_read_interface_modes()
{
    $modes = [];
    if (!is_readable(STATICARP_INTERFACES_FILE)) {
        return $modes;
    }

    foreach (file(STATICARP_INTERFACES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*#/', $line)) {
            continue;
        }
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 3) {
            $modes[$parts[0]] = $parts[2];
        }
    }

    return $modes;
}

function staticarp_write_config($enabled, $entries, $interface_rows)
{
    staticarp_ensure_config_dir();
    file_put_contents(STATICARP_SETTINGS_FILE, 'enabled=' . ($enabled ? 'YES' : 'NO') . "\n", LOCK_EX);
    file_put_contents(STATICARP_ENTRIES_FILE, rtrim($entries) . "\n", LOCK_EX);

    $lines = [];
    foreach ($interface_rows as $row) {
        $lines[] = implode(' ', [$row['name'], $row['device'], $row['mode']]);
    }
    file_put_contents(STATICARP_INTERFACES_FILE, implode("\n", $lines) . "\n", LOCK_EX);
}

function staticarp_valid_ip($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

function staticarp_valid_mac($mac)
{
    return preg_match('/^([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}$/', $mac) === 1;
}

function staticarp_local_ipv4_addresses()
{
    $addresses = [];
    exec('/sbin/ifconfig 2>/dev/null', $output);
    foreach ($output as $line) {
        if (preg_match('/^\s+inet\s+([0-9.]+)/', $line, $matches) && staticarp_valid_ip($matches[1])) {
            $addresses[$matches[1]] = true;
        }
    }
    return $addresses;
}

function staticarp_normalize_entries($raw, &$errors)
{
    $rows = [];
    $local_addresses = staticarp_local_ipv4_addresses();
    foreach (preg_split('/[\r\n,]+/', trim((string)$raw)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = preg_split('/\s+/', $line);
        $ip = $parts[0] ?? '';
        $mac = strtolower($parts[1] ?? '');
        if (!staticarp_valid_ip($ip)) {
            $errors[] = sprintf(staticarp_t('The binding list contains an invalid IP address: %s'), $ip);
            continue;
        }
        if (!staticarp_valid_mac($mac)) {
            $errors[] = sprintf(staticarp_t('The binding list contains an invalid MAC address: %s'), $mac);
            continue;
        }
        if (isset($local_addresses[$ip])) {
            continue;
        }
        $rows[ip2long($ip)] = long2ip(ip2long($ip)) . ' ' . $mac;
    }
    ksort($rows, SORT_NUMERIC);
    return implode("\n", array_values($rows));
}

function staticarp_run_action($action)
{
    $allowed = ['apply', 'reset'];
    if (!in_array($action, $allowed, true)) {
        return;
    }
    exec('/usr/local/sbin/configctl staticarp ' . escapeshellarg($action) . ' >/dev/null 2>&1');
}

function staticarp_current_arp_list()
{
    exec('/usr/sbin/arp -an 2>/dev/null', $rawdata);
    $rows = [];
    $local_addresses = staticarp_local_ipv4_addresses();
    foreach ($rawdata as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (!isset($parts[1], $parts[3]) || $parts[3] === '(incomplete)') {
            continue;
        }
        $ip = trim($parts[1], '()');
        $mac = strtolower($parts[3]);
        if (staticarp_valid_ip($ip) && staticarp_valid_mac($mac) && !isset($local_addresses[$ip])) {
            $rows[ip2long($ip)] = long2ip(ip2long($ip)) . ' ' . $mac;
        }
    }
    ksort($rows, SORT_NUMERIC);
    return implode("\n", array_values($rows));
}

function staticarp_get_interfaces()
{
    global $config;

    $iflist = function_exists('get_interface_list') ? get_interface_list() : [];
    $rows = [];
    foreach (($config['interfaces'] ?? []) as $name => $info) {
        if (empty($info['if'])) {
            continue;
        }
        $device = $info['if'];
        if ($device === 'lo0' || str_starts_with($device, 'lo')) {
            continue;
        }
        if (!empty($info['gateway']) && !in_array(strtolower((string)$info['gateway']), ['none', 'dynamic'], true)) {
            continue;
        }
        $device_info = $iflist[$device] ?? [];
        $ipaddr = $info['ipaddr'] ?? '';
        $subnet = $info['subnet'] ?? '';
        $descr = trim((string)($info['descr'] ?? ''));
        if ($descr === '') {
            $descr = strtoupper($name);
        }
        $rows[$name] = [
            'name' => $name,
            'device' => $device,
            'descr' => $descr,
            'ipaddr' => staticarp_valid_ip($ipaddr) && $subnet !== '' ? $ipaddr . '/' . $subnet : $ipaddr,
            'mac' => $device_info['mac'] ?? '',
            'status' => !empty($device_info['up']) ? 'up' : 'down',
        ];
    }

    return $rows;
}

function staticarp_download_script($ifname, $interfaces, $entries)
{
    if (!isset($interfaces[$ifname])) {
        $ifname = array_key_first($interfaces);
    }
    if ($ifname === null) {
        return;
    }

    $interface = $interfaces[$ifname];
    $script = "@echo off\r\n";
    $script .= "@color 0A\r\n";
    $script .= "@echo Firewall client ARP binding script\r\n";
    $script .= "arp -d\r\n";
    if (staticarp_valid_ip(explode('/', $interface['ipaddr'])[0]) && staticarp_valid_mac($interface['mac'])) {
        $script .= 'arp -s ' . explode('/', $interface['ipaddr'])[0] . ' ' . str_replace(':', '-', $interface['mac']) . "\r\n";
    }
    foreach (preg_split('/\r?\n/', trim($entries)) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (isset($parts[0], $parts[1]) && staticarp_valid_ip($parts[0]) && staticarp_valid_mac($parts[1])) {
            $script .= 'arp -s ' . $parts[0] . ' ' . str_replace(':', '-', $parts[1]) . "\r\n";
        }
    }
    $script .= "arp -a\r\npause\r\n";

    session_cache_limiter('public');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($script));
    header('Content-Disposition: attachment; filename="arp_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $interface['device']) . '.cmd"');
    echo $script;
    exit;
}

$interface_modes = staticarp_read_interface_modes();
$interfaces = staticarp_get_interfaces();
$entries = staticarp_read_entries();
$current_arp = staticarp_current_arp_list();
$enabled = staticarp_read_enabled();
$input_errors = [];
$savemsg = '';
$savemsg_class = 'success';
$mode_labels = [
    'normal' => staticarp_t('Normal Reply'),
    'staticarp' => staticarp_t('Static Reply'),
    '-arp' => staticarp_t('No Reply'),
];
$pgtitle = [staticarp_t('Services'), staticarp_t('Static Binding')];
staticarp_sync_menu_label();

if (isset($_GET['download'])) {
    staticarp_download_script((string)$_GET['download'], $interfaces, $entries);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['copy'])) {
        $entries = $current_arp;
        $enabled = isset($_POST['enable']);
    } elseif (isset($_POST['reset'])) {
        staticarp_run_action('reset');
        $savemsg = staticarp_t('Static binding has been reset.');
        $savemsg_class = 'warning';
    } elseif (isset($_POST['save'])) {
        $enabled = isset($_POST['enable']);
        $entries = staticarp_normalize_entries($_POST['entries'] ?? '', $input_errors);

        if ($enabled && trim($entries) === '') {
            $input_errors[] = staticarp_t('The binding list is empty. Static binding cannot be enabled.');
        }

        $interface_rows = [];
        foreach ($interfaces as $name => $interface) {
            $mode = $_POST['mode'][$name] ?? 'normal';
            if (!array_key_exists($mode, $mode_labels)) {
                $mode = 'normal';
            }
            $interface_modes[$name] = $mode;
            $interface_rows[] = [
                'name' => $name,
                'device' => $interface['device'],
                'mode' => $mode,
            ];
        }

        if (empty($input_errors)) {
            staticarp_write_config($enabled, $entries, $interface_rows);
            if ($enabled) {
                staticarp_run_action('apply');
                $savemsg = staticarp_t('Running configuration saved and applied.');
            } else {
                staticarp_run_action('reset');
                $savemsg = staticarp_t('Configuration saved, but static binding is disabled.');
                $savemsg_class = 'warning';
            }
        }
    }
}

include("head.inc");
include("fbegin.inc");
?>

<style>
.staticarp-page {
    max-width: none;
    width: 100%;
}
.staticarp-panel-heading {
    background: #f5f5f5;
    border-bottom: 1px solid #ddd;
    color: #333;
    font-weight: 700;
    padding: 8px 12px;
}
.staticarp-panel {
    border-radius: 0;
    margin-bottom: 12px;
}
.staticarp-panel .panel-body {
    padding: 14px 12px;
}
.staticarp-table th,
.staticarp-table td {
    text-align: left;
    vertical-align: middle !important;
}
.staticarp-table th {
    font-weight: 400;
}
.staticarp-table td {
    font-weight: 400;
}
.staticarp-table td:last-child,
.staticarp-table th:last-child {
    text-align: center;
    width: 72px;
}
.staticarp-table th:first-child,
.staticarp-table td:first-child {
    padding-left: 12px !important;
}
.staticarp-table th:nth-child(6),
.staticarp-table td:nth-child(6) {
    min-width: 280px;
    width: 280px;
}
.staticarp-mode {
    height: 32px;
    font-weight: 400;
    line-height: 1.4;
    min-width: 260px;
    padding-right: 32px;
    padding-bottom: 4px;
    padding-top: 4px;
    width: 260px;
}
.staticarp-textarea {
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
    font-weight: 400;
    min-height: 248px;
    resize: vertical;
}
.staticarp-textarea-readonly {
    background-color: #fff !important;
}
.staticarp-actions .btn {
    margin-right: 5px;
}
.staticarp-form-row {
    align-items: flex-start;
    display: flex;
    gap: 12px;
    margin-bottom: 14px;
}
.staticarp-form-label {
    flex: 0 0 84px;
    font-weight: 400;
    line-height: 30px;
    margin: 0;
    text-align: left;
    white-space: nowrap;
}
.staticarp-form-control {
    flex: 1 1 auto;
    min-width: 0;
}
.staticarp-editor-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: minmax(374px, 605px) minmax(374px, 605px);
}
.staticarp-editor-title {
    display: block;
    font-weight: 400;
    margin-bottom: 5px;
}
.staticarp-actions {
    margin-left: 0;
}
@media (max-width: 767px) {
    .staticarp-form-row {
        display: block;
    }
    .staticarp-form-label {
        display: block;
        line-height: 1.4;
        margin-bottom: 6px;
        text-align: left;
    }
    .staticarp-editor-grid {
        grid-template-columns: 1fr;
    }
    .staticarp-actions {
        margin-left: 0;
    }
}
</style>

<section class="page-content-main">
    <div class="container-fluid staticarp-page">
        <div class="row">
            <section class="col-xs-12">
                <?php if (!empty($input_errors)): ?>
                    <?php print_input_errors($input_errors); ?>
                <?php endif; ?>
                <?php if ($savemsg !== ''): ?>
                    <div class="alert alert-<?=htmlspecialchars($savemsg_class)?>" role="alert"><?=htmlspecialchars($savemsg)?></div>
                <?php endif; ?>

                <form method="post" class="form-horizontal">
                    <div class="panel panel-default staticarp-panel">
                        <div class="staticarp-panel-heading"><?=htmlspecialchars(staticarp_t('Interface Settings'))?></div>
                        <table class="table table-striped table-condensed staticarp-table">
                            <thead>
                                <tr>
                                    <th><?=htmlspecialchars(staticarp_t('Interface'))?></th>
                                    <th><?=htmlspecialchars(staticarp_t('Device'))?></th>
                                    <th><?=htmlspecialchars(staticarp_t('IP Address'))?></th>
                                    <th><?=htmlspecialchars(staticarp_t('MAC Address'))?></th>
                                    <th><?=htmlspecialchars(staticarp_t('Status'))?></th>
                                    <th><?=htmlspecialchars(staticarp_t('Reply Mode'))?></th>
                                    <th><?=htmlspecialchars(staticarp_t('Script'))?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interfaces as $name => $interface): ?>
                                    <?php $selected_mode = $interface_modes[$name] ?? 'normal'; ?>
                                    <tr>
                                        <td><?=htmlspecialchars($interface['descr'])?></td>
                                        <td><?=htmlspecialchars($interface['device'])?></td>
                                        <td><?=htmlspecialchars($interface['ipaddr'])?></td>
                                        <td><?=htmlspecialchars($interface['mac'])?></td>
                                        <td><?=htmlspecialchars(staticarp_t($interface['status']))?></td>
                                        <td>
                                            <select class="form-control input-sm staticarp-mode" name="mode[<?=htmlspecialchars($name)?>]">
                                                <?php foreach ($mode_labels as $mode => $label): ?>
                                                    <option value="<?=htmlspecialchars($mode)?>" <?=$selected_mode === $mode ? 'selected' : ''?>><?=htmlspecialchars($label)?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <a class="fa fa-download" title="<?=htmlspecialchars(staticarp_t('Download Windows ARP helper script'))?>" href="?download=<?=urlencode($name)?>"></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="panel panel-default staticarp-panel">
                        <div class="staticarp-panel-heading"><?=htmlspecialchars(staticarp_t('Binding Configuration'))?></div>
                        <div class="panel-body">
                            <div class="staticarp-form-row">
                                <div class="staticarp-form-control">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="enable" value="yes" <?=$enabled ? 'checked' : ''?>>
                                        <?=htmlspecialchars(staticarp_t('Enable static ARP binding'))?>
                                    </label>
                                    <p class="help-block"><?=htmlspecialchars(staticarp_t('When enabled, entries from the binding list are loaded into the system ARP table and each interface reply mode is applied.'))?></p>
                                </div>
                            </div>
                            <div class="staticarp-form-row">
                                <div class="staticarp-form-control staticarp-editor-grid">
                                    <div>
                                        <label class="staticarp-editor-title"><?=htmlspecialchars(staticarp_t('Binding List'))?></label>
                                        <textarea class="form-control staticarp-textarea" name="entries"><?=htmlspecialchars($entries)?></textarea>
                                        <p class="help-block"><?=htmlspecialchars(staticarp_t('One entry per line, in IP MAC format.'))?></p>
                                        <button class="btn btn-success btn-sm" type="submit" name="copy"><i class="fa fa-plus icon-embed-btn"></i><?=htmlspecialchars(staticarp_t('Copy Current ARP Table'))?></button>
                                    </div>
                                    <div>
                                        <label class="staticarp-editor-title"><?=htmlspecialchars(staticarp_t('Current ARP Table'))?></label>
                                        <textarea class="form-control staticarp-textarea staticarp-textarea-readonly" readonly><?=htmlspecialchars($current_arp)?></textarea>
                                        <p class="help-block"><?=htmlspecialchars(staticarp_t('Entries currently learned by the system. Copy them to the binding list and edit as needed.'))?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="staticarp-actions">
                                <button class="btn btn-primary" type="submit" name="save"><i class="fa fa-save icon-embed-btn"></i><?=htmlspecialchars(staticarp_t('Save'))?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>
</section>

<?php include("foot.inc"); ?>
