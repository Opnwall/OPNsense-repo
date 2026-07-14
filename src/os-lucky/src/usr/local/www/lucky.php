<?php
/*
 * lucky.php
 * Lucky for OPNsense.
 */

$allowautocomplete = true;
$pgtitle = array(gettext("Services"), gettext("Lucky"));
require_once("guiconfig.inc");

function lucky_config_value($name, $default) {
	$file = '/etc/rc.conf.d/lucky';
	if (!is_readable($file)) {
		return $default;
	}

	$contents = file_get_contents($file);
	if (preg_match('/^' . preg_quote($name, '/') . '="([^"]*)"/m', $contents, $matches)) {
		return $matches[1];
	}

	return $default;
}

function lucky_write_config($enabled, $conf_dir, $port) {
	$enabled_value = $enabled ? 'YES' : 'NO';
	$conf_dir = $conf_dir !== '' ? $conf_dir : '/usr/local/etc/lucky';
	$port = is_numeric($port) ? (int)$port : 16601;

	$contents = "";
	$contents .= "lucky_enable=\"{$enabled_value}\"\n";
	$contents .= "lucky_conf_dir=\"" . addcslashes($conf_dir, "\\\"$`") . "\"\n";
	$contents .= "lucky_http_port=\"{$port}\"\n";

	file_put_contents('/etc/rc.conf.d/lucky', $contents);
	if (!is_dir($conf_dir)) {
		mkdir($conf_dir, 0755, true);
	}
}

function lucky_is_running() {
	$pidfile = '/var/run/lucky.pid';
	if (!is_readable($pidfile)) {
		return false;
	}

	$pid = (int)trim(file_get_contents($pidfile));
	if ($pid <= 0) {
		return false;
	}

	exec('/bin/kill -0 ' . escapeshellarg((string)$pid) . ' >/dev/null 2>&1', $output, $status);
	return $status === 0;
}

function lucky_action($action) {
	$allowed = array('start', 'stop', 'restart');
	if (!in_array($action, $allowed, true)) {
		return;
	}
	exec('/usr/local/sbin/configctl lucky ' . escapeshellarg($action) . ' >/dev/null 2>&1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['save'])) {
		lucky_write_config(isset($_POST['enable']), $_POST['conf_dir'] ?? '', $_POST['web_port'] ?? '16601');
		lucky_action(isset($_POST['enable']) ? 'restart' : 'stop');
	} elseif (isset($_POST['start'])) {
		lucky_action('start');
	} elseif (isset($_POST['stop'])) {
		lucky_action('stop');
	}
	header("Location: lucky.php");
	exit;
}

$enabled = lucky_config_value('lucky_enable', 'YES') !== 'NO';
$conf_dir = lucky_config_value('lucky_conf_dir', '/usr/local/etc/lucky');
$web_port = (int)lucky_config_value('lucky_http_port', '16601');
$running = lucky_is_running();
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/:\d+$/', '', $host);
$lucky_url = "http://{$host}:{$web_port}/";

include("head.inc");
include("fbegin.inc");
?>

<style>
.lucky-panel .panel-heading,
.lucky-section-title {
	background: #3f3f3f;
	border-color: #3f3f3f;
	color: #fff;
	font-weight: 700;
	padding: 7px 14px;
}
.lucky-status {
	align-items: center;
	display: inline-flex;
	height: 24px;
	justify-content: center;
	line-height: 1;
	min-width: 72px;
	padding: 0 10px;
	text-align: center;
	vertical-align: middle;
}
.lucky-service-table {
	margin-bottom: 0;
}
.lucky-service-table td {
	padding-left: 14px !important;
	vertical-align: middle !important;
}
.lucky-service-table td:first-child {
	font-weight: 700;
	width: 140px;
}
.lucky-actions .btn {
	margin-right: 5px;
}
</style>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">
			<section class="col-xs-12">
				<div class="panel panel-default lucky-panel">
					<div class="lucky-section-title"><?=gettext("General Settings")?></div>
					<table class="table table-striped table-condensed lucky-service-table">
						<tr>
							<td><?=gettext("Service Status")?></td>
							<td>
								<span class="lucky-status label <?= $running ? 'label-success' : 'label-default' ?>">
									<?= $running ? gettext("Running") : gettext("Stopped") ?>
								</span>
							</td>
						</tr>
						<tr>
							<td><?=gettext("Link Address")?></td>
							<td><a href="<?=htmlspecialchars($lucky_url)?>" target="_blank" rel="noopener"><?=htmlspecialchars($lucky_url)?></a></td>
						</tr>
						<tr>
							<td><?=gettext("Service Control")?></td>
							<td>
								<form method="post" class="form-inline lucky-actions">
									<button class="btn btn-success btn-sm" type="submit" name="start" <?=$running ? 'disabled' : ''?>><?=gettext("Start")?></button>
									<button class="btn btn-danger btn-sm" type="submit" name="stop" <?=$running ? '' : 'disabled'?>><?=gettext("Stop")?></button>
								</form>
							</td>
						</tr>
					</table>
				</div>

				<div class="panel panel-default">
					<div class="panel-heading"><?=gettext("Advanced Settings")?></div>
					<div class="panel-body">
						<form method="post" class="form-horizontal">
							<div class="form-group">
								<label class="col-sm-2 control-label"><?=gettext("Enable")?></label>
								<div class="col-sm-10">
									<input type="checkbox" name="enable" value="yes" <?=$enabled ? 'checked' : ''?>>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-2 control-label"><?=gettext("Config")?></label>
								<div class="col-sm-10">
									<input class="form-control" name="conf_dir" value="<?=htmlspecialchars($conf_dir)?>">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-2 control-label"><?=gettext("Port")?></label>
								<div class="col-sm-10">
									<input class="form-control" name="web_port" value="<?=htmlspecialchars((string)$web_port)?>">
								</div>
							</div>
							<div class="form-group">
								<div class="col-sm-offset-2 col-sm-10">
									<button class="btn btn-primary" type="submit" name="save"><?=gettext("Save")?></button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
