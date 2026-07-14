<?php

/*
 * Copyright (C) 2014-2026 Deciso B.V.
 * Copyright (C) 2010 Erik Fonnesbeck
 * Copyright (C) 2008-2010 Ermal Luçi
 * Copyright (C) 2004-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2006 Daniel S. Haischt
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_DIR', '/usr/local/etc/sing-box/sub');
define('ENV_FILE', BASE_DIR . '/env');
define('SCRIPT_FILE', BASE_DIR . '/sub.sh');
define('LOG_FILE', '/var/log/sing-box_sub.log');
define('LOCK_FILE', '/var/run/sing-box-sub.lock');
define('LOG_TAIL_LINES', 100);
define('LOG_MAX_BYTES', 262144);
define('LOGS_ENDPOINT', '/sing-box_sub_log.php');
define('CSRF_TOKEN_KEY', 'sing_box_sub_csrf_token');
define('SUBSCRIBE_ACTION', 'start_subscribe');
define('CLEAR_LOG_ACTION', 'clear_log');
define('ALLOWED_URL_SCHEMES', ['http', 'https']);

action_generate_csrf_token();

function sub_lang() {
    if (is_readable('/conf/config.xml')) {
        $config_xml = @file_get_contents('/conf/config.xml');
        if (is_string($config_xml) && preg_match('/<language>([^<]+)<\/language>/', $config_xml, $matches)) {
            return trim($matches[1]) === 'zh_CN' ? 'zh' : 'en';
        }
    }

    return 'en';
}


function sub_t($text) {
    static $map = [
        'zh' => [
            'Failed to write log: %s' => '日志写入失败: %s',
            'Failed to save environment variable: %s' => '环境变量保存失败: %s',
            'Invalid subscription URL.' => '订阅地址格式无效！',
            'Failed to parse subscription URL.' => '订阅地址解析失败！',
            'Only HTTP or HTTPS subscription URLs are allowed.' => '订阅地址仅允许使用 HTTP 或 HTTPS。',
            'Subscription URL is missing a host name.' => '订阅地址缺少主机名。',
            'Subscription URL cannot use private or reserved addresses.' => '订阅地址不能使用内网或保留地址。',
            'Failed to resolve subscription URL host name.' => '订阅地址主机名解析失败。',
            'Subscription URL resolves to a private or reserved address and was rejected.' => '订阅地址解析到了内网或保留地址，已拒绝保存。',
            'Failed to clear log: %s' => '日志清空失败: %s',
            'Unable to create lock file; subscription task was not executed.' => '无法创建锁文件，订阅任务未执行。',
            'Subscription task is already running; refusing duplicate start.' => '订阅任务已在执行中，拒绝重复启动。',
            'Subscription script does not exist or is not executable: %s' => '订阅脚本不存在或不可执行：%s',
            'Starting subscription task.' => '开始执行订阅操作。',
            'Subscription task finished. Exit code: %d' => '订阅操作执行完毕！退出码：%d',
            'Subscription URL saved: %s' => '订阅地址已保存：%s',
            'Address saved successfully.' => '地址保存成功！',
            'Failed to save subscription URL.' => '保存订阅地址失败！',
            'Subscription task is running. Do not submit it again.' => '订阅任务正在执行中，请勿重复提交。',
            'Unable to acquire task lock; subscription task was not executed.' => '无法获取任务锁，订阅任务未执行。',
            'Subscription task completed successfully.' => '订阅操作执行成功。',
            'Subscription task failed with exit code: %d. Check the log.' => '订阅操作执行失败，退出码：%d。请查看日志。',
            'Log cleared.' => '日志已清空！',
            'Failed to clear the log.' => '日志清空失败！',
            'CSRF validation failed. Please refresh the page and try again.' => 'CSRF 校验失败，请刷新页面后重试。',
            'Subscription Management' => '订阅管理',
            'Subscription URL' => '订阅地址',
            'Enter HTTP or HTTPS subscription URL' => '输入 HTTP 或 HTTPS 订阅地址',
            'Save Settings' => '保存设置',
            'Confirm start subscription now?' => '确认立即开始订阅吗？',
            'Start Subscription' => '开始订阅',
            'Log Viewer' => '日志视图',
            'Confirm clear log?' => '确认清空日志吗？',
            'Clear Log' => '清空日志',
            'Error' => '错误',
            'Failed to load logs: ' => '无法加载日志：',
        ],
    ];
    $lang = sub_lang();
    return $map[$lang][$text] ?? $text;
}

function sub_js($text) {
    return json_encode(sub_t($text), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

function log_message($message, $log_file = LOG_FILE) {
    $time = date("Y-m-d H:i:s");
    $log_entry = "[{$time}] {$message}\n";
    try {
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        error_log(sprintf(sub_t('Failed to write log: %s'), $e->getMessage()));
    }
}

function action_generate_csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

function get_csrf_token() {
    return $_SESSION[CSRF_TOKEN_KEY] ?? '';
}

function verify_csrf_token($token) {
    $session_token = $_SESSION[CSRF_TOKEN_KEY] ?? '';
    return !empty($session_token) && is_string($token) && hash_equals($session_token, $token);
}

function add_message(array &$messages, $type, $text) {
    $messages[] = [
        'type' => $type,
        'text' => $text,
    ];
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_valid_env_key($key) {
    return is_string($key) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) === 1;
}

function quote_env_value($value) {
    return str_replace("'", "'\\''", (string)$value);
}

function save_env_variable($key, $value, $env_file = ENV_FILE) {
    if (!is_valid_env_key($key)) {
        return false;
    }

    $lines = file_exists($env_file) ? file($env_file, FILE_IGNORE_NEW_LINES) : [];
    $new_lines = [];
    $pattern = '/^export\\s+' . preg_quote($key, '/') . '=.*/';

    foreach ($lines as $line) {
        if (!preg_match($pattern, $line)) {
            $new_lines[] = $line;
        }
    }

    $quoted_value = quote_env_value($value);
    $new_lines[] = "export {$key}='{$quoted_value}'";

    try {
        file_put_contents($env_file, implode("\n", $new_lines) . "\n", LOCK_EX);
        return true;
    } catch (Exception $e) {
        error_log(sprintf(sub_t('Failed to save environment variable: %s'), $e->getMessage()));
        return false;
    }
}

function parse_env_value($value) {
    $value = trim((string)$value);
    if (preg_match("/^'(.*)'$/s", $value, $matches)) {
        return str_replace("'\\''", "'", $matches[1]);
    }
    if (preg_match('/^"(.*)"$/s', $value, $matches)) {
        return stripcslashes($matches[1]);
    }
    return $value;
}

function load_env_variables($env_file = ENV_FILE) {
    $env_vars = [];
    if (!file_exists($env_file)) {
        return $env_vars;
    }

    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (preg_match('/^export\s+([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
            $env_vars[$matches[1]] = parse_env_value($matches[2]);
        }
    }

    return $env_vars;
}

function is_private_or_reserved_ip($ip) {
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

function resolve_hostname_ips($host) {
    $ips = [];

    $a_records = @dns_get_record($host, DNS_A);
    if (is_array($a_records)) {
        foreach ($a_records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }
    }

    $aaaa_records = @dns_get_record($host, DNS_AAAA);
    if (is_array($aaaa_records)) {
        foreach ($aaaa_records as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
    }

    return array_values(array_unique($ips));
}

function validate_subscribe_url($url, &$error_message = '') {
    if (!is_string($url)) {
        $error_message = sub_t('Invalid subscription URL.');
        return false;
    }

    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = sub_t('Invalid subscription URL.');
        return false;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        $error_message = sub_t('Failed to parse subscription URL.');
        return false;
    }

    $scheme = strtolower($parts['scheme'] ?? '');
    if (!in_array($scheme, ALLOWED_URL_SCHEMES, true)) {
        $error_message = sub_t('Only HTTP or HTTPS subscription URLs are allowed.');
        return false;
    }

    $host = $parts['host'] ?? '';
    if ($host === '') {
        $error_message = sub_t('Subscription URL is missing a host name.');
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (is_private_or_reserved_ip($host)) {
            $error_message = sub_t('Subscription URL cannot use private or reserved addresses.');
            return false;
        }
        return true;
    }

    $resolved_ips = resolve_hostname_ips($host);
    if (empty($resolved_ips)) {
        $error_message = sub_t('Failed to resolve subscription URL host name.');
        return false;
    }

    foreach ($resolved_ips as $ip) {
        if (is_private_or_reserved_ip($ip)) {
            $error_message = sub_t('Subscription URL resolves to a private or reserved address and was rejected.');
            return false;
        }
    }

    return true;
}

function clear_log($log_file = LOG_FILE) {
    try {
        file_put_contents($log_file, '', LOCK_EX);
        return true;
    } catch (Exception $e) {
        error_log(sprintf(sub_t('Failed to clear log: %s'), $e->getMessage()));
        return false;
    }
}

function read_log_tail($log_file = LOG_FILE, $max_lines = LOG_TAIL_LINES, $max_bytes = LOG_MAX_BYTES) {
    if (!file_exists($log_file) || !is_readable($log_file)) {
        return '';
    }

    $fp = @fopen($log_file, 'rb');
    if ($fp === false) {
        return '';
    }

    $file_size = filesize($log_file);
    if ($file_size === false) {
        fclose($fp);
        return '';
    }

    $read_size = min($file_size, $max_bytes);
    if ($read_size <= 0) {
        fclose($fp);
        return '';
    }

    if ($read_size > 0) {
        fseek($fp, -$read_size, SEEK_END);
    }

    $content = fread($fp, $read_size);
    fclose($fp);

    if ($content === false || $content === '') {
        return '';
    }

    $lines = preg_split("/\r\n|\n|\r/", $content);
    if ($file_size > $read_size && !empty($lines)) {
        array_shift($lines);
    }

    $tail_lines = array_slice($lines, -$max_lines);
    return implode("\n", $tail_lines);
}

function is_subscribe_running($lock_file = LOCK_FILE) {
    if (!file_exists($lock_file)) {
        return false;
    }

    $fp = @fopen($lock_file, 'c');
    if ($fp === false) {
        return false;
    }

    $locked = !flock($fp, LOCK_EX | LOCK_NB);
    if (!$locked) {
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return $locked;
}

function execute_subscribe_script(&$return_var, &$output_lines) {
    $return_var = 1;
    $output_lines = [];

    $lock_fp = @fopen(LOCK_FILE, 'c');
    if ($lock_fp === false) {
        log_message(sub_t('Unable to create lock file; subscription task was not executed.'));
        return false;
    }

    if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
        fclose($lock_fp);
        log_message(sub_t('Subscription task is already running; refusing duplicate start.'));
        return false;
    }

    if (!file_exists(SCRIPT_FILE) || !is_executable(SCRIPT_FILE)) {
        $return_var = 127;
        log_message(sprintf(sub_t('Subscription script does not exist or is not executable: %s'), SCRIPT_FILE));
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
        return true;
    }

    log_message(sub_t('Starting subscription task.'));
    $cmd = '/bin/sh ' . escapeshellarg(SCRIPT_FILE) . ' >> ' . escapeshellarg(LOG_FILE) . ' 2>&1';
    exec($cmd, $output_lines, $return_var);
    log_message(sprintf(sub_t('Subscription task finished. Exit code: %d'), $return_var));

    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);

    return true;
}

function handle_save_action(array &$messages) {
    $url = filter_input(INPUT_POST, 'subscribe_url', FILTER_UNSAFE_RAW);
    $url = is_string($url) ? trim($url) : '';

    $error_message = '';
    if (!validate_subscribe_url($url, $error_message)) {
        add_message($messages, 'danger', $error_message);
        return;
    }

    if (save_env_variable('CLASH_URL', $url)) {
        log_message(sprintf(sub_t('Subscription URL saved: %s'), $url));
        add_message($messages, 'success', sub_t('Address saved successfully.'));
        return;
    }

    add_message($messages, 'danger', sub_t('Failed to save subscription URL.'));
}

function handle_subscribe_action(array &$messages) {
    if (is_subscribe_running()) {
        add_message($messages, 'warning', sub_t('Subscription task is running. Do not submit it again.'));
        return;
    }

    $return_var = 1;
    $output_lines = [];
    $executed = execute_subscribe_script($return_var, $output_lines);

    if (!$executed) {
        add_message($messages, 'danger', sub_t('Unable to acquire task lock; subscription task was not executed.'));
        return;
    }

    if ($return_var === 0) {
        add_message($messages, 'success', sub_t('Subscription task completed successfully.'));
        return;
    }

    add_message($messages, 'danger', sprintf(sub_t('Subscription task failed with exit code: %d. Check the log.'), $return_var));
}

function handle_clear_log_action(array &$messages) {
    if (clear_log()) {
        log_message(sub_t('Log cleared.'));
        add_message($messages, 'success', sub_t('Log cleared.'));
        return;
    }

    add_message($messages, 'danger', sub_t('Failed to clear the log.'));
}

function handle_form_submission() {
    $messages = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $messages;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        add_message($messages, 'danger', sub_t('CSRF validation failed. Please refresh the page and try again.'));
        return $messages;
    }

    if (isset($_POST['save'])) {
        handle_save_action($messages);
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case SUBSCRIBE_ACTION:
                handle_subscribe_action($messages);
                break;
            case CLEAR_LOG_ACTION:
                handle_clear_log_action($messages);
                break;
        }
    }

    return $messages;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'log') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'running' => is_subscribe_running(),
        'log_content' => read_log_tail(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$messages = handle_form_submission();
$env_vars = load_env_variables();
$current_url = $env_vars['CLASH_URL'] ?? '';
$log_content = h(read_log_tail());
$csrf_token = get_csrf_token();
$is_running = is_subscribe_running();
?>

<style>
    .proxy-suite-box-title {
        padding: 12px 14px;
        border-bottom: 1px solid #eeeeee;
        font-size: 14px;
        font-weight: 600;
    }

    .proxy-suite-box-body {
        padding: 14px;
    }

    .proxy-suite-log {
        max-width: none;
        font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
        font-size: 14px;
        line-height: 1.25;
        white-space: pre;
        overflow-wrap: normal;
        resize: vertical;
    }

    .proxy-suite-toolbar .btn {
        margin-right: 4px;
        margin-bottom: 0;
    }
</style>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <?php if (!empty($messages)): ?>
                <div class="col-xs-12">
                    <?php foreach ($messages as $message): ?>
                        <div class="alert alert-<?= h($message['type']); ?>"><?= h($message['text']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-link text-muted"></i> <?= h(sub_t('Subscription Management')); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token); ?>">
                        <div class="form-group">
                            <label for="subscribe_url"><?= h(sub_t('Subscription URL')); ?></label>
                            <input type="text" id="subscribe_url" name="subscribe_url" value="<?= h($current_url); ?>" class="form-control" placeholder="<?= h(sub_t('Enter HTTP or HTTPS subscription URL')); ?>" autocomplete="off">
                        </div>
                        <div class="proxy-suite-toolbar">
                            <button type="submit" name="save" value="1" class="btn btn-primary">
                                <i class="fa fa-save"></i> <?= h(sub_t('Save Settings')); ?>
                            </button>
                            <button type="submit" name="action" value="<?= h(SUBSCRIBE_ACTION); ?>" class="btn btn-success" onclick="return confirm(<?= sub_js('Confirm start subscription now?'); ?>);" <?= $is_running ? 'disabled="disabled"' : ''; ?>>
                                <i class="fa fa-refresh"></i> <?= h(sub_t('Start Subscription')); ?>
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-file-text text-muted"></i> <?= h(sub_t('Log Viewer')); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <form method="post" class="form-inline proxy-suite-toolbar">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token); ?>">
                            <button type="submit" name="action" value="<?= h(CLEAR_LOG_ACTION); ?>" class="btn btn-default" onclick="return confirm(<?= sub_js('Confirm clear log?'); ?>);">
                                <i class="fa fa-trash"></i> <?= h(sub_t('Clear Log')); ?>
                            </button>
                        </form>
                        <br>
                        <textarea readonly id="log_content" name="log_content" rows="20" class="form-control proxy-suite-log"><?= $log_content; ?></textarea>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>
<script>
const LOGS_ENDPOINT = <?= json_encode(LOGS_ENDPOINT, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

function refreshLogs() {
    fetch(LOGS_ENDPOINT, { cache: 'no-store' })
        .then(response => response.text())
        .then(logContent => {
            const logViewer = document.getElementById('log_content');
            if (!logViewer) {
                return;
            }
            logViewer.value = logContent;
            logViewer.scrollTop = logViewer.scrollHeight;
        })
        .catch(error => {
            const logViewer = document.getElementById('log_content');
            if (logViewer) {
                logViewer.value = '[' + <?= sub_js('Error'); ?> + '] ' + <?= sub_js('Failed to load logs: '); ?> + error.message;
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    refreshLogs();
    setInterval(refreshLogs, 5000);
});
</script>
<?php include("foot.inc"); ?>
