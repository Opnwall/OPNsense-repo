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

const MIHOMO_CSRF_TOKEN_KEY = "mihomo_service_csrf_token";

$config_file = "/usr/local/etc/mihomo/config.yaml";
$log_file = "/var/log/mihomo.log";
$message = "";
$message_type = "info";

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
            'Temporary configuration write failed; cannot validate.' => '临时配置写入失败，无法校验。',
            'Configuration validation passed.' => '配置校验通过。',
            "Configuration validation failed:\n%s" => "配置校验失败：\n%s",
            'Configuration content cannot be empty.' => '配置内容不能为空！',
            'Failed to save configuration: target directory is not writable.' => '配置保存失败，目标目录不可写。',
            'Configuration backup failed; save canceled.' => '配置备份失败，已取消保存。',
            'Failed to save configuration: temporary file write failed.' => '配置保存失败，临时文件写入失败。',
            'Failed to save configuration: cannot replace the active configuration file.' => '配置保存失败，无法替换正式配置文件。',
            'Configuration saved successfully.' => '配置保存成功！',
            'Log file does not exist; no need to clear it.' => '日志文件不存在，无需清空。',
            'Failed to clear the log. Make sure the log file is writable.' => '日志清空失败，请确保日志文件可写。',
            'Log cleared.' => '日志已清空！',
            'Failed to clear the log.' => '日志清空失败！',
            'Invalid action.' => '无效的操作！',
            'mihomo start command submitted. Refresh status shortly.' => 'mihomo 启动命令已提交，请稍候刷新状态。',
            'mihomo service stopped.' => 'mihomo 服务已停止！',
            'mihomo restart command submitted. Refresh status shortly.' => 'mihomo 重启命令已提交，请稍候刷新状态。',
            'Failed to start mihomo service.' => 'mihomo 服务启动失败！',
            'Failed to stop mihomo service.' => 'mihomo 服务停止失败！',
            'Failed to restart mihomo service.' => 'mihomo 服务重启失败！',
            'CSRF validation failed. Please refresh the page and try again.' => 'CSRF 校验失败，请刷新页面后重试。',
            'Configuration file not found.' => '配置文件未找到！',
            'Service Status' => '服务状态',
            'mihomo is running' => 'mihomo 正在运行',
            'mihomo is stopped' => 'mihomo 已停止',
            'Service status is normal' => '服务状态正常',
            'Service is not running' => '服务未运行',
            'Service Control' => '服务控制',
            'Start' => '启动',
            'Stop' => '停止',
            'Restart' => '重启',
            'Configuration Management' => '配置管理',
            'Save Configuration' => '保存配置',
            'Log Viewer' => '日志视图',
            'Clear Log' => '清空日志',
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
    if (empty($_SESSION[MIHOMO_CSRF_TOKEN_KEY])) {
        $_SESSION[MIHOMO_CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

function getCsrfToken()
{
    return $_SESSION[MIHOMO_CSRF_TOKEN_KEY] ?? '';
}

function verifyCsrfToken($token)
{
    $sessionToken = $_SESSION[MIHOMO_CSRF_TOKEN_KEY] ?? '';
    return !empty($sessionToken) && is_string($token) && hash_equals($sessionToken, $token);
}

function execCommand($command)
{
    $output = [];
    $return_var = 0;
    exec($command . " 2>&1", $output, $return_var);
    return [implode("\n", $output), $return_var];
}

function execBackgroundCommand($command)
{
    $bg_command = "nohup sh -c " . escapeshellarg($command) . " >/dev/null 2>&1 &";
    exec($bg_command);
}

function getServiceStatus()
{
    list($output, $return_var) = execCommand("/usr/sbin/service mihomo status");
    return $return_var === 0 ? "running" : "stopped";
}

function validateConfigContent($content, $current_file)
{
    $temp_file = $current_file . ".webtmp";
    $config_dir = dirname($current_file);

    if (file_put_contents($temp_file, $content, LOCK_EX) === false) {
        return [false, proxy_t('Temporary configuration write failed; cannot validate.')];
    }

    list($output, $return_var) = execCommand(
        "/usr/local/bin/mihomo -d " . escapeshellarg($config_dir) .
        " -t -f " . escapeshellarg($temp_file)
    );
    @unlink($temp_file);

    if ($return_var === 0) {
        return [true, proxy_t('Configuration validation passed.')];
    }

    return [false, sprintf(proxy_t("Configuration validation failed:\n%s"), $output)];
}

function saveConfig($file, $content)
{
    if (empty(trim($content))) {
        return [proxy_t('Configuration content cannot be empty.'), "danger"];
    }

    $dir = dirname($file);
    if (!is_dir($dir) || !is_writable($dir)) {
        return [proxy_t('Failed to save configuration: target directory is not writable.'), "danger"];
    }

    list($is_valid, $validate_message) = validateConfigContent($content, $file);
    if (!$is_valid) {
        return [$validate_message, "danger"];
    }

    $backup_file = $file . ".bak." . date("Ymd_His");
    $backup_created = false;
    if (file_exists($file)) {
        if (!@copy($file, $backup_file)) {
            return [proxy_t('Configuration backup failed; save canceled.'), "danger"];
        }
        $backup_created = true;
    }

    $temp_file = $file . ".tmp";
    if (file_put_contents($temp_file, $content, LOCK_EX) === false) {
        @unlink($temp_file);
        if ($backup_created) {
            @unlink($backup_file);
        }
        return [proxy_t('Failed to save configuration: temporary file write failed.'), "danger"];
    }

    if (!@rename($temp_file, $file)) {
        @unlink($temp_file);
        if ($backup_created) {
            @unlink($backup_file);
        }
        return [proxy_t('Failed to save configuration: cannot replace the active configuration file.'), "danger"];
    }

    return [proxy_t('Configuration saved successfully.'), "success"];
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

function handleServiceAction($action)
{
    $allowedActions = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowedActions, true)) {
        return [proxy_t('Invalid action.'), "danger"];
    }

    $messages = [
        'start' => [proxy_t('mihomo start command submitted. Refresh status shortly.'), proxy_t('Failed to start mihomo service.')],
        'stop' => [proxy_t('mihomo service stopped.'), proxy_t('Failed to stop mihomo service.')],
        'restart' => [proxy_t('mihomo restart command submitted. Refresh status shortly.'), proxy_t('Failed to restart mihomo service.')]
    ];

    if ($action === 'start' || $action === 'restart') {
        execBackgroundCommand("/usr/local/sbin/configctl mihomo " . $action);
        return [$messages[$action][0], "success"];
    }

    list($output, $return_var) = execCommand("/usr/local/sbin/configctl mihomo stop");

    if (stripos($output, 'not running') !== false) {
        return [proxy_t('mihomo service stopped.'), "success"];
    }

    if ($return_var === 0) {
        return [$messages[$action][0], "success"];
    }

    $detail = trim($output) !== "" ? "\n" . $output : "";
    return [$messages[$action][1] . $detail, "danger"];
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => getServiceStatus()]);
    exit;
}

generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = proxy_t('CSRF validation failed. Please refresh the page and try again.');
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'save_config':
                $config_content_raw = isset($_POST['config_content']) ? (string)$_POST['config_content'] : '';
                list($message, $message_type) = saveConfig($config_file, $config_content_raw);
                break;
            case 'clear_log':
                list($message, $message_type) = clearLogFile($log_file);
                break;
            case 'start':
            case 'stop':
            case 'restart':
                list($message, $message_type) = handleServiceAction($action);
                break;
            default:
                $message = proxy_t('Invalid action.');
                $message_type = "danger";
                break;
        }
    }
}

$csrf_token = getCsrfToken();
$config_content = file_exists($config_file)
    ? htmlspecialchars(file_get_contents($config_file), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : proxy_t('Configuration file not found.');

$service_status = getServiceStatus();
$show_message = !empty($message) && !in_array($message, [
    proxy_t('mihomo start command submitted. Refresh status shortly.'),
    proxy_t('mihomo service stopped.'),
    proxy_t('mihomo restart command submitted. Refresh status shortly.')
], true);

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
            <?php if ($show_message): ?>
                <div class="col-xs-12">
                    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <pre style="margin:0;border:0;background:transparent;padding:0;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-heartbeat text-muted"></i> <?= htmlspecialchars(proxy_t('Service Status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <div id="mihomo-status" class="alert <?= $service_status === 'running' ? 'alert-success' : 'alert-danger'; ?> proxy-suite-status">
                            <i class="fa <?= $service_status === 'running' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?>"></i>
                            <span class="proxy-suite-status-title"><?= htmlspecialchars($service_status === 'running' ? proxy_t('mihomo is running') : proxy_t('mihomo is stopped'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            <span><?= htmlspecialchars($service_status === 'running' ? proxy_t('Service status is normal') : proxy_t('Service is not running'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-sliders text-muted"></i> <?= htmlspecialchars(proxy_t('Service Control'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <form method="post" class="form-inline proxy-suite-toolbar">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button type="submit" name="action" value="start" class="btn btn-success">
                                <i class="fa fa-play"></i> <?= htmlspecialchars(proxy_t('Start'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </button>
                            <button type="submit" name="action" value="stop" class="btn btn-danger">
                                <i class="fa fa-stop"></i> <?= htmlspecialchars(proxy_t('Stop'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </button>
                            <button type="submit" name="action" value="restart" class="btn btn-warning">
                                <i class="fa fa-refresh"></i> <?= htmlspecialchars(proxy_t('Restart'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-file-text-o text-muted"></i> <?= htmlspecialchars(proxy_t('Configuration Management'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <textarea name="config_content" rows="14" spellcheck="false" class="form-control proxy-suite-editor"><?= $config_content; ?></textarea>
                        <br>
                        <button type="submit" name="action" value="save_config" class="btn btn-primary">
                            <i class="fa fa-save"></i> <?= htmlspecialchars(proxy_t('Save Configuration'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </button>
                    </form>
                    </div>
                </div>
            </section>

            <section class="col-xs-12">
                <div class="content-box">
                    <div class="proxy-suite-box-title">
                        <i class="fa fa-file-text text-muted"></i> <?= htmlspecialchars(proxy_t('Log Viewer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                    </div>
                    <div class="proxy-suite-box-body">
                        <form method="post" class="form-inline proxy-suite-toolbar">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button type="submit" name="action" value="clear_log" class="btn btn-default">
                                <i class="fa fa-trash"></i> <?= htmlspecialchars(proxy_t('Clear Log'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
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
    function renderStatus(status) {
        const statusElement = document.getElementById('mihomo-status');
        if (!statusElement) {
            return;
        }

        if (status === 'running') {
            statusElement.className = 'alert alert-success proxy-suite-status';
            statusElement.innerHTML = '<i class="fa fa-check-circle text-success"></i> <span class="proxy-suite-status-title">' + <?= proxy_js('mihomo is running'); ?> + '</span><span>' + <?= proxy_js('Service status is normal'); ?> + '</span>';
        } else {
            statusElement.className = 'alert alert-danger proxy-suite-status';
            statusElement.innerHTML = '<i class="fa fa-times-circle text-danger"></i> <span class="proxy-suite-status-title">' + <?= proxy_js('mihomo is stopped'); ?> + '</span><span>' + <?= proxy_js('Service is not running'); ?> + '</span>';
        }
    }

    function checkmihomoStatus() {
        fetch('/mihomo.php?ajax=status', { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                renderStatus(data.status === 'running' ? 'running' : 'stopped');
            })
            .catch(() => {
                renderStatus('stopped');
            });
    }

    function refreshLogs() {
        fetch('/mihomo_logs.php', { cache: 'no-store' })
            .then(response => response.text())
            .then(logContent => {
                const logViewer = document.getElementById('log-viewer');
                logViewer.value = logContent;
                logViewer.scrollTop = logViewer.scrollHeight;
            })
            .catch(() => {
                const logViewer = document.getElementById('log-viewer');
                logViewer.value = '[' + <?= proxy_js('Error'); ?> + '] ' + <?= proxy_js('Failed to load logs. Please check the network or server status.'); ?>;
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        checkmihomoStatus();
        refreshLogs();
        setInterval(checkmihomoStatus, 2000);
        setInterval(refreshLogs, 5000);
    });
</script>

<?php include("foot.inc"); ?>
