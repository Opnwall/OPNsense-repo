<?php
$allowautocomplete = true;
require_once('guiconfig.inc');

const SPEEDTEST_STATE_DIR = '/var/db/speedtest';
const SPEEDTEST_SETTINGS = SPEEDTEST_STATE_DIR . '/settings.json';
const SPEEDTEST_RESULT = SPEEDTEST_STATE_DIR . '/result.json';

function speedtest_lang(): string {
    global $config;
    $language = strtolower(str_replace('-', '_', (string)($config['system']['language'] ?? 'en')));
    if (in_array($language, ['zh_cn', 'zh_hans_cn'], true)) return 'zh_Hans';
    if (in_array($language, ['zh_tw', 'zh_hant_tw', 'zh_hk', 'zh_hant_hk'], true)) return 'zh_Hant';
    return 'en';
}

function speedtest_t(string $key): string {
    static $messages = [
        'en' => [
            'diagnostics'=>'Diagnostics','title'=>'Speedtest','run'=>'Start Test','clear'=>'Clear Result','settings'=>'Test Settings','interface'=>'Outbound Interface','interface_help'=>'Only enabled interfaces with an IPv4 gateway are shown.','automatic'=>'Automatic','server'=>'Test Server','server_auto'=>'Automatic selection','server_help'=>'Refresh the server list after changing the outbound interface.','refresh'=>'Refresh Servers','refreshing'=>'Retrieving test servers.','server_list_failed'=>'Unable to retrieve available test servers.','threads'=>'Connections','result'=>'Test Result','time'=>'Test Time','isp'=>'ISP / Public IP','test_server'=>'Test Server','latency'=>'Latency','jitter'=>'Jitter','loss'=>'Packet Loss','download'=>'Download','upload'=>'Upload','running'=>'Testing, please wait.......','failed'=>'The speed test failed.','invalid_server'=>'Select a valid test server.','invalid_threads'=>'Connections must be between 1 and 16.','engine'=>'Engine','distance'=>'Distance'
        ],
        'zh_Hans' => [
            'diagnostics'=>'诊断','title'=>'Speedtest','run'=>'开始测速','clear'=>'清除结果','settings'=>'测速设置','interface'=>'出站接口','interface_help'=>'仅显示已启用且配置 IPv4 网关的接口。','automatic'=>'自动选择','server'=>'测速服务器','server_auto'=>'自动选择','server_help'=>'更改出站接口后，请刷新服务器列表。','refresh'=>'刷新服务器','refreshing'=>'正在获取测速服务器。','server_list_failed'=>'无法获取可用测速服务器。','threads'=>'并发连接','result'=>'测速结果','time'=>'测试时间','isp'=>'运营商 / 公网 IP','test_server'=>'测速服务器','latency'=>'延迟','jitter'=>'抖动','loss'=>'丢包率','download'=>'下载','upload'=>'上传','running'=>'正在测速，请等待.......','failed'=>'互联网测速失败。','invalid_server'=>'请选择有效的测速服务器。','invalid_threads'=>'并发连接必须在 1 到 16 之间。','engine'=>'测速引擎','distance'=>'距离'
        ],
        'zh_Hant' => [
            'diagnostics'=>'診斷','title'=>'Speedtest','run'=>'開始測速','clear'=>'清除結果','settings'=>'測速設定','interface'=>'出站介面','interface_help'=>'僅顯示已啟用且設定 IPv4 閘道的介面。','automatic'=>'自動選擇','server'=>'測速伺服器','server_auto'=>'自動選擇','server_help'=>'變更出站介面後，請重新整理伺服器清單。','refresh'=>'重新整理伺服器','refreshing'=>'正在取得測速伺服器。','server_list_failed'=>'無法取得可用測速伺服器。','threads'=>'並行連線','result'=>'測速結果','time'=>'測試時間','isp'=>'電信業者 / 公網 IP','test_server'=>'測速伺服器','latency'=>'延遲','jitter'=>'抖動','loss'=>'封包遺失率','download'=>'下載','upload'=>'上傳','running'=>'正在測速，請等待.......','failed'=>'網際網路測速失敗。','invalid_server'=>'請選擇有效的測速伺服器。','invalid_threads'=>'並行連線必須介於 1 到 16 之間。','engine'=>'測速引擎','distance'=>'距離'
        ],
    ];
    $language = speedtest_lang();
    return $messages[$language][$key] ?? $messages['en'][$key] ?? $key;
}

function speedtest_ensure_state(): void {
    if (!is_dir(SPEEDTEST_STATE_DIR)) mkdir(SPEEDTEST_STATE_DIR, 0700, true);
}

function speedtest_settings(): array {
    $defaults = ['interface'=>'auto','server_id'=>'','threads'=>'4'];
    if (!is_readable(SPEEDTEST_SETTINGS)) return $defaults;
    $value = json_decode((string)file_get_contents(SPEEDTEST_SETTINGS), true);
    return is_array($value) ? array_merge($defaults, $value) : $defaults;
}

function speedtest_save_settings(array $settings): void {
    speedtest_ensure_state();
    file_put_contents(SPEEDTEST_SETTINGS, json_encode($settings, JSON_UNESCAPED_SLASHES), LOCK_EX);
    chmod(SPEEDTEST_SETTINGS, 0600);
}

function speedtest_runtime_ipv4(string $device): string {
    if (!preg_match('/^[a-zA-Z0-9_.:-]+$/', $device)) return '';
    exec('/sbin/ifconfig ' . escapeshellarg($device) . ' inet 2>/dev/null', $output);
    foreach ($output as $line) {
        if (preg_match('/\binet\s+([0-9.]+)/', $line, $match) && filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $match[1];
    }
    return '';
}

function speedtest_outbound_interfaces(): array {
    global $config;
    $result = [];
    foreach ((array)($config['interfaces'] ?? []) as $name => $item) {
        if (!is_array($item) || empty($item['enable']) || empty($item['gateway']) || empty($item['if'])) continue;
        $address = speedtest_runtime_ipv4((string)$item['if']);
        if ($address === '') continue;
        $description = trim((string)($item['descr'] ?? '')) ?: strtoupper((string)$name);
        $result[$name] = ['description'=>$description, 'device'=>(string)$item['if'], 'address'=>$address];
    }
    return $result;
}

function speedtest_source_address(string $interface, array $interfaces): string {
    return isset($interfaces[$interface]) ? (string)$interfaces[$interface]['address'] : '';
}

function speedtest_server_cache(string $interface): string {
    $key = preg_replace('/[^a-z0-9_]/i', '', $interface) ?: 'auto';
    return SPEEDTEST_STATE_DIR . "/servers-{$key}.json";
}

function speedtest_load_servers(string $interface): array {
    $file = speedtest_server_cache($interface);
    if (!is_readable($file)) return [];
    $items = json_decode((string)file_get_contents($file), true);
    if (!is_array($items)) return [];
    return array_values(array_filter($items, function($server) {
        return is_array($server) && preg_match('/^\d+(?:\.\d+)?ms$/i', (string)($server['latency'] ?? ''));
    }));
}

function speedtest_fetch_servers(string $interface, array $interfaces): array {
    $command = '/bin/timeout 40 /usr/local/bin/opnsense-speedtest --list';
    $source = speedtest_source_address($interface, $interfaces);
    if ($source !== '') $command .= ' --source ' . escapeshellarg($source);
    exec($command . ' 2>&1', $output, $status);
    if ($status !== 0) return [];
    $servers = [];
    foreach ($output as $line) {
        if (!preg_match('/^\[\s*(\d+)\]\s+([0-9.]+)km\s+(\S+)\s+(.+?)\s+\((.*?)\)\s+by\s+(.+)$/u', trim($line), $match)) continue;
        if (!preg_match('/^\d+(?:\.\d+)?ms$/i', $match[3])) continue;
        $servers[] = ['id'=>$match[1], 'distance'=>(float)$match[2], 'latency'=>$match[3], 'name'=>$match[4], 'country'=>$match[5], 'sponsor'=>$match[6]];
    }
    if ($servers) {
        speedtest_ensure_state();
        file_put_contents(speedtest_server_cache($interface), json_encode($servers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        chmod(speedtest_server_cache($interface), 0600);
    }
    return $servers;
}

function speedtest_load_result(): ?array {
    if (!is_readable(SPEEDTEST_RESULT)) return null;
    $value = json_decode((string)file_get_contents(SPEEDTEST_RESULT), true);
    return is_array($value) ? $value : null;
}

function speedtest_duration_ms($value): float { return is_numeric($value) ? (float)$value / 1000000 : 0.0; }

function speedtest_packet_loss(array $value): ?float {
    $sent = (int)($value['sent'] ?? 0); $duplicate = (int)($value['dup'] ?? 0); $maximum = (int)($value['max'] ?? 0);
    if ($sent === 0 || $maximum < 0) return null;
    return max(0.0, (1.0 - (($sent - $duplicate) / ($maximum + 1))) * 100.0);
}

$pgtitle = [speedtest_t('diagnostics'), speedtest_t('title')];
$settings = speedtest_settings();
$interfaces = speedtest_outbound_interfaces();
$input_errors = [];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear'])) {
        @unlink(SPEEDTEST_RESULT);
        header('Location: diagnostics_speedtest.php'); exit;
    }
    if (isset($_POST['refresh_servers'])) {
        $interface = (string)($_POST['interface'] ?? 'auto');
        if ($interface !== 'auto' && !isset($interfaces[$interface])) $interface = 'auto';
        $settings['interface'] = $interface;
        $settings['server_id'] = '';
        speedtest_save_settings($settings);
        if (!speedtest_fetch_servers($interface, $interfaces)) $error_message = speedtest_t('server_list_failed');
        else { header('Location: diagnostics_speedtest.php'); exit; }
    }
    if (isset($_POST['run'])) {
        $interface = (string)($_POST['interface'] ?? 'auto');
        if ($interface !== 'auto' && !isset($interfaces[$interface])) $interface = 'auto';
        $server_id = trim((string)($_POST['server_id'] ?? ''));
        $threads = filter_var($_POST['threads'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>16]]);
        $server_ids = array_column(speedtest_load_servers($interface), 'id');
        if ($server_id !== '' && !in_array($server_id, $server_ids, true)) $input_errors[] = speedtest_t('invalid_server');
        if ($threads === false) $input_errors[] = speedtest_t('invalid_threads');
        if (!$input_errors) {
            $settings = ['interface'=>$interface,'server_id'=>$server_id,'threads'=>(string)$threads];
            speedtest_save_settings($settings);
            $args = ['--json','--thread',(string)$threads];
            if ($server_id !== '') array_push($args, '--server', $server_id);
            $source = speedtest_source_address($interface, $interfaces);
            if ($source !== '') array_push($args, '--source', $source);
            $command = '/bin/timeout 180 /usr/local/bin/opnsense-speedtest';
            foreach ($args as $arg) $command .= ' ' . escapeshellarg($arg);
            set_time_limit(190);
            exec($command . ' 2>&1', $output, $status);
            $raw = implode("\n", $output);
            $start = strpos($raw, '{');
            $result_data = $start === false ? null : json_decode(substr($raw, $start), true);
            if ($status === 0 && is_array($result_data) && !empty($result_data['servers'][0])) {
                speedtest_ensure_state();
                file_put_contents(SPEEDTEST_RESULT, json_encode($result_data, JSON_UNESCAPED_SLASHES), LOCK_EX);
                chmod(SPEEDTEST_RESULT, 0600);
                header('Location: diagnostics_speedtest.php'); exit;
            }
            $error_message = speedtest_t('failed') . ($raw !== '' ? ' ' . trim($raw) : '');
        }
    }
}

$result = speedtest_load_result();
$server_result = $result['servers'][0] ?? null;
$user = $result['user_info'] ?? [];
$packet_loss = is_array($server_result['packet_loss'] ?? null) ? speedtest_packet_loss($server_result['packet_loss']) : null;
$available_servers = speedtest_load_servers((string)$settings['interface']);

include('head.inc');
include('fbegin.inc');
?>
<style>
.speedtest-page{max-width:none;width:100%}.speedtest-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border:1px solid #ddd;margin-bottom:15px}.speedtest-metric{padding:18px;border-right:1px solid #ddd;background:#fff}.speedtest-metric:last-child{border-right:0}.speedtest-metric span{color:#667;display:block;font-size:12px}.speedtest-metric strong{display:block;font-size:25px;margin-top:5px;white-space:nowrap}.speedtest-metric small{color:#667;font-size:12px}.speedtest-control{max-width:480px;width:100%!important}.speedtest-refresh{display:block;margin-top:8px}.speedtest-default-icon{color:#333;margin-right:6px}.speedtest-actions{border-bottom:0!important;margin-bottom:0!important;padding-bottom:0!important}.speedtest-result th{padding-left:15px!important;width:200px}.speedtest-panel{border-radius:0;margin-bottom:12px}.speedtest-panel-heading{background:#f5f5f5;border-bottom:1px solid #ddd;color:#333;font-weight:700;padding:8px 12px}@media(max-width:800px){.speedtest-summary{grid-template-columns:1fr 1fr}.speedtest-metric{border-bottom:1px solid #ddd}}
</style>
<section class="page-content-main"><div class="container-fluid speedtest-page"><div class="row"><section class="col-xs-12">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($error_message !== ''): ?><div class="alert alert-danger" role="alert"><?=htmlspecialchars($error_message)?></div><?php endif; ?>
<div class="alert alert-info" id="speedtest-status" style="display:none"><i class="fa fa-spinner fa-spin icon-embed-btn"></i><strong><?=speedtest_t('running')?></strong></div>
<?php if ($server_result): ?>
<div class="speedtest-summary">
<div class="speedtest-metric"><span><?=speedtest_t('latency')?></span><strong><?=number_format(speedtest_duration_ms($server_result['latency'] ?? 0),2)?> <small>ms</small></strong></div>
<div class="speedtest-metric"><span><?=speedtest_t('jitter')?></span><strong><?=number_format(speedtest_duration_ms($server_result['jitter'] ?? 0),2)?> <small>ms</small></strong></div>
<div class="speedtest-metric"><span><?=speedtest_t('loss')?></span><strong><?=$packet_loss===null?'N/A':number_format($packet_loss,2).' <small>%</small>'?></strong></div>
<div class="speedtest-metric"><span><?=speedtest_t('download')?></span><strong><?=number_format(((float)($server_result['dl_speed']??0))*8/1000000,2)?> <small>Mbps</small></strong></div>
<div class="speedtest-metric"><span><?=speedtest_t('upload')?></span><strong><?=number_format(((float)($server_result['ul_speed']??0))*8/1000000,2)?> <small>Mbps</small></strong></div>
</div>
<div class="panel panel-default speedtest-panel"><div class="speedtest-panel-heading"><?=speedtest_t('result')?></div><table class="table table-striped table-condensed speedtest-result">
<tr><th><?=speedtest_t('time')?></th><td><?=htmlspecialchars((string)($result['timestamp']??''))?></td></tr>
<tr><th><?=speedtest_t('isp')?></th><td><?=htmlspecialchars(trim((string)($user['isp']??'').' / '.(string)($user['IP']??$user['ip']??''),' /'))?></td></tr>
<tr><th><?=speedtest_t('test_server')?></th><td><?=htmlspecialchars('['.($server_result['id']??'').'] '.($server_result['name']??'').' - '.($server_result['sponsor']??''))?></td></tr>
<tr><th><?=speedtest_t('distance')?></th><td><?=number_format((float)($server_result['distance']??0),2)?> km</td></tr>
<tr><th><?=speedtest_t('engine')?></th><td>speedtest-go 1.7.10</td></tr></table></div>
<?php endif; ?>
<form method="post" class="form-horizontal" id="speedtest-form"><div class="panel panel-default speedtest-panel"><div class="speedtest-panel-heading"><?=speedtest_t('settings')?></div><div class="panel-body">
<div class="form-group"><label class="col-sm-2 control-label"><?=speedtest_t('interface')?></label><div class="col-sm-10"><select class="form-control speedtest-control" name="interface"><option value="auto"><?=speedtest_t('automatic')?></option><?php foreach($interfaces as $name=>$item):?><option value="<?=htmlspecialchars($name)?>" <?=$settings['interface']===$name?'selected':''?>><?=htmlspecialchars($item['description'])?> (<?=htmlspecialchars($name)?>)</option><?php endforeach;?></select><span class="help-block"><?=speedtest_t('interface_help')?></span></div></div>
<div class="form-group"><label class="col-sm-2 control-label"><?=speedtest_t('server')?></label><div class="col-sm-10"><select class="form-control speedtest-control" name="server_id"><option value=""><?=speedtest_t('server_auto')?></option><?php foreach($available_servers as $server):?><option value="<?=htmlspecialchars($server['id'])?>" <?=$settings['server_id']===$server['id']?'selected':''?>><?=htmlspecialchars('['.$server['id'].'] '.$server['name'].' - '.$server['sponsor'].' / '.$server['latency'].' / '.number_format((float)$server['distance'],1).' km')?></option><?php endforeach;?></select><button class="btn btn-default speedtest-refresh" type="submit" name="refresh_servers" id="refresh-servers"><i class="fa fa-refresh speedtest-default-icon"></i><?=speedtest_t('refresh')?></button><span class="help-block"><?=speedtest_t('server_help')?></span></div></div>
<div class="form-group"><label class="col-sm-2 control-label"><?=speedtest_t('threads')?></label><div class="col-sm-10"><input class="form-control speedtest-control" type="number" min="1" max="16" name="threads" value="<?=htmlspecialchars($settings['threads'])?>"></div></div>
<div class="form-group speedtest-actions"><div class="col-sm-offset-2 col-sm-10"><button class="btn btn-primary" type="submit" name="run" id="run-test"><i class="fa fa-tachometer icon-embed-btn"></i><?=speedtest_t('run')?></button> <button class="btn btn-default" type="submit" name="clear"><i class="fa fa-trash speedtest-default-icon"></i><?=speedtest_t('clear')?></button></div></div>
</div></div></form>
</section></div></div></section>
<script>document.getElementById('speedtest-form').addEventListener('submit',function(e){if(e.submitter&&(e.submitter.id==='run-test'||e.submitter.id==='refresh-servers')){e.preventDefault();e.submitter.disabled=true;const s=document.getElementById('speedtest-status');s.querySelector('strong').textContent=e.submitter.id==='refresh-servers'?<?=json_encode(speedtest_t('refreshing'))?>:<?=json_encode(speedtest_t('running'))?>;s.style.display='block';window.scrollTo({top:0,behavior:'smooth'});const a=document.createElement('input');a.type='hidden';a.name=e.submitter.id==='refresh-servers'?'refresh_servers':'run';a.value='1';this.appendChild(a);setTimeout(()=>HTMLFormElement.prototype.submit.call(this),80);}});</script>
<?php include('foot.inc'); ?>
