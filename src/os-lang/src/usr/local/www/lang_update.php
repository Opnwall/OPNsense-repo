<?php
/*
 * lang_update.php
 *
 * Chinese localization updater for OPNsense.
 */

require_once("guiconfig.inc");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const LANGTOOL_DOWNLOAD_URL = 'https://cloud.pfchina.org/index.php/s/CYFKMKGY7spK7mj/download?path=%2FOPNsense&files=lang.zip';
const LANGTOOL_TRUSTED_HOST = 'cloud.pfchina.org';
const LANGTOOL_INSTALL_ROOT = '/usr/local';
const LANGTOOL_EXPECTED_SHA256 = '';

function langtool_language()
{
    global $config;

    $candidates = array();
    if (is_readable('/conf/config.xml')) {
        $config_xml = @file_get_contents('/conf/config.xml');
        if (is_string($config_xml) && preg_match('/<language>([^<]+)<\/language>/', $config_xml, $matches)) {
            $candidates[] = $matches[1];
        }
    }
    if (isset($config['system']['language'])) {
        $candidates[] = $config['system']['language'];
    }

    foreach ($candidates as $language) {
        $language = strtolower(str_replace('-', '_', trim((string)$language)));
        if ($language !== '') {
            return $language;
        }
    }
    return 'en_us';
}

function langtool_family()
{
    $language = langtool_language();
    if (in_array($language, array('zh_cn', 'zh_hans', 'zh_hans_cn'), true) || strpos($language, 'zh_cn') === 0 || strpos($language, 'zh_hans') === 0) {
        return 'zh_Hans';
    }
    if (in_array($language, array('zh_tw', 'zh_hant', 'zh_hant_tw'), true) || strpos($language, 'zh_tw') === 0 || strpos($language, 'zh_hant') === 0) {
        return 'zh_Hant';
    }
    return 'en';
}

function langtool_t($key)
{
    static $messages = array(
        'Services' => array('en' => 'Services', 'zh_Hans' => '服务', 'zh_Hant' => '服務'),
        'Localization Tool' => array('en' => 'Localization Tool', 'zh_Hans' => '汉化工具', 'zh_Hant' => '漢化工具'),
        'Chinese localization package' => array('en' => 'Chinese localization package', 'zh_Hans' => '中文语言包', 'zh_Hant' => '中文語言包'),
        'Current OPNsense version' => array('en' => 'Current OPNsense version', 'zh_Hans' => '当前 OPNsense 版本', 'zh_Hant' => '目前 OPNsense 版本'),
        'Current language' => array('en' => 'Current language', 'zh_Hans' => '当前语言', 'zh_Hant' => '目前語言'),
        'Download source' => array('en' => 'Download source', 'zh_Hans' => '下载源', 'zh_Hant' => '下載來源'),
        'Update localization' => array('en' => 'Update localization', 'zh_Hans' => '更新汉化', 'zh_Hant' => '更新漢化'),
        'Updating...' => array('en' => 'Updating...', 'zh_Hans' => '正在更新...', 'zh_Hant' => '正在更新...'),
        'Status' => array('en' => 'Status', 'zh_Hans' => '执行状态', 'zh_Hant' => '執行狀態'),
        'Readme' => array('en' => 'Readme', 'zh_Hans' => '更新说明', 'zh_Hant' => '更新說明'),
        'Unknown' => array('en' => 'Unknown', 'zh_Hans' => '未知', 'zh_Hant' => '未知'),
        'Refresh the page after the WebGUI reloads.' => array('en' => 'Refresh the page after the WebGUI reloads.', 'zh_Hans' => 'WebGUI 重新加载后请刷新页面。', 'zh_Hant' => 'WebGUI 重新載入後請重新整理頁面。'),
        'The package is downloaded from the trusted pfchina.org source and installed under /usr/local.' => array('en' => 'The package is downloaded from the trusted pfchina.org source and installed under /usr/local.', 'zh_Hans' => '语言包会从受信任的 pfchina.org 下载源获取，并安装到 /usr/local。', 'zh_Hant' => '語言包會從受信任的 pfchina.org 下載來源取得，並安裝到 /usr/local。'),
        'Invalid form token, please refresh and try again.' => array('en' => 'Invalid form token, please refresh and try again.', 'zh_Hans' => '表单令牌无效，请刷新页面后重试。', 'zh_Hant' => '表單權杖無效，請重新整理頁面後再試。'),
        'Invalid download URL.' => array('en' => 'Invalid download URL.', 'zh_Hans' => '下载链接无效。', 'zh_Hant' => '下載連結無效。'),
        'Download URL must use HTTPS.' => array('en' => 'Download URL must use HTTPS.', 'zh_Hans' => '下载链接必须使用 HTTPS。', 'zh_Hant' => '下載連結必須使用 HTTPS。'),
        'Download host is not trusted' => array('en' => 'Download host is not trusted', 'zh_Hans' => '下载域名不在信任列表中', 'zh_Hant' => '下載網域不在信任清單中'),
        'Unable to create temporary directory.' => array('en' => 'Unable to create temporary directory.', 'zh_Hans' => '无法创建临时目录。', 'zh_Hant' => '無法建立暫存目錄。'),
        'Downloading package...' => array('en' => 'Downloading package...', 'zh_Hans' => '正在下载语言包...', 'zh_Hant' => '正在下載語言包...'),
        'Download completed.' => array('en' => 'Download completed.', 'zh_Hans' => '下载完成。', 'zh_Hant' => '下載完成。'),
        'Download failed.' => array('en' => 'Download failed.', 'zh_Hans' => '下载失败。', 'zh_Hant' => '下載失敗。'),
        'Checksum verification failed.' => array('en' => 'Checksum verification failed.', 'zh_Hans' => '压缩包校验失败。', 'zh_Hant' => '壓縮包校驗失敗。'),
        'Reading archive file list...' => array('en' => 'Reading archive file list...', 'zh_Hans' => '正在读取压缩包文件列表...', 'zh_Hant' => '正在讀取壓縮包檔案清單...'),
        'Archive is empty.' => array('en' => 'Archive is empty.', 'zh_Hans' => '压缩包为空。', 'zh_Hant' => '壓縮包為空。'),
        'Archive contains unsafe path' => array('en' => 'Archive contains unsafe path', 'zh_Hans' => '压缩包包含不安全路径', 'zh_Hant' => '壓縮包包含不安全路徑'),
        'Archive contains unsupported path' => array('en' => 'Archive contains unsupported path', 'zh_Hans' => '压缩包包含未允许的安装路径', 'zh_Hant' => '壓縮包包含未允許的安裝路徑'),
        'Extracting package...' => array('en' => 'Extracting package...', 'zh_Hans' => '正在解压语言包...', 'zh_Hant' => '正在解壓語言包...'),
        'Extraction failed.' => array('en' => 'Extraction failed.', 'zh_Hans' => '解压失败。', 'zh_Hant' => '解壓失敗。'),
        'Installing localization files...' => array('en' => 'Installing localization files...', 'zh_Hans' => '正在安装汉化文件...', 'zh_Hant' => '正在安裝漢化檔案...'),
        'Installation failed.' => array('en' => 'Installation failed.', 'zh_Hans' => '汉化文件安装失败。', 'zh_Hant' => '漢化檔案安裝失敗。'),
        'Localization completed.' => array('en' => 'Localization completed.', 'zh_Hans' => '汉化完成。', 'zh_Hant' => '漢化完成。'),
        'No readme.md found in the package.' => array('en' => 'No readme.md found in the package.', 'zh_Hans' => '语言包中未找到 readme.md。', 'zh_Hant' => '語言包中未找到 readme.md。'),
        'Cleaning temporary files...' => array('en' => 'Cleaning temporary files...', 'zh_Hans' => '正在清理临时文件...', 'zh_Hant' => '正在清理暫存檔案...'),
        'Reloading WebGUI...' => array('en' => 'Reloading WebGUI...', 'zh_Hans' => '正在重新加载 WebGUI...', 'zh_Hant' => '正在重新載入 WebGUI...'),
    );

    $family = langtool_family();
    return $messages[$key][$family] ?? $messages[$key]['en'] ?? $key;
}

function langtool_version()
{
    $version_file = '/usr/local/opnsense/version/core';
    if (!is_readable($version_file)) {
        return langtool_t('Unknown');
    }

    $content = @file_get_contents($version_file);
    if (!is_string($content)) {
        return langtool_t('Unknown');
    }

    $version_info = json_decode($content, true);
    if (is_array($version_info) && !empty($version_info['CORE_PKGVERSION'])) {
        return $version_info['CORE_PKGVERSION'];
    }
    if (preg_match('/"CORE_PKGVERSION":\s*"([^"]+)"/', $content, $matches)) {
        return $matches[1];
    }
    return langtool_t('Unknown');
}

function langtool_run($command)
{
    exec($command . ' 2>&1', $output, $status);
    return array('status' => $status, 'output' => (array)$output);
}

function langtool_log(&$log, $message)
{
    $log[] = $message;
}

function langtool_tmpdir(&$log)
{
    $tmp_base = tempnam(sys_get_temp_dir(), 'lang_update_');
    if ($tmp_base === false) {
        langtool_log($log, langtool_t('Unable to create temporary directory.'));
        return false;
    }
    unlink($tmp_base);
    if (!mkdir($tmp_base, 0700)) {
        langtool_log($log, langtool_t('Unable to create temporary directory.'));
        return false;
    }
    return $tmp_base;
}

function langtool_cleanup($tmp_dir, &$log)
{
    if (!is_string($tmp_dir) || $tmp_dir === '' || !is_dir($tmp_dir)) {
        return;
    }
    $real_tmp_dir = realpath($tmp_dir);
    $real_system_tmp = realpath(sys_get_temp_dir());
    if ($real_tmp_dir === false || $real_system_tmp === false || strpos($real_tmp_dir, $real_system_tmp . DIRECTORY_SEPARATOR . 'lang_update_') !== 0) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real_tmp_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($real_tmp_dir);
    langtool_log($log, langtool_t('Cleaning temporary files...'));
}

function langtool_validate_url($url, &$log)
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        langtool_log($log, langtool_t('Invalid download URL.'));
        return false;
    }
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https') {
        langtool_log($log, langtool_t('Download URL must use HTTPS.'));
        return false;
    }
    if (strcasecmp($parts['host'] ?? '', LANGTOOL_TRUSTED_HOST) !== 0) {
        langtool_log($log, langtool_t('Download host is not trusted') . ': ' . ($parts['host'] ?? ''));
        return false;
    }
    return true;
}

function langtool_validate_checksum($archive, &$log)
{
    if (LANGTOOL_EXPECTED_SHA256 === '') {
        return true;
    }
    $actual_hash = hash_file('sha256', $archive);
    if (!hash_equals(strtolower(LANGTOOL_EXPECTED_SHA256), strtolower((string)$actual_hash))) {
        langtool_log($log, langtool_t('Checksum verification failed.'));
        return false;
    }
    return true;
}

function langtool_archive_entries($archive, &$log)
{
    langtool_log($log, langtool_t('Reading archive file list...'));
    $result = langtool_run('unzip -Z1 ' . escapeshellarg($archive));
    if ($result['status'] !== 0) {
        $log = array_merge($log, $result['output']);
        return false;
    }
    $entries = array_values(array_filter(array_map('trim', $result['output']), function ($entry) {
        return $entry !== '';
    }));
    if (empty($entries)) {
        langtool_log($log, langtool_t('Archive is empty.'));
        return false;
    }
    return $entries;
}

function langtool_validate_entries($entries, &$log)
{
    $allowed_prefixes = array('bin/', 'etc/', 'include/', 'lib/', 'man/', 'opnsense/', 'sbin/', 'share/', 'www/');
    foreach ($entries as $entry) {
        if ($entry === 'readme.md' || substr($entry, -1) === '/') {
            continue;
        }
        if ($entry[0] === '/' || strpos($entry, '\\') !== false || preg_match('#(^|/)\.\.($|/)#', $entry)) {
            langtool_log($log, langtool_t('Archive contains unsafe path') . ': ' . $entry);
            return false;
        }
        $allowed = false;
        foreach ($allowed_prefixes as $prefix) {
            if (strpos($entry, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            langtool_log($log, langtool_t('Archive contains unsupported path') . ': ' . $entry);
            return false;
        }
    }
    return true;
}

function langtool_reload_webgui(&$log)
{
    langtool_run('rm -f /var/lib/php/tmp/opnsense_menu_cache.xml /var/lib/php/tmp/opnsense_acl_cache.json');
    langtool_run('nohup /usr/local/sbin/configctl webgui restart >/dev/null 2>&1 &');
    langtool_log($log, langtool_t('Reloading WebGUI...'));
}

function langtool_install(&$log, &$readme)
{
    if (!langtool_validate_url(LANGTOOL_DOWNLOAD_URL, $log)) {
        return false;
    }

    $tmp_dir = langtool_tmpdir($log);
    if ($tmp_dir === false) {
        return false;
    }

    $archive = $tmp_dir . '/lang.zip';
    $staging = $tmp_dir . '/staging';

    try {
        langtool_log($log, langtool_t('Downloading package...'));
        $download = langtool_run('fetch -o ' . escapeshellarg($archive) . ' ' . escapeshellarg(LANGTOOL_DOWNLOAD_URL));
        if ($download['status'] !== 0) {
            langtool_log($log, langtool_t('Download failed.'));
            $log = array_merge($log, $download['output']);
            return false;
        }
        langtool_log($log, langtool_t('Download completed.'));

        if (!langtool_validate_checksum($archive, $log)) {
            return false;
        }
        $entries = langtool_archive_entries($archive, $log);
        if ($entries === false || !langtool_validate_entries($entries, $log)) {
            return false;
        }

        mkdir($staging, 0700);
        langtool_log($log, langtool_t('Extracting package...'));
        $extract = langtool_run('unzip -q -o ' . escapeshellarg($archive) . ' -d ' . escapeshellarg($staging));
        if ($extract['status'] !== 0) {
            langtool_log($log, langtool_t('Extraction failed.'));
            $log = array_merge($log, $extract['output']);
            return false;
        }

        $readme_file = $staging . '/readme.md';
        $readme = is_readable($readme_file) ? file_get_contents($readme_file) : langtool_t('No readme.md found in the package.');
        @unlink($readme_file);

        langtool_log($log, langtool_t('Installing localization files...'));
        $install = langtool_run('tar -C ' . escapeshellarg($staging) . ' -cf - . | tar -C ' . escapeshellarg(LANGTOOL_INSTALL_ROOT) . ' -xf -');
        if ($install['status'] !== 0) {
            langtool_log($log, langtool_t('Installation failed.'));
            $log = array_merge($log, $install['output']);
            return false;
        }

        langtool_log($log, langtool_t('Localization completed.'));
        langtool_reload_webgui($log);
        return true;
    } finally {
        langtool_cleanup($tmp_dir, $log);
    }
}

if (empty($_SESSION['lang_update_csrf'])) {
    $_SESSION['lang_update_csrf'] = bin2hex(random_bytes(32));
}

$log_output = array();
$readme_output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['lang_update_csrf'], $csrf_token)) {
        langtool_log($log_output, langtool_t('Invalid form token, please refresh and try again.'));
    } else {
        langtool_install($log_output, $readme_output);
    }
}

$pgtitle = array(langtool_t('Services'), langtool_t('Localization Tool'));
$current_version = langtool_version();
$current_language = langtool_language();

include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>

<style>
    .langtool-panel {
        background: #fff;
        border: 1px solid #ddd;
        margin-bottom: 12px;
    }
    .langtool-panel-title {
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        color: #333;
        font-weight: 600;
        padding: 8px 10px;
    }
    .langtool-panel-body {
        padding: 12px 14px;
    }
    .langtool-meta {
        display: grid;
        grid-template-columns: 190px minmax(0, 1fr);
        gap: 6px 14px;
        margin-bottom: 12px;
    }
    .langtool-meta dt {
        color: #555;
        font-weight: 600;
    }
    .langtool-meta dd {
        margin: 0;
        word-break: break-all;
    }
    .langtool-output {
        background: #f7f7f7;
        border: 1px solid #ddd;
        color: #333;
        max-height: 280px;
        overflow: auto;
        padding: 10px;
        white-space: pre-wrap;
    }
    @media (max-width: 767px) {
        .langtool-meta {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <form method="post" id="langtool-form">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['lang_update_csrf'], ENT_QUOTES, 'UTF-8')?>">
                    <div class="langtool-panel">
                        <div class="langtool-panel-title"><?=htmlspecialchars(langtool_t('Chinese localization package'), ENT_QUOTES, 'UTF-8')?></div>
                        <div class="langtool-panel-body">
                            <dl class="langtool-meta">
                                <dt><?=htmlspecialchars(langtool_t('Current OPNsense version'), ENT_QUOTES, 'UTF-8')?></dt>
                                <dd><?=htmlspecialchars($current_version, ENT_QUOTES, 'UTF-8')?></dd>
                                <dt><?=htmlspecialchars(langtool_t('Current language'), ENT_QUOTES, 'UTF-8')?></dt>
                                <dd><?=htmlspecialchars($current_language, ENT_QUOTES, 'UTF-8')?></dd>
                                <dt><?=htmlspecialchars(langtool_t('Download source'), ENT_QUOTES, 'UTF-8')?></dt>
                                <dd><?=htmlspecialchars(LANGTOOL_TRUSTED_HOST, ENT_QUOTES, 'UTF-8')?></dd>
                            </dl>
                            <p class="text-muted"><?=htmlspecialchars(langtool_t('The package is downloaded from the trusted pfchina.org source and installed under /usr/local.'), ENT_QUOTES, 'UTF-8')?></p>
                            <button type="submit" class="btn btn-primary" id="langtool-submit">
                                <i class="fa fa-download"></i> <?=htmlspecialchars(langtool_t('Update localization'), ENT_QUOTES, 'UTF-8')?>
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($log_output)): ?>
                    <div class="langtool-panel">
                        <div class="langtool-panel-title"><?=htmlspecialchars(langtool_t('Status'), ENT_QUOTES, 'UTF-8')?></div>
                        <div class="langtool-panel-body">
                            <pre class="langtool-output"><?=htmlspecialchars(implode("\n", $log_output), ENT_QUOTES, 'UTF-8')?></pre>
                            <p class="text-muted"><?=htmlspecialchars(langtool_t('Refresh the page after the WebGUI reloads.'), ENT_QUOTES, 'UTF-8')?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($readme_output !== ''): ?>
                    <div class="langtool-panel">
                        <div class="langtool-panel-title"><?=htmlspecialchars(langtool_t('Readme'), ENT_QUOTES, 'UTF-8')?></div>
                        <div class="langtool-panel-body">
                            <pre class="langtool-output"><?=htmlspecialchars($readme_output, ENT_QUOTES, 'UTF-8')?></pre>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<script>
    (function () {
        var form = document.getElementById('langtool-form');
        var button = document.getElementById('langtool-submit');
        if (form && button) {
            form.addEventListener('submit', function () {
                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?=htmlspecialchars(langtool_t('Updating...'), ENT_QUOTES, 'UTF-8')?>';
            });
        }
    }());
</script>

<?php include("foot.inc"); ?>
