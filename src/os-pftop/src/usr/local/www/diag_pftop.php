<?php
/*
 * diag_pftop.php
 *
 * pfTop WebGUI page for OPNsense.
 */

require_once("guiconfig.inc");

function pftop_language()
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
    if (function_exists('config_read_array')) {
        $candidates[] = config_read_array('system', 'language');
    }

    foreach ($candidates as $language) {
        $language = strtolower(str_replace('-', '_', trim((string)$language)));
        if ($language !== '') {
            return $language;
        }
    }
    return 'en_us';
}

function pftop_language_family()
{
    $language = pftop_language();
    if (in_array($language, array('zh_cn', 'zh_hans', 'zh_hans_cn'), true) || strpos($language, 'zh_cn') === 0 || strpos($language, 'zh_hans') === 0) {
        return 'zh_Hans';
    }
    if (in_array($language, array('zh_tw', 'zh_hant', 'zh_hant_tw'), true) || strpos($language, 'zh_tw') === 0 || strpos($language, 'zh_hant') === 0) {
        return 'zh_Hant';
    }
    return 'en';
}

function pftop_t($key)
{
    static $messages = array(
        'System' => array('en' => 'System', 'zh_Hans' => '系统', 'zh_Hant' => '系統'),
        'Diagnostics' => array('en' => 'Diagnostics', 'zh_Hans' => '诊断', 'zh_Hant' => '診斷'),
        'pfTop' => array('en' => 'pfTop', 'zh_Hans' => 'pfTop', 'zh_Hant' => 'pfTop'),
        'View' => array('en' => 'View', 'zh_Hans' => '查看', 'zh_Hant' => '檢視'),
        'Sort by' => array('en' => 'Sort by', 'zh_Hans' => '排序方式', 'zh_Hant' => '排序方式'),
        'Rows' => array('en' => 'Rows', 'zh_Hans' => '行数', 'zh_Hant' => '列數'),
        'Filter' => array('en' => 'Filter', 'zh_Hans' => '过滤器', 'zh_Hant' => '過濾器'),
        'Output' => array('en' => 'Output', 'zh_Hans' => '输出', 'zh_Hant' => '輸出'),
        'Loading...' => array('en' => 'Loading...', 'zh_Hans' => '加载中...', 'zh_Hant' => '載入中...'),
        'No data received.' => array('en' => 'No data received.', 'zh_Hans' => '未获取到数据。', 'zh_Hant' => '未取得資料。'),
        'pftop was not found on this system.' => array('en' => 'pftop was not found on this system.', 'zh_Hans' => '系统中未找到 pftop。', 'zh_Hant' => '系統中未找到 pftop。'),
        'The filter contains unsupported shell characters.' => array('en' => 'The filter contains unsupported shell characters.', 'zh_Hans' => '过滤器包含不支持的 shell 字符。', 'zh_Hant' => '過濾器包含不支援的 shell 字元。'),
        'pftop returned an error.' => array('en' => 'pftop returned an error.', 'zh_Hans' => 'pftop 返回错误。', 'zh_Hant' => 'pftop 傳回錯誤。'),
        'Default' => array('en' => 'Default', 'zh_Hans' => '默认', 'zh_Hant' => '預設'),
        'Label' => array('en' => 'Label', 'zh_Hans' => '标签', 'zh_Hant' => '標籤'),
        'Long' => array('en' => 'Long', 'zh_Hans' => '详细', 'zh_Hant' => '詳細'),
        'Queue' => array('en' => 'Queue', 'zh_Hans' => '队列', 'zh_Hant' => '佇列'),
        'Rules' => array('en' => 'Rules', 'zh_Hans' => '规则', 'zh_Hant' => '規則'),
        'Size' => array('en' => 'Size', 'zh_Hans' => '大小', 'zh_Hant' => '大小'),
        'Speed' => array('en' => 'Speed', 'zh_Hans' => '速率', 'zh_Hant' => '速率'),
        'State' => array('en' => 'State', 'zh_Hans' => '状态', 'zh_Hant' => '狀態'),
        'Time' => array('en' => 'Time', 'zh_Hans' => '时间', 'zh_Hant' => '時間'),
        'Age' => array('en' => 'Age', 'zh_Hans' => '连接时间', 'zh_Hant' => '連線時間'),
        'Bytes' => array('en' => 'Bytes', 'zh_Hans' => '字节', 'zh_Hant' => '位元組'),
        'Destination' => array('en' => 'Destination', 'zh_Hans' => '目标', 'zh_Hant' => '目的地'),
        'Destination port' => array('en' => 'Destination port', 'zh_Hans' => '目标端口', 'zh_Hant' => '目的連接埠'),
        'Expiration' => array('en' => 'Expiration', 'zh_Hans' => '过期时间', 'zh_Hant' => '到期時間'),
        'None' => array('en' => 'None', 'zh_Hans' => '无', 'zh_Hant' => '無'),
        'Packets' => array('en' => 'Packets', 'zh_Hans' => '数据包', 'zh_Hant' => '封包'),
        'Source port' => array('en' => 'Source port', 'zh_Hans' => '源端口', 'zh_Hant' => '來源連接埠'),
        'Source' => array('en' => 'Source', 'zh_Hans' => '源地址', 'zh_Hant' => '來源位址'),
        'All' => array('en' => 'All', 'zh_Hans' => '全部', 'zh_Hant' => '全部'),
    );

    $family = pftop_language_family();
    return $messages[$key][$family] ?? $messages[$key]['en'] ?? $key;
}

$allowautocomplete = true;
$pgtitle = array(pftop_t("System"), pftop_t("Diagnostics"), pftop_t("pfTop"));

function pftop_binary()
{
    foreach (array('/usr/local/sbin/pftop', '/usr/sbin/pftop', '/usr/bin/pftop') as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }
    return null;
}

function pftop_value($name, $allowed, $default)
{
    $value = $_REQUEST[$name] ?? $default;
    return in_array($value, $allowed, true) ? $value : $default;
}

function pftop_options($options, $selected)
{
    $html = '';
    foreach ($options as $value => $label) {
        if (is_int($value)) {
            $value = $label;
        }
        $safe_value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $safe_label = htmlspecialchars(pftop_t((string)$label), ENT_QUOTES, 'UTF-8');
        $html .= '<option value="' . $safe_value . '"' . ($selected === (string)$value ? ' selected' : '') . '>' . $safe_label . '</option>';
    }
    return $html;
}

function pftop_filter_argument($filter)
{
    $filter = trim((string)$filter);
    if ($filter === '') {
        return '';
    }
    if (strlen($filter) > 160 || preg_match('/[;&|`$<>(){}\\\\\r\n]/', $filter)) {
        return false;
    }
    return $filter;
}

$sort_options = array(
    'age' => 'Age',
    'bytes' => 'Bytes',
    'dest' => 'Destination',
    'dport' => 'Destination port',
    'exp' => 'Expiration',
    'none' => 'None',
    'pkt' => 'Packets',
    'sport' => 'Source port',
    'src' => 'Source',
);
$view_options = array(
    'default' => 'Default',
    'label' => 'Label',
    'long' => 'Long',
    'queue' => 'Queue',
    'rules' => 'Rules',
    'size' => 'Size',
    'speed' => 'Speed',
    'state' => 'State',
    'time' => 'Time',
);
$count_options = array('20', '30', '40', '55', '100', 'all' => 'All');

$sort = pftop_value('sort', array_keys($sort_options), 'bytes');
$view = pftop_value('view', array_keys($view_options), 'default');
$count = pftop_value('count', array('20', '30', '40', '55', '100', 'all'), '100');
$filter = pftop_filter_argument($_REQUEST['filter'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'true') {
    $pftop = pftop_binary();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div id="pftop-result">';

    if ($pftop === null) {
        echo '<div class="alert alert-danger">' . htmlspecialchars(pftop_t('pftop was not found on this system.'), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
        exit;
    }
    if ($filter === false) {
        echo '<div class="alert alert-danger">' . htmlspecialchars(pftop_t('The filter contains unsupported shell characters.'), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
        exit;
    }

    $args = array('-b', '-w', '135', '-v', $view);
    if ($filter !== '') {
        $args[] = '-f';
        $args[] = $filter;
    }
    if (in_array($view, array('queue', 'label', 'rules'), true) && $count === 'all') {
        $args[] = '-a';
    } elseif (in_array($view, array('queue', 'label', 'rules'), true)) {
        $args[] = $count;
    } else {
        $args[] = '-o';
        $args[] = $sort;
        $args[] = $count === 'all' ? '-a' : $count;
    }

    $command = escapeshellarg($pftop);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg((string)$arg);
    }
    exec($command . ' 2>&1', $output, $status);
    if ($status !== 0 && empty($output)) {
        $output[] = pftop_t('pftop returned an error.');
    }
    echo '<pre class="pftop-output">' . htmlspecialchars(implode("\n", $output), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</div>';
    exit;
}

include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>

<style>
    .pftop-panel {
        background: #fff;
        border: 1px solid #ddd;
        margin-bottom: 12px;
    }
    .pftop-panel-title {
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        color: #333;
        font-size: 13px;
        font-weight: 600;
        padding: 8px 10px;
    }
    .pftop-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 16px;
        align-items: flex-end;
        padding: 12px;
    }
    .pftop-control {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 150px;
    }
    .pftop-control label {
        font-size: 12px;
        font-weight: 600;
        margin: 0;
    }
    .pftop-control select,
    .pftop-control input {
        min-height: 30px;
    }
    .pftop-filter {
        flex: 1 1 320px;
    }
    .pftop-output-wrap {
        padding: 8px;
    }
    .pftop-output {
        height: calc(100vh - 330px);
        min-height: 300px;
        max-height: 600px;
        overflow: auto;
        margin: 0;
        border: 1px solid #ddd;
        background: #101010;
        color: #e6e6e6;
        font-size: 12px;
        line-height: 1.35;
        white-space: pre;
    }
</style>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <div class="pftop-panel">
                    <div class="pftop-panel-title"><?=htmlspecialchars(pftop_t("pfTop"), ENT_QUOTES, 'UTF-8')?></div>
                    <form id="pftop-form" class="pftop-controls">
                        <input type="hidden" name="ajax" value="true">
                        <div class="pftop-control">
                            <label for="view"><?=htmlspecialchars(pftop_t("View"), ENT_QUOTES, 'UTF-8')?></label>
                            <select class="form-control" name="view" id="view"><?=pftop_options($view_options, $view)?></select>
                        </div>
                        <div class="pftop-control">
                            <label for="sort"><?=htmlspecialchars(pftop_t("Sort by"), ENT_QUOTES, 'UTF-8')?></label>
                            <select class="form-control" name="sort" id="sort"><?=pftop_options($sort_options, $sort)?></select>
                        </div>
                        <div class="pftop-control">
                            <label for="count"><?=htmlspecialchars(pftop_t("Rows"), ENT_QUOTES, 'UTF-8')?></label>
                            <select class="form-control" name="count" id="count"><?=pftop_options($count_options, $count)?></select>
                        </div>
                        <div class="pftop-control pftop-filter">
                            <label for="filter"><?=htmlspecialchars(pftop_t("Filter"), ENT_QUOTES, 'UTF-8')?></label>
                            <input class="form-control" type="text" name="filter" id="filter" value="<?=htmlspecialchars((string)($_REQUEST['filter'] ?? ''), ENT_QUOTES, 'UTF-8')?>" placeholder="tcp, ip6, dst net 208.123.73.0/24">
                        </div>
                    </form>
                </div>
            </section>
        </div>
        <div class="row">
            <section class="col-xs-12">
                <div class="pftop-panel">
                    <div class="pftop-panel-title"><?=htmlspecialchars(pftop_t("Output"), ENT_QUOTES, 'UTF-8')?></div>
                    <div id="pftop-output" class="pftop-output-wrap">
                        <pre class="pftop-output"><?=htmlspecialchars(pftop_t("Loading..."), ENT_QUOTES, 'UTF-8')?></pre>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
    (function () {
        const form = document.getElementById('pftop-form');
        const output = document.getElementById('pftop-output');
        let timer = null;

        function refreshPftop() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const params = new URLSearchParams(new FormData(form));
                output.innerHTML = '<pre class="pftop-output"><?=htmlspecialchars(pftop_t("Loading..."), ENT_QUOTES, 'UTF-8')?></pre>';
                fetch('/diag_pftop.php?' + params.toString(), {credentials: 'same-origin'})
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const result = doc.querySelector('#pftop-result');
                        output.innerHTML = result ? result.innerHTML : '<pre class="pftop-output"><?=htmlspecialchars(pftop_t("No data received."), ENT_QUOTES, 'UTF-8')?></pre>';
                    })
                    .catch(function (error) {
                        output.innerHTML = '<div class="alert alert-danger">' + error.message + '</div>';
                    });
            }, 250);
        }

        form.querySelectorAll('select,input').forEach(function (element) {
            element.addEventListener('change', refreshPftop);
        });
        document.getElementById('filter').addEventListener('keyup', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                refreshPftop();
            }
        });

        refreshPftop();
        setInterval(refreshPftop, 5000);
    })();
</script>

<?php include("foot.inc"); ?>
