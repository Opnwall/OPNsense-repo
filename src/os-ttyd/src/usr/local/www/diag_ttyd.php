<?php
/*
 * diag_ttyd.php
 * ttyd for OPNsense.
 */

$allowautocomplete = true;
$pgtitle = array(gettext("System"), gettext("Diagnostics"), gettext("ttyd"));
require_once("guiconfig.inc");

function ttyd_config_value($name, $default) {
	$file = '/etc/rc.conf.d/ttyd';
	if (!is_readable($file)) {
		return $default;
	}

	$contents = file_get_contents($file);
	if (preg_match('/^' . preg_quote($name, '/') . '="([^"]*)"/m', $contents, $matches)) {
		return $matches[1];
	}

	return $default;
}

function ttyd_is_running() {
	$pidfile = '/var/run/ttyd.pid';
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

function ttyd_start() {
	mwexec('/usr/local/etc/rc.d/os-ttyd onestart');
}

$port = (int)ttyd_config_value('ttyd_port', '7681');
$listen = ttyd_config_value('ttyd_interface', '0.0.0.0');
$ssh_target = '127.0.0.1:22';

if (!ttyd_is_running()) {
	ttyd_start();
}

$running = ttyd_is_running();
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/:\d+$/', '', $host);
$terminal_path = '/ttyd/';
$terminal_url = "https://{$host}{$terminal_path}";

include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>

<style>
	.ttyd-status {
		background: #fff;
		margin-bottom: 12px;
		overflow-x: auto;
	}
	.ttyd-status-table {
		width: 100%;
		min-width: 760px;
		margin-bottom: 0;
		table-layout: fixed;
		border-collapse: collapse;
		font-family: inherit;
	}
	.ttyd-status-table th,
	.ttyd-status-table td {
		text-align: center;
		vertical-align: middle;
		border-bottom: 1px solid #ead6ce;
		padding: 7px 12px;
		line-height: 1.35;
	}
	.ttyd-status-table th {
		color: #333;
		font-size: 13px;
		font-weight: 600;
		background: #fafafa;
	}
	.ttyd-status-table td {
		font-size: 12px;
		color: #333;
	}
	.ttyd-status-table a {
		color: #d94f00;
		font-weight: 600;
	}
	.ttyd-status-cell {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.ttyd-service-state {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
	}
	.ttyd-service-state .fa {
		font-size: 10px;
	}
	.ttyd-terminal-panel {
		border: 1px solid #cfcfcf;
		background: #1f1f1f;
	}
	.ttyd-terminal-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 30px;
		padding: 0 12px;
		background: #f7f7f7;
		border-bottom: 1px solid #cfcfcf;
		color: #333;
		font-size: 12px;
		font-weight: 600;
	}
	.ttyd-terminal-head small {
		color: #777;
		font-size: 11px;
		font-weight: 400;
	}
	.ttyd-terminal {
		display: block;
		width: 100%;
		height: calc(100vh - 310px);
		min-height: 460px;
		border: 0;
		background: #1f1f1f;
	}
	@media (max-width: 991px) {
		.ttyd-terminal {
			height: 65vh;
		}
	}
</style>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">
			<section class="col-xs-12">
				<div class="ttyd-status">
					<table class="ttyd-status-table">
						<thead>
							<tr>
								<th><?=gettext("URL")?></th>
								<th><?=gettext("Listen address")?></th>
								<th><?=gettext("Target")?></th>
								<th><?=gettext("Status")?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="ttyd-status-cell">
									<a href="<?=htmlspecialchars($terminal_url)?>" target="_blank" rel="noopener"><?=htmlspecialchars($terminal_url)?></a>
								</td>
								<td class="ttyd-status-cell"><?=htmlspecialchars($listen)?></td>
								<td class="ttyd-status-cell"><?=htmlspecialchars($ssh_target)?></td>
								<td class="ttyd-status-cell">
									<span class="ttyd-service-state">
										<i class="fa fa-circle <?=($running ? 'text-success' : 'text-danger')?>"></i>
										<?=($running ? gettext("running") : gettext("stopped"))?>
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>
<?php if (!$running): ?>
		<div class="row">
			<section class="col-xs-12">
				<div class="alert alert-warning">
					<?=gettext("The terminal service could not be started. Make sure ttyd is installed and Secure Shell is enabled in System > Settings > Administration.")?>
				</div>
			</section>
		</div>
<?php else: ?>
		<div class="row">
			<section class="col-xs-12">
				<div class="ttyd-terminal-panel">
					<div class="ttyd-terminal-head">
						<span><i class="fa fa-terminal fa-fw"></i></span>
						<small><?=htmlspecialchars($ssh_target)?></small>
					</div>
					<iframe class="ttyd-terminal" src="<?=htmlspecialchars($terminal_path)?>"></iframe>
				</div>
			</section>
		</div>
<?php endif; ?>
	</div>
</section>

<?php include("foot.inc"); ?>
