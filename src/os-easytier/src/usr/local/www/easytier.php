<?php
require_once('guiconfig.inc');

if (!isset($_GET['view']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: /easytier.php?view=status');
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const EASYTIER_CONFIG = '/usr/local/etc/easytier/config.toml';
const EASYTIER_LOG = '/var/log/easytier.log';
const EASYTIER_PID = '/var/run/easytier.pid';

function et_lang()
{
    global $config;
    $lang = strtolower(str_replace('-', '_', (string)($config['system']['language'] ?? '')));
    if (preg_match('/^zh_(tw|hk|mo)|hant/', $lang)) {
        return 'zh_tw';
    }
    if (preg_match('/^zh($|_(cn|sg))|hans/', $lang)) {
        return 'zh_cn';
    }
    return 'en';
}

function et_t($text)
{
    static $zh = [
        'Status' => '状态', 'Configuration' => '配置', 'Peers' => '节点', 'Log' => '日志',
        'EasyTier service status' => 'EasyTier 服务状态', 'Service status' => '服务状态',
        'Running' => '运行中', 'Stopped' => '已停止', 'Version' => '版本', 'Process ID' => '进程 ID',
        'Node name' => '节点名称', 'Virtual address' => '虚拟地址', 'Network name' => '网络名称',
        'Configuration file' => '配置文件', 'Start' => '启动', 'Stop' => '停止', 'Restart' => '重启',
        'EasyTier configuration' => 'EasyTier 配置',
        'Save' => '保存', 'Save & Restart' => '保存并重启', 'EasyTier peer connection status' => 'EasyTier 节点连接状态',
        'Virtual IP' => '虚拟 IP', 'Hostname' => '主机名', 'Connection status' => '连接状态',
        'Latency' => '延迟', 'Packet loss' => '丢包率', 'Received' => '接收', 'Sent' => '发送',
        'Tunnel' => '隧道', 'NAT type' => 'NAT 类型', 'Local' => '本机', 'Relay' => '中继',
        'EasyTier log' => 'EasyTier 日志', 'No peer information is currently available.' => '当前没有可用的节点信息。',
        'Clear log' => '清除日志', 'Log cleared.' => '日志已清除。',
        'Are you sure you want to clear the EasyTier log?' => '确定要清除 EasyTier 日志吗？',
        'EasyTier is stopped. Start the service to view peer connections.' => 'EasyTier 已停止，请启动服务后查看节点连接。',
        'Unable to query EasyTier peers. Check that the RPC portal is 127.0.0.1:15888.' => '无法查询 EasyTier 节点，请确认 RPC 门户为 127.0.0.1:15888。',
        'Configuration saved.' => '配置已保存。', 'Configuration saved and restart submitted.' => '配置已保存，并已提交重启。',
        'The configuration cannot be empty.' => '配置不能为空。', 'The configuration contains invalid data.' => '配置包含无效数据。',
        'Service command submitted.' => '服务命令已提交。', 'CSRF validation failed.' => 'CSRF 校验失败。',
        'The configuration is stored with mode 0600. Protect the network secret.' => '配置文件以 0600 权限保存，请妥善保护网络密钥。',
        'EasyTier uses a dynamic interface. Do not assign easytier0 under Interfaces > Assignments.' => 'EasyTier 使用动态接口，请勿在“接口 > 分配”中添加 easytier0。',
        'Showing the last 100 lines. Network secrets are redacted.' => '显示最近 100 行，网络密钥已隐藏。',
        'Unable to save configuration.' => '无法保存配置。',
    ];
    static $zh_tw = [
        'Status' => '狀態', 'Configuration' => '設定', 'Peers' => '節點', 'Log' => '日誌',
        'EasyTier service status' => 'EasyTier 服務狀態', 'Service status' => '服務狀態',
        'Running' => '執行中', 'Stopped' => '已停止', 'Version' => '版本', 'Process ID' => '處理程序 ID',
        'Node name' => '節點名稱', 'Virtual address' => '虛擬位址', 'Network name' => '網路名稱',
        'Configuration file' => '設定檔', 'Start' => '啟動', 'Stop' => '停止', 'Restart' => '重新啟動',
        'EasyTier configuration' => 'EasyTier 設定', 'Save' => '儲存',
        'Save & Restart' => '儲存並重新啟動', 'EasyTier peer connection status' => 'EasyTier 節點連線狀態',
        'Virtual IP' => '虛擬 IP', 'Hostname' => '主機名稱', 'Connection status' => '連線狀態',
        'Latency' => '延遲', 'Packet loss' => '封包遺失率', 'Received' => '接收', 'Sent' => '傳送',
        'Tunnel' => '通道', 'NAT type' => 'NAT 類型', 'Local' => '本機', 'Relay' => '中繼',
        'EasyTier log' => 'EasyTier 日誌', 'Clear log' => '清除日誌', 'Log cleared.' => '日誌已清除。',
        'Are you sure you want to clear the EasyTier log?' => '確定要清除 EasyTier 日誌嗎？',
        'No peer information is currently available.' => '目前沒有可用的節點資訊。',
        'EasyTier is stopped. Start the service to view peer connections.' => 'EasyTier 已停止，請啟動服務後查看節點連線。',
        'Unable to query EasyTier peers. Check that the RPC portal is 127.0.0.1:15888.' => '無法查詢 EasyTier 節點，請確認 RPC 入口為 127.0.0.1:15888。',
        'Configuration saved.' => '設定已儲存。', 'Configuration saved and restart submitted.' => '設定已儲存，並已送出重新啟動要求。',
        'The configuration cannot be empty.' => '設定內容不可為空。', 'The configuration contains invalid data.' => '設定包含無效資料。',
        'Service command submitted.' => '服務命令已送出。', 'CSRF validation failed.' => 'CSRF 驗證失敗。',
        'The configuration is stored with mode 0600. Protect the network secret.' => '設定檔以 0600 權限儲存，請妥善保護網路密鑰。',
        'EasyTier uses a dynamic interface. Do not assign easytier0 under Interfaces > Assignments.' => 'EasyTier 使用動態介面，請勿在「介面 > 指派」中加入 easytier0。',
        'Showing the last 100 lines. Network secrets are redacted.' => '顯示最近 100 行，網路密鑰已隱藏。',
        'Unable to save configuration.' => '無法儲存設定。',
    ];
    return match (et_lang()) {
        'zh_cn' => $zh[$text] ?? $text,
        'zh_tw' => $zh_tw[$text] ?? $text,
        default => $text,
    };
}

function et_run($command, &$output = null)
{
    $lines = [];
    $status = 0;
    exec($command . ' 2>&1', $lines, $status);
    $output = implode("\n", $lines);
    return $status;
}

function et_running()
{
    return et_run('/usr/local/etc/rc.d/easytier onestatus') === 0;
}

function et_value($config, $key)
{
    return preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*"([^"]*)"/m', $config, $m) ? $m[1] : '';
}

if (empty($_SESSION['easytier_csrf'])) {
    $_SESSION['easytier_csrf'] = bin2hex(random_bytes(32));
}

$view = $_GET['view'] ?? 'status';
if (!in_array($view, ['status', 'config', 'peers', 'log'], true)) {
    $view = 'status';
}
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['easytier_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $message = et_t('CSRF validation failed.');
        $message_type = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if (in_array($action, ['save', 'save_restart'], true)) {
            $text = str_replace("\r\n", "\n", (string)($_POST['config'] ?? ''));
            if ($text === '') {
                $message = et_t('The configuration cannot be empty.');
                $message_type = 'danger';
            } elseif (str_contains($text, "\0")) {
                $message = et_t('The configuration contains invalid data.');
                $message_type = 'danger';
            } else {
                $tmp = EASYTIER_CONFIG . '.tmp';
                if (file_put_contents($tmp, $text, LOCK_EX) === false || !rename($tmp, EASYTIER_CONFIG)) {
                    @unlink($tmp);
                    $message = et_t('Unable to save configuration.');
                    $message_type = 'danger';
                } else {
                    chmod(EASYTIER_CONFIG, 0600);
                    if ($action === 'save_restart') {
                        exec('/usr/local/sbin/configctl easytier restart >/dev/null 2>&1 &');
                        $message = et_t('Configuration saved and restart submitted.');
                    } else {
                        $message = et_t('Configuration saved.');
                    }
                }
            }
        } elseif (in_array($action, ['start', 'stop', 'restart'], true)) {
            if ($action === 'start') {
                exec('/usr/sbin/sysrc -f /etc/rc.conf.d/easytier easytier_enable=YES >/dev/null 2>&1');
            } elseif ($action === 'stop') {
                exec('/usr/sbin/sysrc -f /etc/rc.conf.d/easytier easytier_enable=NO >/dev/null 2>&1');
            }
            exec('/usr/local/sbin/configctl easytier ' . $action . ' >/dev/null 2>&1 &');
            $message = et_t('Service command submitted.');
        } elseif ($action === 'clear_log') {
            exec('/usr/local/sbin/configctl easytier clear_log >/dev/null 2>&1');
            $message = et_t('Log cleared.');
        }
    }
}

$config_text = is_readable(EASYTIER_CONFIG) ? (string)file_get_contents(EASYTIER_CONFIG) : '';
$running = et_running();
$version = '';
et_run('/usr/local/sbin/easytier-core --version', $version);
$pid = is_readable(EASYTIER_PID) ? trim((string)file_get_contents(EASYTIER_PID)) : '';
$peer_rows = [];
$peer_error = '';
if ($view === 'peers' && $running) {
    $raw = '';
    if (et_run('/usr/bin/timeout 5 /usr/local/sbin/easytier-cli -p 127.0.0.1:15888 peer', $raw) === 0) {
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, '|') || str_contains($line, '---') || str_contains($line, 'ipv4')) continue;
            $cols = array_map('trim', explode('|', trim($line, '|')));
            if (count($cols) >= 10) $peer_rows[] = array_slice($cols, 0, 10);
        }
    } else {
        $peer_error = et_t('Unable to query EasyTier peers. Check that the RPC portal is 127.0.0.1:15888.');
    }
}
$log_text = '';
if (is_readable(EASYTIER_LOG)) {
    $lines = file(EASYTIER_LOG, FILE_IGNORE_NEW_LINES) ?: [];
    $log_text = implode("\n", array_slice($lines, -100));
    $log_text = preg_replace('/(network_secret\s*=\s*")[^"]*(")/i', '$1********$2', $log_text);
}

$pgtitle = [et_t('VPN'), 'EasyTier'];
include('head.inc');
include('fbegin.inc');
?>
<style>
.et-tabs{margin-bottom:15px}.content-box.et-content-box{margin-bottom:15px}.et-box-title{padding:10px 15px;border-bottom:1px solid #ddd;background:#fafafa;font-weight:600;line-height:20px}.et-box-body{padding:15px}.et-box-footer{padding:12px 15px;border-top:1px solid #ddd;background:#fafafa}.et-summary{margin:0}.et-summary th{width:220px}.et-summary th,.et-summary td{vertical-align:middle!important}.et-config{display:block;width:60em;max-width:100%;min-height:460px;font-family:Menlo,Monaco,Consolas,"Courier New",monospace;line-height:1.35;resize:vertical}.et-log{min-height:300px;max-height:540px;overflow:auto;white-space:pre-wrap;margin:0;font-family:Menlo,Monaco,Consolas,"Courier New",monospace;line-height:1.35}.et-actions{margin:0}.et-actions .btn{margin-right:6px}.et-table{margin-bottom:0}.et-table th{white-space:nowrap}.et-table th,.et-table td{vertical-align:middle!important}.et-table th:first-child,.et-table td:first-child{padding-left:15px}.et-table th:last-child,.et-table td:last-child{padding-right:15px}.et-inline-alert{margin:15px}.et-help{margin:8px 0 0}@media(max-width:767px){.et-summary th{width:42%}.et-actions .btn{margin-bottom:5px}.et-config{min-height:360px}}
</style>
<section class="page-content-main">
<div class="container-fluid"><div class="row"><div class="col-xs-12">
<ul class="nav nav-tabs et-tabs">
<?php foreach (['status'=>'Status','config'=>'Configuration','peers'=>'Peers','log'=>'Log'] as $key=>$label): ?>
  <li class="<?=$view === $key ? 'active' : ''?>"><a href="easytier.php?view=<?=$key?>"><?=htmlspecialchars(et_t($label))?></a></li>
<?php endforeach; ?>
</ul>
<?php if ($message): ?><div class="alert alert-<?=$message_type?>"><?=htmlspecialchars($message)?></div><?php endif; ?>

<?php if ($view === 'status'): ?>
<div class="content-box et-content-box"><div class="et-box-title"><?=et_t('EasyTier service status')?></div><div class="et-box-body">
<table class="table table-condensed table-striped et-summary"><tbody>
<tr><th><?=et_t('Service status')?></th><td><span class="label label-<?=$running?'success':'danger'?>"><?=$running?et_t('Running'):et_t('Stopped')?></span></td></tr>
<tr><th><?=et_t('Version')?></th><td><?=htmlspecialchars(trim($version) ?: '-')?></td></tr>
<tr><th><?=et_t('Process ID')?></th><td><?=htmlspecialchars($pid ?: '-')?></td></tr>
<tr><th><?=et_t('Node name')?></th><td><?=htmlspecialchars(et_value($config_text, 'hostname') ?: '-')?></td></tr>
<tr><th><?=et_t('Virtual address')?></th><td><?=htmlspecialchars(et_value($config_text, 'ipv4') ?: '-')?></td></tr>
<tr><th><?=et_t('Network name')?></th><td><?=htmlspecialchars(et_value($config_text, 'network_name') ?: '-')?></td></tr>
<tr><th><?=et_t('Configuration file')?></th><td><code><?=EASYTIER_CONFIG?></code></td></tr>
</tbody></table></div><div class="et-box-footer"><form method="post" class="et-actions"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['easytier_csrf'])?>">
<button class="btn btn-success" name="action" value="start" <?=$running?'disabled':''?>><?=et_t('Start')?></button>
<button class="btn btn-danger" name="action" value="stop" <?=$running?'':'disabled'?>><?=et_t('Stop')?></button>
<button class="btn btn-warning" name="action" value="restart" <?=$running?'':'disabled'?>><?=et_t('Restart')?></button></form></div></div>
<div class="alert alert-info"><?=et_t('EasyTier uses a dynamic interface. Do not assign easytier0 under Interfaces > Assignments.')?></div>

<?php elseif ($view === 'config'): ?>
<div class="content-box et-content-box"><div class="et-box-title"><?=et_t('EasyTier configuration')?></div>
<form method="post"><div class="et-box-body"><label for="config"><?=et_t('Configuration file')?></label><textarea id="config" name="config" class="form-control et-config" spellcheck="false"><?=htmlspecialchars($config_text, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')?></textarea><p class="help-block et-help"><?=et_t('The configuration is stored with mode 0600. Protect the network secret.')?></p></div>
<div class="et-box-footer"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['easytier_csrf'])?>"><button class="btn btn-primary" name="action" value="save"><?=et_t('Save')?></button> <button class="btn btn-warning" name="action" value="save_restart"><?=et_t('Save & Restart')?></button></div></form></div>

<?php elseif ($view === 'peers'): ?>
<div class="content-box et-content-box"><div class="et-box-title"><?=et_t('EasyTier peer connection status')?></div><div style="padding:0">
<?php if (!$running): ?><div class="alert alert-warning et-inline-alert"><?=et_t('EasyTier is stopped. Start the service to view peer connections.')?></div>
<?php elseif ($peer_error): ?><div class="alert alert-danger et-inline-alert"><?=htmlspecialchars($peer_error)?></div>
<?php elseif (!$peer_rows): ?><div class="alert alert-info et-inline-alert"><?=et_t('No peer information is currently available.')?></div>
<?php else: ?><div class="table-responsive"><table class="table table-condensed table-striped table-hover et-table"><thead><tr>
<?php foreach (['Virtual IP','Hostname','Connection status','Latency','Packet loss','Received','Sent','Tunnel','NAT type','Version'] as $h): ?><th><?=et_t($h)?></th><?php endforeach; ?>
</tr></thead><tbody><?php foreach ($peer_rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?=htmlspecialchars($cell)?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></div>

<?php else: ?>
<div class="content-box et-content-box"><div class="et-box-title"><?=et_t('EasyTier log')?></div><div class="et-box-body"><pre class="et-log"><?=htmlspecialchars($log_text)?></pre><p class="help-block et-help"><?=et_t('Showing the last 100 lines. Network secrets are redacted.')?></p></div><div class="et-box-footer"><form method="post" class="et-actions"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['easytier_csrf'])?>"><button class="btn btn-danger" name="action" value="clear_log" onclick="return confirm(<?=htmlspecialchars(json_encode(et_t('Are you sure you want to clear the EasyTier log?'), JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>);"><?=et_t('Clear log')?></button></form></div></div>
<?php endif; ?>
</div></div></div>
</section>
<?php include('foot.inc'); ?>
