<?php
require_once("inc/header.inc.php");
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
	$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
	$username = $_SESSION['mailcow_cc_username'];

?>
<div class="container">
<p class="lead"><?=$lang['user']['did_you_know'];?></p>

<div class="panel panel-default">
<div class="panel-heading"><?=$lang['user']['user_details'];?></div>
<div class="panel-body">
<form class="form-horizontal" role="form" method="post" autocomplete="off">
	<p><?=$lang['user']['user_change_fn'];?></p>
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_new_pass"><?=$lang['user']['new_password'];?></label>
		<div class="col-sm-5">
		<input type="password" class="form-control" pattern="(?=.*[A-Za-z])(?=.*[0-9])\w{6,}" name="user_new_pass" id="user_new_pass" autocomplete="off"">
		</div>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_new_pass2"><?=$lang['user']['new_password_repeat'];?></label>
		<div class="col-sm-5">
		<input type="password" class="form-control" pattern="(?=.*[A-Za-z])(?=.*[0-9])\w{6,}" name="user_new_pass2" id="user_new_pass2" autocomplete="off">
		<p class="help-block"><?=$lang['user']['new_password_description'];?></p>
		</div>
	</div>
	<hr>
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_old_pass"><?=$lang['user']['password_now'];?></label>
		<div class="col-sm-5">
		<input type="password" class="form-control" name="user_old_pass" id="user_old_pass" autocomplete="off" required>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9">
			<button type="submit" name="trigger_set_user_account" class="btn btn-success btn-default"><?=$lang['user']['save_changes'];?></button>
		</div>
	</div>
</form>
</div>
</div>

<div class="panel panel-default">
<div class="panel-heading"><?=$lang['user']['spam_aliases'];?></div>
<div class="panel-body">
<form class="form-horizontal" role="form" method="post">
<div class="table-responsive">
<table class="table table-striped" id="timelimitedaliases">
	<thead>
	<tr>
		<th><?=$lang['user']['alias'];?></th>
		<th><?=$lang['user']['alias_valid_until'];?></th>
		<th><?=$lang['user']['alias_time_left'];?></th>
	</tr>
	</thead>
	<tbody>
<?php
$result = mysqli_query($link, "SELECT address, 
	goto,
	UNIX_TIMESTAMP(validity) as validity,
	TIMEDIFF(validity, NOW()) as timeleft
	FROM spamalias WHERE goto='".$username."' AND validity >= NOW() ORDER BY timeleft ASC");
while ($row = mysqli_fetch_array($result)):
?>
		<tr>
		<td><?=$row['address'];?></td>
		<td><?=date($lang['user']['alias_full_date'], $row['validity']);?></td>
		<td><?php
		echo explode(':', $row['timeleft'])[0]."h, ";
		echo explode(':', $row['timeleft'])[1]."m, ";
		echo explode(':', $row['timeleft'])[2]."s";
		?></td>
		</tr>
<?php
endwhile;
?>
	</tbody>
</table>
</div>
<div class="form-group">
	<div class="col-sm-9">
		<select name="validity" title="<?=$lang['user']['alias_select_validity'];?>">
			<option value="1">1 <?=$lang['user']['hour'];?></option>
			<option value="6">6 <?=$lang['user']['hours'];?></option>
			<option value="24">1 <?=$lang['user']['day'];?></option>
			<option value="168">1 <?=$lang['user']['week'];?></option>
			<option value="672">4 <?=$lang['user']['weeks'];?></option>
		</select>
		<button type="submit" name="trigger_set_time_limited_aliases" value="generate" class="btn btn-success"><?=$lang['user']['alias_create_random'];?></button>
	</div>
</div>
<div class="form-group">
	<div class="col-sm-12">
		<button type="submit" name="trigger_set_time_limited_aliases" value="delete" class="btn btn-xs btn-danger"><?=$lang['user']['alias_remove_all'];?></button>
		<button type="submit" name="trigger_set_time_limited_aliases" value="extend" class="btn btn-xs btn-warning"><?=$lang['user']['alias_extend_all'];?></button>
	</div>
</div>
</form>
</div>
</div>

<div class="panel panel-default">
<div class="panel-heading"><?=$lang['user']['spamfilter_behavior'];?></div>
<div class="panel-body">
<form class="form-horizontal" role="form" method="post">
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
			<input name="score" id="score" type="text" /><br /><br />
			<small>
			<ul>
				<li><?=$lang['user']['spamfilter_green'];?></li>
				<li><?=$lang['user']['spamfilter_yellow'];?></li>
				<li><?=$lang['user']['spamfilter_red'];?></li>
			</ul>
			<p><i><?=$lang['user']['spamfilter_default_score'];?> 5:15</i></p>
			<p><?=$lang['user']['spamfilter_hint'];?></p>
			</small>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
			<button type="submit" id="trigger_set_spam_score" name="trigger_set_spam_score" class="btn btn-success"><?=$lang['user']['save_changes'];?></button>
		</div>
	</div>
</form>
</div>
</div>

</div> <!-- /container -->
<?php
}
else {
	header('Location: /login');
}
require_once("inc/footer.inc.php");
?>
