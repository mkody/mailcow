<?php
require_once("inc/header.inc.php");
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
	<h2><?=sprintf($lang['start']['dashboard'], $_SERVER['SERVER_NAME']);?></h2>
	<div class="row">
		<div class="col-xs-6">
			<p><a href="/SOGo/" role="button" class="btn btn-success btn-lg btn-block"><?=$lang['start']['start_sogo'];?></a></p>
			<p class="lead"><?=$lang['start']['start_sogo_description'];?></p>
			<p><?=$lang['start']['start_sogo_detail'];?></p>
		</div>
		<div class="col-xs-6">
			<p><a href="/login" role="button" class="btn btn-info btn-lg btn-block"><?=$lang['start']['mailcow_panel'];?></a></p>
			<p class="lead"><?=$lang['start']['mailcow_panel_description'];?></p>
			<p><?=$lang['start']['mailcow_panel_detail'];?></p>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
		<hr>
		<h4><?=$lang['start']['recommended_config'];?></h4>
			<div class="panel panel-default">
			<div class="panel-heading">
				<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
				<?=$lang['start']['imap_smtp_server'];?>
				<span class="badge pull-right"><?=$lang['start']['imap_smtp_server_badge'];?></span>
			</div>
				<div class="panel-body">
				<p><?=sprintf($lang['start']['imap_smtp_server_description'], "https://www.mozilla.org/thunderbird/");?></p>
				<div class="table-responsive">
				<table class="table table-striped table-hover">
					<thead>
					<tr>
						<th><?=$lang['start']['service'];?></th>
						<th><?=$lang['start']['encryption'];?></th>
						<th><?=$lang['start']['hostname'];?></th>
						<th><?=$lang['start']['port'];?></th>
					</tr>
					</thead>
					<tbody>
						<tr>
							<td>IMAP</td>
							<td>STARTTLS</td>
							<td><?php echo $MYHOSTNAME; ?></td>
							<td>143</td>
						</tr>
						<tr>
							<td>IMAPS</td>
							<td>SSL</td>
							<td><?php echo $MYHOSTNAME; ?></td>
							<td>993</td>
						</tr>
						<tr>
							<td>POP3</td>
							<td>STARTTLS</td>
							<td><?php echo $MYHOSTNAME; ?></td>
							<td>110</td>
						</tr>
						<tr>
							<td>POP3S</td>
							<td>SSL</td>
							<td><?php echo $MYHOSTNAME; ?></td>
							<td>995</td>
						</tr>
						<tr>
							<td>SMTP</td>
							<td>STARTTLS</td>
							<td><?php echo $MYHOSTNAME; ?></td>
							<td>587</td>
						</tr>
						<tr>
							<td>SMTPS</td>
							<td>SSL</td>
							<td><?php echo $MYHOSTNAME; ?></td>
							<td>465</td>
						</tr>
					</tbody>
				</table>
				</div>
				<p><?=$lang['start']['imap_smtp_server_auth_info'];?></p>
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="panel panel-default">
				<div class="panel-heading"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span> <?=$lang['start']['managesieve'];?> <span class="badge pull-right"><?=$lang['start']['managesieve_badge'];?></span></div>
				<div class="panel-body">
					<p><?=sprintf($lang['start']['managesieve_description'], "https://github.com/thsmi/sieve/tree/master/nightly#builds", $_SERVER['SERVER_NAME']);?></p>
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="panel panel-default">
				<div class="panel-heading"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span> Cal- und CardDAV <span class="badge pull-right"><?=$lang['start']['cal_carddav_badge'];?></span></div>
				<div class="panel-body">
					<p><?=sprintf($lang['start']['cal_carddav_description'], $_SERVER['SERVER_NAME']);?></p>
				</div>
			</div>
		</div>
		<div class="clearfix"></div>
		<div class="col-md-12">
		<hr>
		<h4><?=$lang['start']['as_config'];?></h4>
			<div class="panel panel-default">
				<div class="panel-heading"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span> <?=$lang['start']['ms_as'];?> <span class="badge pull-right"><?=$lang['start']['ms_as_badge'];?></span></div>
				<div class="panel-body">
					<p><?=sprintf($lang['start']['ms_as_desc'], $_SERVER['SERVER_NAME'], $_SERVER['SERVER_NAME']);?></p>
				</div>
			</div>
		</div>
		<div class="clearfix"></div>
		<hr>
	</div>
	<p><?=$lang['start']['footer'];?></p>
</div> <!-- /container -->
<?php
require_once("inc/footer.inc.php");
?>
