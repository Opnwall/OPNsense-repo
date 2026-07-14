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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SINGBOX_CONFIG_FILE = "/usr/local/etc/sing-box/config.json";
const SINGBOX_BINARY = "/usr/local/bin/sing-box";
const SINGBOX_LOG_FILE = "/var/log/sing-box.log";
const STATUS_ENDPOINT = "/sing-box.php?ajax=status";
const LOGS_ENDPOINT = "/sing-box_log.php";
const CSRF_TOKEN_KEY = "sing_box_service_csrf_token";

$message = "";
$message_type = "info";

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function proxy_lang()
{
    if (is_readable('/conf/config.xml')) {
        $config_xml = @file_get_contents('/conf/config.xml');
        if (is_string($config_xml) && preg_match('/<language>([^<]+)<\/language>/', $config_xml, $matches)) {
            return trim($matches[1]) === 'zh_CN' ? 'zh' : 'en';
        }
    }

    return 'en';
}


function proxy_t($text)
{
    static $map = [
        'zh' => [
            'Configuration file does not exist; cannot validate.' => '配置文件不存在，无法校验。',
            'sing-box binary is missing or not executable: %s' => 'sing-box 可执行文件不存在或不可执行：%s',
            'sing-box configuration validation passed.' => 'sing-box 配置校验通过。',
            'sing-box configuration validation failed.' => 'sing-box 配置校验失败。',
            'sing-box service started successfully.' => 'sing-box 服务启动成功！',
            'sing-box service stopped.' => 'sing-box 服务已停止！',
            'sing-box service restarted successfully.' => 'sing-box 服务重启成功！',
            'Failed to start sing-box service.' => 'sing-box 服务启动失败！',
            'Failed to stop sing-box service.' => 'sing-box 服务停止失败！',
            'Failed to restart sing-box service.' => 'sing-box 服务重启失败！',
            'Invalid action.' => '无效的操作！',
            'Log file does not exist; no need to clear it.' => '日志文件不存在，无需清空。',
            'Failed to clear the log. Make sure the log file is writable.' => '日志清空失败，请确保日志文件可写。',
            'Log cleared.' => '日志已清空！',
            'Failed to clear the log.' => '日志清空失败！',
            'Configuration content cannot be empty.' => '配置内容不能为空！',
            'JSON format error: %s' => 'JSON 格式错误：%s',
            'Configuration directory is not writable: %s' => '配置目录不可写：%s',
            'Failed to save configuration. Make sure the file is writable.' => '配置保存失败，请确保文件可写。',
            'Unable to create temporary file.' => '无法创建临时文件。',
            'Failed to write temporary configuration file.' => '写入临时配置文件失败。',
            'JSON format is valid, but sing-box configuration validation failed: %s' => 'JSON 格式正确，但 sing-box 配置校验失败：%s',
            'Failed to save configuration.' => '配置保存失败！',
            'Configuration saved successfully. %s' => '配置保存成功！%s',
            'CSRF validation failed. Please refresh the page and try again.' => 'CSRF 校验失败，请刷新页面后重试。',
            'Configuration file not found. Create or save a configuration first.' => '配置文件未找到，请先创建或保存配置。',
            'Service Status' => '服务状态',
            'Checking...' => '检查中...',
            'Reading service status' => '正在读取服务状态',
            'Service Control' => '服务控制',
            'Start' => '启动',
            'Stop' => '停止',
            'Restart' => '重启',
            'Configuration Management' => '配置管理',
            'Save Configuration' => '保存配置',
            'Log Viewer' => '日志视图',
            'Clear Log' => '清空日志',
            'sing-box is running' => 'sing-box 正在运行',
            'sing-box is stopped' => 'sing-box 已停止',
            'Service status is normal' => '服务状态正常',
            'Service is not running' => '服务未运行',
            'Status unknown' => '状态未知',
            'Unable to confirm service status' => '无法确认服务状态',
            'Status check failed' => '状态检查失败',
            'Please try again shortly' => '请稍候重试',
            'Error' => '错误',
            'Failed to load logs. Please check the network or server status.' => '无法加载日志，请检查网络或服务器状态。',
        ],
    ];
    $lang = proxy_lang();
    return $map[$lang][$text] ?? $text;
}

function proxy_js($text)
{
    return json_encode(proxy_t($text), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

function generateCsrfToken()
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

function getCsrfToken()
{
    return $_SESSION[CSRF_TOKEN_KEY] ?? '';
}

function verifyCsrfToken($token)
{
    $sessionToken = $_SESSION[CSRF_TOKEN_KEY] ?? '';
    return !empty($sessionToken) && is_string($token) && hash_equals($sessionToken, $token);
}

function execCommand($command)
{
    $output = [];
    $return_var = 0;
    exec($command . " 2>&1", $output, $return_var);
    return [$output, $return_var];
}

function readFileContent($file, $default = "")
{
    if (!file_exists($file)) {
        return $default;
    }

    $content = file_get_contents($file);
    return $content !== false ? $content : $default;
}

function validateJsonConfig($content)
{
    json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return json_last_error_msg();
    }
    return true;
}

function validateSingBoxConfig($binary, $file)
{
    if (!file_exists($file)) {
        return [false, proxy_t('Configuration file does not exist; cannot validate.')];
    }

    if (!file_exists($binary) || !is_executable($binary)) {
        return [false, sprintf(proxy_t('sing-box binary is missing or not executable: %s'), $binary)];
    }

    $command = escapeshellarg($binary) . " check -c " . escapeshellarg($file);
    list($output, $return_var) = execCommand($command);
    $result = trim(implode("\n", $output));

    if ($return_var === 0) {
        return [true, $result !== '' ? $result : proxy_t('sing-box configuration validation passed.')];
    }

    return [false, $result !== '' ? $result : proxy_t('sing-box configuration validation failed.')];
}

function handleServiceAction($action)
{
    $messages = [
        'start' => [proxy_t('sing-box service started successfully.'), proxy_t('Failed to start sing-box service.')],
        'stop' => [proxy_t('sing-box service stopped.'), proxy_t('Failed to stop sing-box service.')],
        'restart' => [proxy_t('sing-box service restarted successfully.'), proxy_t('Failed to restart sing-box service.')],
    ];

    if (!isset($messages[$action])) {
        return [false, proxy_t('Invalid action.')];
    }

    list($output, $return_var) = execCommand("service sing-box " . escapeshellarg($action));

    if ($return_var === 0) {
        return [true, $messages[$action][0]];
    }

    $detail = trim(implode("\n", $output));
    return [false, $messages[$action][1] . ($detail !== '' ? "\n" . $detail : '')];
}

function getServiceStatus()
{
    list($output, $return_var) = execCommand("service sing-box status");
    return $return_var === 0 ? "running" : "stopped";
}

function clearLogFile($file)
{
    if (!file_exists($file)) {
        return [proxy_t('Log file does not exist; no need to clear it.'), "warning"];
    }

    if (!is_writable($file)) {
        return [proxy_t('Failed to clear the log. Make sure the log file is writable.'), "danger"];
    }

    return file_put_contents($file, "", LOCK_EX) !== false
        ? [proxy_t('Log cleared.'), "success"]
        : [proxy_t('Failed to clear the log.'), "danger"];
}

function saveConfig($binary, $file, $content)
{
    if (trim($content) === '') {
        return [false, proxy_t('Configuration content cannot be empty.')];
    }

    $jsonValidationResult = validateJsonConfig($content);
    if ($jsonValidationResult !== true) {
        return [false, sprintf(proxy_t('JSON format error: %s'), $jsonValidationResult)];
    }

    $dir = dirname($file);
    if (!is_dir($dir) || !is_writable($dir)) {
        return [false, sprintf(proxy_t('Configuration directory is not writable: %s'), $dir)];
    }

    if (file_exists($file) && !is_writable($file)) {
        return [false, proxy_t('Failed to save configuration. Make sure the file is writable.')];
    }

    if (strlen($content) > 4 * 1024 * 1024) {
        return [false, proxy_t('Configuration content is larger than 4 MiB.')];
    }

    $temp_file = tempnam($dir, '.singbox_cfg_');
    if ($temp_file === false) {
        return [false, proxy_t('Unable to create temporary file.')];
    }

    try {
        if (file_put_contents($temp_file, $content, LOCK_EX) === false) {
            return [false, proxy_t('Failed to write temporary configuration file.')];
        }

        list($isValid, $checkMessage) = validateSingBoxConfig($binary, $temp_file);
        if (!$isValid) {
            return [false, sprintf(proxy_t('JSON format is valid, but sing-box configuration validation failed: %s'), $checkMessage)];
        }

        if (file_exists($file) && !@copy($file, $file . '.bak')) {
            return [false, proxy_t('Failed to create configuration backup.')];
        }

        @chmod($temp_file, 0644);
        if (!@rename($temp_file, $file)) {
            return [false, proxy_t('Failed to save configuration.')];
        }
        $temp_file = '';
        @chmod($file, 0644);

        return [true, sprintf(proxy_t('Configuration saved successfully. %s'), $checkMessage)];
    } finally {
        if ($temp_file !== '') {
            @unlink($temp_file);
        }
    }
}

generateCsrfToken();

if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => getServiceStatus()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $posted_config = (string)($_POST['config_content'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = proxy_t('CSRF validation failed. Please refresh the page and try again.');
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'save_config':
                list($saveSuccess, $saveMessage) = saveConfig(SINGBOX_BINARY, SINGBOX_CONFIG_FILE, $posted_config);
                $message = $saveMessage;
                $message_type = $saveSuccess ? 'success' : 'danger';
                break;
            case 'start':
            case 'stop':
            case 'restart':
                list($actionSuccess, $actionMessage) = handleServiceAction($action);
                $message = $actionSuccess ? '' : $actionMessage;
                $message_type = $actionSuccess ? 'info' : 'danger';
                break;
            case 'clear_log':
                list($message, $message_type) = clearLogFile(SINGBOX_LOG_FILE);
                break;
            default:
                $message = proxy_t('Invalid action.');
                $message_type = 'danger';
                break;
        }
    }
}

$config_raw_content = readFileContent(SINGBOX_CONFIG_FILE, '');
$csrf_token = getCsrfToken();
if ($config_raw_content === '' && !file_exists(SINGBOX_CONFIG_FILE) && $message === '') {
    $message = proxy_t('Configuration file not found. Create or save a configuration first.');
    $message_type = 'warning';
}

include("head.inc");
include("fbegin.inc");
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

    .proxy-suite-editor,
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

    .proxy-suite-status {
        margin-bottom: 0;
        padding: 12px 18px;
    }

    .proxy-suite-status .proxy-suite-status-title {
        font-weight: 600;
        margin-left: 8px;
        margin-right: 12px;
    }
</style>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <?php if ($message !== ''): ?>
                <div class="col-xs-12">
                    <div id="page-message" class="alert alert-<?= e($message_type); ?>">
                        <pre style="margin:0;border:0;background:transparent;padding:0;white-space:pre-wrap;word-break:break-word;"><?= e($message); ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-heartbeat text-muted"></i> <?= e(proxy_t('Service Status')); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <div id="sing-box-status" class="alert alert-warning proxy-suite-status">
                            <i class="fa fa-refresh fa-spin"></i>
                            <span class="proxy-suite-status-title"><?= e(proxy_t('Checking...')); ?></span>
                            <span><?= e(proxy_t('Reading service status')); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-sliders text-muted"></i> <?= e(proxy_t('Service Control')); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <form method="post" class="form-inline proxy-suite-toolbar">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token); ?>">
                            <button type="submit" name="action" value="start" class="btn btn-success">
                                <i class="fa fa-play"></i> <?= e(proxy_t('Start')); ?>
                            </button>
                            <button type="submit" name="action" value="stop" class="btn btn-danger">
                                <i class="fa fa-stop"></i> <?= e(proxy_t('Stop')); ?>
                            </button>
                            <button type="submit" name="action" value="restart" class="btn btn-warning">
                                <i class="fa fa-refresh"></i> <?= e(proxy_t('Restart')); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-file-text-o text-muted"></i> <?= e(proxy_t('Configuration Management')); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token); ?>">
                        <textarea id="config_content" name="config_content" rows="14" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off" class="form-control proxy-suite-editor"><?= e($config_raw_content); ?></textarea>
                        <br>
                        <button type="submit" name="action" value="save_config" class="btn btn-primary">
                            <i class="fa fa-save"></i> <?= e(proxy_t('Save Configuration')); ?>
                        </button>
                    </form>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-file-text text-muted"></i> <?= e(proxy_t('Log Viewer')); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <form method="post" class="form-inline proxy-suite-toolbar">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token); ?>">
                            <button type="submit" name="action" value="clear_log" class="btn btn-default">
                                <i class="fa fa-trash"></i> <?= e(proxy_t('Clear Log')); ?>
                            </button>
                        </form>
                        <br>
                        <textarea id="log-viewer" rows="14" class="form-control proxy-suite-log" readonly></textarea>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
    const STATUS_ENDPOINT = <?= json_encode(STATUS_ENDPOINT, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const LOGS_ENDPOINT = <?= json_encode(LOGS_ENDPOINT, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    function setStatus(state) {
        const statusElement = document.getElementById('sing-box-status');
        const statusMap = {
            running: {
                className: 'alert alert-success proxy-suite-status',
                html: '<i class="fa fa-check-circle text-success"></i> <span class="proxy-suite-status-title">' + <?= proxy_js('sing-box is running'); ?> + '</span><span>' + <?= proxy_js('Service status is normal'); ?> + '</span>'
            },
            stopped: {
                className: 'alert alert-danger proxy-suite-status',
                html: '<i class="fa fa-times-circle text-danger"></i> <span class="proxy-suite-status-title">' + <?= proxy_js('sing-box is stopped'); ?> + '</span><span>' + <?= proxy_js('Service is not running'); ?> + '</span>'
            },
            unknown: {
                className: 'alert alert-warning proxy-suite-status',
                html: '<i class="fa fa-exclamation-circle text-warning"></i> <span class="proxy-suite-status-title">' + <?= proxy_js('Status unknown'); ?> + '</span><span>' + <?= proxy_js('Unable to confirm service status'); ?> + '</span>'
            },
            error: {
                className: 'alert alert-danger proxy-suite-status',
                html: '<i class="fa fa-times-circle text-danger"></i> <span class="proxy-suite-status-title">' + <?= proxy_js('Status check failed'); ?> + '</span><span>' + <?= proxy_js('Please try again shortly'); ?> + '</span>'
            }
        };
        const nextState = statusMap[state] || statusMap.unknown;
        statusElement.className = nextState.className;
        statusElement.innerHTML = nextState.html;
    }

    function refreshStatus() {
        fetch(STATUS_ENDPOINT, { cache: 'no-store' })
            .then(response => response.json())
            .then(data => setStatus(data.status))
            .catch(() => setStatus('error'));
    }

    function refreshLogs() {
        fetch(LOGS_ENDPOINT, { cache: 'no-store' })
            .then(response => response.text())
            .then(logContent => {
                const logViewer = document.getElementById('log-viewer');
                const shouldStickToBottom =
                    logViewer.scrollTop + logViewer.clientHeight >= logViewer.scrollHeight - 20;

                logViewer.value = logContent;

                if (shouldStickToBottom) {
                    logViewer.scrollTop = logViewer.scrollHeight;
                }
            })
            .catch(() => {
                const logViewer = document.getElementById('log-viewer');
                logViewer.value = '[' + <?= proxy_js('Error'); ?> + '] ' + <?= proxy_js('Failed to load logs. Please check the network or server status.'); ?>;
                logViewer.scrollTop = logViewer.scrollHeight;
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshStatus();
        refreshLogs();
        setInterval(refreshStatus, 2000);
        setInterval(refreshLogs, 5000);
    });
</script>

<?php include("foot.inc"); ?>
