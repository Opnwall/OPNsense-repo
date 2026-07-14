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

define('ENV_FILE', '/usr/local/etc/mihomo/sub/env');
define('LOG_FILE', '/var/log/mihomo_sub.log');
define('SUB_SCRIPT', '/usr/local/etc/mihomo/sub/sub.sh');
define('CSRF_TOKEN_KEY', 'mihomo_sub_csrf_token');

$message = '';
$message_type = 'info';

function sub_lang()
{
    if (is_readable('/conf/config.xml')) {
        $config_xml = @file_get_contents('/conf/config.xml');
        if (is_string($config_xml) && preg_match('/<language>([^<]+)<\/language>/', $config_xml, $matches)) {
            return trim($matches[1]) === 'zh_CN' ? 'zh' : 'en';
        }
    }

    return 'en';
}


function sub_t($text)
{
    static $map = [
        'zh' => [
            'Log file does not exist; no need to clear it.' => '日志文件不存在，无需清空。',
            'Failed to clear the log. Make sure the log file is writable.' => '日志清空失败，请确保日志文件可写。',
            'Log cleared.' => '日志已清空！',
            'Failed to clear the log.' => '日志清空失败！',
            'Variable name cannot be empty.' => '变量名不能为空',
            'Directory does not exist: %s' => '目录不存在: %s',
            'Directory is not writable: %s' => '目录不可写: %s',
            'Failed to write temporary file: %s' => '临时文件写入失败: %s',
            'Failed to replace target file: %s' => '无法替换目标文件: %s',
            'Saved successfully.' => '保存成功',
            'Invalid subscription URL.' => '订阅地址格式无效！',
            'Only HTTP or HTTPS subscription URLs are allowed.' => '订阅地址仅允许使用 HTTP 或 HTTPS。',
            'CSRF validation failed. Please refresh the page and try again.' => 'CSRF 校验失败，请刷新页面后重试。',
            'Failed to save subscription URL: %s' => '保存订阅地址失败：%s',
            'Failed to save access secret: %s' => '保存访问密钥失败：%s',
            'Subscription URL saved.' => '订阅地址已保存。',
            'Access secret saved.' => '访问密钥已保存。',
            'Settings saved successfully.' => '设置已成功保存。',
            'Subscription task submitted; running in the background.' => '订阅任务已提交，开始后台执行。',
            'Subscription task completed successfully.' => '订阅任务执行成功。',
            'Subscription task failed with exit code: $rc.' => '订阅任务执行失败，退出码：$rc。',
            'Subscription task submitted. Check the log shortly.' => '订阅任务已提交，请稍候查看日志。',
            'Invalid action.' => '无效的操作！',
            'Subscription Management' => '订阅管理',
            'Subscription URL' => '订阅地址',
            'Enter HTTP or HTTPS subscription URL' => '输入 HTTP 或 HTTPS 订阅地址',
            'Access Secret' => '访问密钥',
            'Enter dashboard access secret' => '输入控制面板访问密钥',
            'Save Settings' => '保存设置',
            'Start Subscription' => '开始订阅',
            'Log Viewer' => '日志视图',
            'Clear Log' => '清空日志',
            'Error' => '错误',
            'Failed to load logs: ' => '无法加载日志：',
        ],
    ];
    $lang = sub_lang();
    return $map[$lang][$text] ?? $text;
}

function sub_js($text)
{
    return json_encode(sub_t($text), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

function generate_csrf_token()
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

function get_csrf_token()
{
    return $_SESSION[CSRF_TOKEN_KEY] ?? '';
}

function verify_csrf_token($token)
{
    $session_token = $_SESSION[CSRF_TOKEN_KEY] ?? '';
    return !empty($session_token) && is_string($token) && hash_equals($session_token, $token);
}

function execBackgroundCommand($command)
{
    $bg_command = "nohup sh -c " . escapeshellarg($command) . " >/dev/null 2>&1 &";
    exec($bg_command);
}

function log_message($message, $log_file = LOG_FILE)
{
    $time = date("Y-m-d H:i:s");
    $log_entry = "[{$time}] {$message}\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function clear_log($log_file = LOG_FILE)
{
    if (!file_exists($log_file)) {
        return [sub_t('Log file does not exist; no need to clear it.'), "warning"];
    }

    if (!is_writable($log_file)) {
        return [sub_t('Failed to clear the log. Make sure the log file is writable.'), "danger"];
    }

    return file_put_contents($log_file, '', LOCK_EX) !== false
        ? [sub_t('Log cleared.'), "success"]
        : [sub_t('Failed to clear the log.'), "danger"];
}

function escape_env_value($value)
{
    return str_replace("'", "'\"'\"'", $value);
}

function save_env_variable($key, $value, $env_file = ENV_FILE)
{
    if ($key === '') {
        return [false, sub_t('Variable name cannot be empty.')];
    }

    $dir = dirname($env_file);
    if (!is_dir($dir)) {
        return [false, sprintf(sub_t('Directory does not exist: %s'), $dir)];
    }

    if (!is_writable($dir)) {
        return [false, sprintf(sub_t('Directory is not writable: %s'), $dir)];
    }

    $lines = file_exists($env_file) ? file($env_file, FILE_IGNORE_NEW_LINES) : [];
    $new_lines = [];

    foreach ($lines as $line) {
        if (!preg_match('/^(export\s+)?' . preg_quote($key, '/') . '=/', $line)) {
            $new_lines[] = $line;
        }
    }

    $new_lines[] = "{$key}='" . escape_env_value($value) . "'";
    $tmp_file = $env_file . '.tmp';

    if (@file_put_contents($tmp_file, implode("\n", $new_lines) . "\n", LOCK_EX) === false) {
        @unlink($tmp_file);
        return [false, sprintf(sub_t('Failed to write temporary file: %s'), $tmp_file)];
    }

    if (!@rename($tmp_file, $env_file)) {
        @unlink($tmp_file);
        return [false, sprintf(sub_t('Failed to replace target file: %s'), $env_file)];
    }

    return [true, sub_t('Saved successfully.')];
}

function load_env_variables($env_file = ENV_FILE)
{
    $env_vars = [];

    if (!file_exists($env_file)) {
        return $env_vars;
    }

    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (preg_match("/^(?:export\s+)?([A-Za-z0-9_]+)='(.*)'$/", $line, $matches)) {
            $env_vars[$matches[1]] = str_replace("'\"'\"'", "'", $matches[2]);
        }
    }

    return $env_vars;
}

function validate_subscribe_url($url, &$error_message = '')
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = sub_t('Invalid subscription URL.');
        return false;
    }

    $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        $error_message = sub_t('Only HTTP or HTTPS subscription URLs are allowed.');
        return false;
    }

    return true;
}

function cleanup_temp_files()
{
    $files = [
        "/usr/local/etc/mihomo/sub/temp/mihomo_config.yaml",
        "/usr/local/etc/mihomo/sub/temp/proxies.txt",
        "/usr/local/etc/mihomo/sub/temp/config.yaml",
    ];

    foreach ($files as $file) {
        @unlink($file);
    }
}

function handle_form_submission()
{
    global $message, $message_type;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    $is_save = isset($_POST['save']);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = sub_t('CSRF validation failed. Please refresh the page and try again.');
        $message_type = 'danger';
        return;
    }

    if ($is_save) {
        $url = isset($_POST['subscribe_url']) ? trim((string)$_POST['subscribe_url']) : '';
        $secret = isset($_POST['mihomo_secret']) ? trim((string)$_POST['mihomo_secret']) : '';
        $url_error = '';

        if (!validate_subscribe_url($url, $url_error)) {
            $message = $url_error;
            $message_type = "danger";
            return;
        }

        list($url_saved, $url_msg) = save_env_variable('mihomo_URL', $url);
        list($secret_saved, $secret_msg) = save_env_variable('mihomo_secret', $secret);

        if (!$url_saved) {
            $message = sprintf(sub_t('Failed to save subscription URL: %s'), $url_msg);
            $message_type = "danger";
            return;
        }

        if (!$secret_saved) {
            $message = sprintf(sub_t('Failed to save access secret: %s'), $secret_msg);
            $message_type = "danger";
            return;
        }

        log_message(sub_t('Subscription URL saved.'));
        log_message(sub_t('Access secret saved.'));
        $message = sub_t('Settings saved successfully.');
        $message_type = "success";
        return;
    }

    if ($action === 'subscribe_now') {
        cleanup_temp_files();
        @file_put_contents(LOG_FILE, '', LOCK_EX);
        log_message(sub_t('Subscription task submitted; running in the background.'));

        $command =
            "/bin/sh " . escapeshellarg(SUB_SCRIPT) .
            " >> " . escapeshellarg(LOG_FILE) . " 2>&1; " .
            "rc=$?; " .
            "if [ \"\$rc\" -eq 0 ]; then " .
            "echo \"[$(date '+%Y-%m-%d %H:%M:%S')] " . sub_t('Subscription task completed successfully.') . "\" >> " . escapeshellarg(LOG_FILE) . "; " .
            "else " .
            "echo \"[$(date '+%Y-%m-%d %H:%M:%S')] " . sub_t('Subscription task failed with exit code: $rc.') . "\" >> " . escapeshellarg(LOG_FILE) . "; " .
            "fi";

        execBackgroundCommand($command);
        $message = sub_t('Subscription task submitted. Check the log shortly.');
        $message_type = "success";
        return;
    }

    if ($action === 'clear_log') {
        list($message, $message_type) = clear_log();
        return;
    }

    $message = sub_t('Invalid action.');
    $message_type = "danger";
}

generate_csrf_token();
handle_form_submission();

$env_vars = load_env_variables();
$current_url = $env_vars['mihomo_URL'] ?? '';
$current_secret = $env_vars['mihomo_secret'] ?? '';
$log_lines = file_exists(LOG_FILE) ? file(LOG_FILE) : [];
$log_tail = array_slice($log_lines, -200);
$log_content = htmlspecialchars(implode("", $log_tail), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf_token = get_csrf_token();
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
            <?php if (!empty($message)): ?>
                <div class="col-xs-12">
                    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <pre style="margin:0;border:0;background:transparent;padding:0;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-link text-muted"></i> <?= htmlspecialchars(sub_t('Subscription Management'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="subscribe_url"><?= htmlspecialchars(sub_t('Subscription URL'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
                            <input type="text" id="subscribe_url" name="subscribe_url" value="<?= htmlspecialchars($current_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="form-control" placeholder="<?= htmlspecialchars(sub_t('Enter HTTP or HTTPS subscription URL'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="mihomo_secret"><?= htmlspecialchars(sub_t('Access Secret'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
                            <input type="text" id="mihomo_secret" name="mihomo_secret" value="<?= htmlspecialchars($current_secret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="form-control" placeholder="<?= htmlspecialchars(sub_t('Enter dashboard access secret'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="proxy-suite-toolbar">
                            <button type="submit" name="save" value="1" class="btn btn-primary">
                                <i class="fa fa-save"></i> <?= htmlspecialchars(sub_t('Save Settings'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </button>
                            <button type="submit" name="action" value="subscribe_now" class="btn btn-success">
                                <i class="fa fa-refresh"></i> <?= htmlspecialchars(sub_t('Start Subscription'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-file-text text-muted"></i> <?= htmlspecialchars(sub_t('Log Viewer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <form method="post" class="form-inline proxy-suite-toolbar">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button type="submit" name="action" value="clear_log" class="btn btn-default">
                                <i class="fa fa-trash"></i> <?= htmlspecialchars(sub_t('Clear Log'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </button>
                        </form>
                        <br>
                        <textarea readonly id="log-viewer" rows="20" class="form-control proxy-suite-log"><?= $log_content; ?></textarea>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
function refreshLogs() {
    fetch('/mihomo_sub_log.php', { cache: 'no-store' })
        .then(response => response.text())
        .then(logContent => {
            const logViewer = document.getElementById('log-viewer');
            if (!logViewer) {
                return;
            }
            logViewer.value = logContent;
            logViewer.scrollTop = logViewer.scrollHeight;
        })
        .catch((error) => {
            const logViewer = document.getElementById('log-viewer');
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
