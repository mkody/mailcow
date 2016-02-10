<?php
function ssha256($password, $salt = "random") {
	if ($salt == "random") {
		$salt_str = bin2hex(openssl_random_pseudo_bytes(8));
	}
	else {
		$salt_str = $salt;
	}
	return "{SSHA256}".base64_encode(hash('sha256', $password.$salt_str, true).$salt_str);
}
function hasDomainAccess($link, $username, $role, $domain) {
	if (!filter_var($username, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		return false;
	}
	if (!is_valid_domain_name($domain)) {
		return false;
	}
	$qstring = "SELECT `domain` FROM `domain_admins`
		WHERE (
			`active`='1'
			AND `username`='".$username."'
			AND `domain`='".$domain."'
		)
		OR 'admin'='".$role."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0 && !empty($num_results)) {
		return true;
	}
	return false;
}
function check_login($link, $user, $pass, $set_user_account = "no") {
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $user))) {
		return false;
	}
	if (!strpos(shell_exec("file --mime-encoding /usr/bin/doveadm"), "binary")) {
		return false;
	}
	if ($set_user_account == "yes") {
		$result = mysqli_query($link, "SELECT `password` FROM `mailbox` WHERE active='1' AND username='$user'");
		while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
			$row = "'".$row[0]."'";
			exec("echo ".$pass." | doveadm pw -s ".$GLOBALS['PASS_SCHEME']." -t ".$row, $out, $return);
			if (isset($out[0]) && strpos($out[0], "verified") !== false && $return == "0") {
				unset($_SESSION['ldelay']);
				return "user";
			}
			else {
				return false;
			}
		}
	}
	$user = strtolower(trim($user));
	$pass = escapeshellarg($pass);
	$result = mysqli_query($link, "SELECT `password` FROM `admin`
		WHERE `superadmin`='1'
		AND `username`='".$user."'");
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$row = "'".$row[0]."'";
		exec("echo ".$pass." | doveadm pw -s ".$GLOBALS['PASS_SCHEME']." -t ".$row, $out, $return);
		if (isset($out[0]) && strpos($out[0], "verified") !== false && $return == "0") {
			unset($_SESSION['ldelay']);
			return "admin";
		}
	}
	$result = mysqli_query($link, "SELECT `password` FROM `admin`
		WHERE `superadmin`='0'
		AND `active`='1'
		AND `username`='".$user."'");
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$row = "'".$row[0]."'";
		exec("echo ".$pass." | doveadm pw -s ".$GLOBALS['PASS_SCHEME']." -t ".$row, $out, $return);
		if (isset($out[0]) && strpos($out[0], "verified") !== false && $return == "0") {
			unset($_SESSION['ldelay']);
			return "domainadmin";
		}
	}
	$result = mysqli_query($link, "SELECT `password` FROM `mailbox`
		WHERE `active`='1'
		AND `username`='".$user."'");
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$row = "'".$row[0]."'";
		exec("echo ".$pass." | doveadm pw -s ".$GLOBALS['PASS_SCHEME']." -t ".$row, $out, $return);
		if (isset($out[0]) && strpos($out[0], "verified") !== false && $return == "0") {
			unset($_SESSION['ldelay']);
			return "user";
		}
	}
	if (!isset($_SESSION['ldelay'])) {
		$_SESSION['ldelay'] = "0";
	}
	elseif (!isset($_SESSION['mailcow_cc_username'])) {
		$_SESSION['ldelay'] = $_SESSION['ldelay']+0.5;
	}
	sleep($_SESSION['ldelay']);
}
function formatBytes($size, $precision = 2) {
	if(!is_numeric($size)) {
		return "0";
	}
	$base = log($size, 1024);
	$suffixes = array(' Byte', ' KiB', ' MiB', ' GiB', ' TiB');
	if ($size == "0") {
		return "0";
	}
	return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}
function getMaxMessageSize() {
	return shell_exec('echo $(( $(/usr/sbin/postconf -h message_size_limit) / 1048576 ))');
}
function setMaxMessageSize($size) {
	global $lang;
	$size = filter_var(trim($size), FILTER_SANITIZE_NUMBER_FLOAT);
	if (!is_numeric($size)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['max_msg_size'])
		);
		return false;
	}
	$size = $size * 1048576;
	exec('sudo /usr/sbin/postconf -e message_size_limit='.$size, $return, $ec);
	if ($ec != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['exit_code_not_null'], $ec)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['msg_size_saved'])
	);
}
function opendkim_table($action, $which = "") {
	global $lang;
	switch ($action) {
		case "delete":
			if(!ctype_alnum(str_replace(array("_", "-", "."), "", $which))) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_not_found'])
				);
				break;
			}
			$selector	= explode("_", $which)[0];
			$domain		= explode("_", $which)[1];
			exec('sudo /usr/local/sbin/mc_dkim_ctrl del '.$selector.' '.$domain, $return, $ec);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_remove_failed'])
				);
				break;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['dkim_removed'])
			);
			break;
		case "add":
			$selector = explode("_", $which)[0];
			$domain = explode("_", $which)[1];
			if(!ctype_alnum($selector) || !ctype_alnum(str_replace(array("-", "."), "", $domain))) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
				);
				break;
			}
			exec('sudo /usr/local/sbin/mc_dkim_ctrl add '.$selector.' '.$domain, $return, $ec);
			if ($return != "0") {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['dkim_add_failed'])
				);
				break;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['dkim_added'])
			);
			break;
	}
}
function sys_info($what) {
	switch ($what) {
		case "ram":
			$return['total'] = filter_var(shell_exec("free -b | grep Mem | awk '{print $2}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['used'] = filter_var(shell_exec("free -b | grep Mem | awk '{print $3}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['free'] = filter_var(shell_exec("free -b | grep Mem | awk '{print $4}'"), FILTER_SANITIZE_NUMBER_FLOAT);
			$return['used_percent'] = round(shell_exec("free -b | grep Mem | awk '{print $3/$2 * 100.0}'"));
			return $return;
			break;
		case "vmail_percentage":
			$df = disk_free_space("/var/vmail");
			$dt = disk_total_space("/var/vmail");
			$du = $dt - $df;
			return sprintf('%.2f',($du / $dt) * 100);
			break;
		case "pflog":
			$pflog_content = file_get_contents($GLOBALS['PFLOG']);
			if (!file_exists($GLOBALS['PFLOG'])) {
				return "none";
			}
			else {
				return file_get_contents($GLOBALS['PFLOG']);
			}
			break;
		case "mailgraph":
			$imageurls = array("0-n", "1-n", "2-n", "3-n");
			$return = "";
			foreach ($imageurls as $image) {
				$image = 'http://localhost:81/mailgraph.cgi?'.$image;
				$imageData = base64_encode(file_get_contents($image));
				$return .='<img class="img-responsive" alt="'.$image.'" src="data:image/png;base64,'.$imageData.'" />';
			}
			return $return;
			break;
		case "mailq":
			return shell_exec("mailq");
			break;
	}
}
function postfix_reload() {
	exec('sudo /usr/sbin/postfix reload', $return, $ec);
	if ($ec != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['exit_code_not_null'], $ec)
		);
		return false;
	}
}
function pflog_renew() {
	exec('sudo /usr/local/sbin/mc_pflog_renew', $return, $ec);
	if ($ec != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['exit_code_not_null'], $ec)
		);
		return false;
	}
}
function mailbox_add_domain($link, $postarray) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$domain				= idn_to_ascii(mysqli_real_escape_string($link, strtolower(trim($postarray['domain']))));
	$description		= mysqli_real_escape_string($link, $postarray['description']);
	$aliases			= mysqli_real_escape_string($link, $postarray['aliases']);
	$mailboxes			= mysqli_real_escape_string($link, $postarray['mailboxes']);
	$maxquota			= mysqli_real_escape_string($link, $postarray['maxquota']);
	$quota				= mysqli_real_escape_string($link, $postarray['quota']);

	if ($maxquota > $quota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
		);
		return false;
	}

	isset($postarray['active']) ? $active = '1' : $active = '0';
	isset($postarray['relay_all_recipients']) ? $relay_all_recipients = '1' : $relay_all_recipients = '0';
	isset($postarray['backupmx']) ? $backupmx = '1' : $backupmx = '0';
	isset($postarray['relay_all_recipients']) ? $backupmx = '1' : true;

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	foreach (array($quota, $maxquota, $mailboxes, $aliases) as $data) {
		if (!is_numeric($data)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['object_is_not_numeric'], htmlspecialchars($data))
			);
			return false;
		}
	}
	$InsertDomain = "INSERT INTO `domain` (`domain`, `description`, `aliases`, `mailboxes`, `maxquota`, `quota`, `transport`, `backupmx`, `created`, `modified`, `active`, `relay_all_recipients`)
		VALUES ('".$domain."', '$description', '$aliases', '$mailboxes', '$maxquota', '$quota', 'virtual', '".$backupmx."', now(), now(), '".$active."', '".$relay_all_recipients."')";
	if (!mysqli_query($link, $InsertDomain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	exec('sudo /usr/local/sbin/mc_dkim_ctrl add default '.$domain);
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_added'], htmlspecialchars($domain))
	);
}
function mailbox_add_alias($link, $postarray) {
	global $lang;
	$addresses		= array_map('trim', explode(',', $postarray['address']));
	$gotos			= array_map('trim', explode(',', $postarray['goto']));
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if (empty($addresses)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_empty'])
		);
		return false;
	}
	if (empty($gotos)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['goto_empty'])
		);
		return false;
	}

	foreach ($addresses as $address) {
		// Should be faster than exploding
		$domain			= idn_to_ascii(substr(strstr($address, '@'), 1));
		$local_part		= strstr($address, '@', true);
		$address		= $local_part.'@'.$domain;
		if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['alias_invalid'])
			);
			return false;
		}

		if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}

		$qstring = "SELECT `address` FROM `alias`
			WHERE `address`='".$address."'";
		$qresult = mysqli_query($link, $qstring);
		$num_results = mysqli_num_rows($qresult);
		if ($num_results != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($address))
			);
			return false;
		}

		$qstring = "SELECT `address` FROM `spamalias`
			WHERE address='".$address."'";
		$qresult = mysqli_query($link, $qstring);
		$num_results = mysqli_num_rows($qresult);
		if ($num_results != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($address))
			);
			return false;
		}

		// Passing reference to alter array
		// This shouldn't impact perfomance too much since we usually don't paste many addresses
		foreach ($gotos as &$goto) {

			$goto_domain		= idn_to_ascii(substr(strstr($goto, '@'), 1));
			$goto_local_part	= strstr($goto, '@', true);
			$goto				= $goto_local_part.'@'.$goto_domain;

			if (!filter_var($goto, FILTER_VALIDATE_EMAIL) === true) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['goto_invalid'])
				);
				return false;
			}
			if ($goto == $address) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['alias_goto_identical'])
				);
				return false;
			}
		}
		$goto = implode(",", $gotos);
		if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
			$InsertAliasQuery = "INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
				VALUES ('@".$domain."', '".$goto."', '".$domain."', NOW(), NOW(), '".$active."')";
		}
		else {
			$InsertAliasQuery = "INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
				VALUES ('".$address."', '".$goto."', '".$domain."', NOW(), NOW(), '".$active."')";
		}
		if (!mysqli_query($link, $InsertAliasQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_added'])
	);
}
function mailbox_add_alias_domain($link, $postarray) {
	global $lang;
	$alias_domain	= mysqli_real_escape_string($link, strtolower(trim($postarray['alias_domain'])));
	$target_domain	= mysqli_real_escape_string($link, strtolower(trim($postarray['target_domain'])));
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}
	if (!is_valid_domain_name($target_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['target_domain_invalid'])
		);
		return false;
	}
	foreach (array($alias_domain, $target_domain) as $domain) {
		if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}
	}
	if ($alias_domain == $target_domain) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
		);
		return false;
	}
	
	$qstring = "SELECT `domain` FROM `domain`
		WHERE `domain`='".$target_domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['targetd_not_found'])
		);
		return false;
	}

	$qstring = "SELECT `domain` FROM `domain`
		WHERE `domain`='".$alias_domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_not_found'])
		);
		return false;
	}

	$qstring = "SELECT alias_domain FROM alias_domain
		WHERE `alias_domain`='".$alias_domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_exists'])
		);
		return false;
	}

	$InsertAliasDomainQuery = "INSERT INTO `alias_domain` (`alias_domain`, `target_domain`, `created`, `modified`, `active`)
		VALUES ('".$alias_domain."', '".$target_domain."', NOW(), NOW(), '".$active."')";
	if (!mysqli_query($link, $InsertAliasDomainQuery)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['aliasd_added'], htmlspecialchars($alias_domain))
	);
}
function mailbox_add_mailbox($link, $postarray) {
	global $lang;
	$password		= mysqli_real_escape_string($link, $postarray['password']);
	$password2		= mysqli_real_escape_string($link, $postarray['password2']);
	$domain			= mysqli_real_escape_string($link, strtolower(trim($postarray['domain'])));
	$local_part		= mysqli_real_escape_string($link, strtolower(trim($postarray['local_part'])));
	$name			= mysqli_real_escape_string($link, $postarray['name']);
	$quota_m		= mysqli_real_escape_string($link, $postarray['quota']);

	if (empty($name)) {
		$name = $local_part;
	}
	else {
		$name = utf8_decode($name);
	}

	isset($postarray['active']) ? $active = '1' : $active = '0';

	$quota_b		= ($quota_m * 1048576);
	$maildir		= $domain."/".$local_part."/";
	$username		= $local_part.'@'.$domain;

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	$DomainData = mysqli_fetch_assoc(mysqli_query($link,
		"SELECT `mailboxes`, `maxquota`, `quota` FROM `domain`
			WHERE domain='".$domain."'"));
	$MailboxData = mysqli_fetch_assoc(mysqli_query($link,
		"SELECT 
			count(*) as count,
			coalesce(round(sum(quota)/1048576), 0) as quota
				FROM `mailbox`
					WHERE domain='".$domain."'"));

	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	$qstring = "SELECT `local_part` FROM `mailbox` WHERE local_part='".$local_part."' and domain='".$domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
		);
		return false;
	}

	$qstring = "SELECT `address` FROM `alias` WHERE address='".$username."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($username))
		);
		return false;
	}

	$qstring = "SELECT `address` FROM `spamalias` WHERE address='".$username."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results != 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($username))
		);
		return false;
	}

	if (!ctype_alnum(str_replace(array('.', '-'), '', $local_part)) || empty ($local_part)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	if (!is_numeric($quota_m) || $quota_m == "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'])
		);
		return false;
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = ssha256($password);
	}
	else {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['password_empty'])
		);
		return false;
	}
	if ($MailboxData['count'] >= $DomainData['mailboxes']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['max_mailbox_exceeded'], $MailboxData['count'], $DomainData['mailboxes'])
		);
		return false;
	}
	
	$qstring = "SELECT `domain` FROM `domain`
		WHERE `domain`='".$domain."'";
	$qresult = mysqli_query($link, $qstring);
	$num_results = mysqli_num_rows($qresult);
	if ($num_results == 0) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_not_found'])
		);
		return false;
	}

	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $quota_m, $DomainData['maxquota'])
		);
		return false;
	}
	if (($MailboxData['quota'] + $quota_m) > $DomainData['quota']) {
		$quota_left_m = ($DomainData['quota'] - $MailboxData['quota']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
		);
		return false;
	}
	$create_user_array = array(
		"INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `created`, `modified`, `active`) 
			VALUES ('".$username."', '".$password_hashed."', '".$name."', '$maildir', '".$quota_b."', '$local_part', '".$domain."', now(), now(), '".$active."')",
		"INSERT INTO `quota2` (`username`, `bytes`, `messages`)
			VALUES ('".$username."', '0', '0')",
		"INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
			VALUES ('".$username."', '".$username."', '".$domain."', now(), now(), '".$active."')"
	);
	foreach ($create_user_array as $create_user) {
		if (!mysqli_query($link, $create_user)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_added'], htmlspecialchars($username))
	);
}
function mailbox_edit_alias($link, $postarray) {
	global $lang;
	$address = mysqli_real_escape_string($link, $postarray['address']);
	$domain = substr($address, strpos($address, '@')+1);

	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	if (empty($postarray['goto'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['goto_empty'])
		);
		return false;
	}
	$gotos = array_map('trim', explode(',', $postarray['goto']));
	foreach ($gotos as $goto) {
		if (!filter_var($goto, FILTER_VALIDATE_EMAIL)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' =>sprintf($lang['danger']['goto_invalid'])
			);
			return false;
		}
		if ($goto == $address) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['alias_goto_identical'])
			);
			return false;
		}
	}
	$goto = implode(",", $gotos);
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_invalid'])
		);
		return false;
	}
	$mystring = "UPDATE alias SET goto='".$goto."', active='".$active."' WHERE address='".$address."'";
	if (!mysqli_query($link, $mystring)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_modified'], htmlspecialchars($address))
	);
}
function mailbox_edit_domain($link, $postarray) {
	global $lang;
	$domain			= mysqli_real_escape_string($link, $postarray['domain']);
	$description	= mysqli_real_escape_string($link, $postarray['description']);
	// Numbers
	$aliases		= filter_var($postarray['aliases'], FILTER_SANITIZE_NUMBER_FLOAT);
	$mailboxes		= filter_var($postarray['mailboxes'], FILTER_SANITIZE_NUMBER_FLOAT);
	$maxquota		= filter_var($postarray['maxquota'], FILTER_SANITIZE_NUMBER_FLOAT);
	$quota			= filter_var($postarray['quota'], FILTER_SANITIZE_NUMBER_FLOAT);

	$MailboxData = mysqli_fetch_assoc(mysqli_query($link,
			"SELECT 
				count(*) AS count,
				max(coalesce(round(quota/1048576), 0)) AS maxquota,
				coalesce(round(sum(quota)/1048576), 0) AS quota
					FROM `mailbox`
						WHERE domain='".$domain."'"));
	$AliasData = mysqli_fetch_assoc(mysqli_query($link, 
			"SELECT count(*) AS count FROM `alias`
				WHERE domain='".$domain."'
				AND address NOT IN (
					SELECT `username` FROM `mailbox`
				)"));
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if ($_SESSION['mailcow_cc_role'] == "admin") {
		if ($maxquota > $quota) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
			);
			return false;
		}
		if ($MailboxData['maxquota'] > $maxquota) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['maxquota_in_use'], $MailboxData['maxquota'])
			);
			return false;
		}
		if ($MailboxData['quota'] > $quota) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['domain_quota_m_in_use'], $MailboxData['quota'])
			);
			return false;
		}
		if ($MailboxData['count'] > $mailboxes) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['mailboxes_in_use'], $MailboxData['count'])
			);
			return false;
		}
		if ($AliasData['count'] > $aliases) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['aliases_in_use'], $AliasData['count'])
			);
			return false;
		}
		isset($postarray['relay_all_recipients']) ? $relay_all_recipients = '1' : $relay_all_recipients = '0';
		isset($postarray['backupmx']) ? $backupmx = '1' : $backupmx = '0';
		isset($postarray['relay_all_recipients']) ? $backupmx = '1' : true;
	}
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	if ($_SESSION['mailcow_cc_role'] == "admin") {
		$UpdateDomainData = "UPDATE `domain` SET
			`modified`=now(),
			`relay_all_recipients`='".$relay_all_recipients."',
			`backupmx`='".$backupmx."',
			`active`='".$active."',
			`quota`='".$quota."',
			`maxquota`='".$maxquota."',
			`mailboxes`='".$mailboxes."',
			`aliases`='".$aliases."',
			`description`='".$description."'
				WHERE domain='".$domain."'";
	}
	else {
		$UpdateDomainData = "UPDATE `domain` SET
			`modified`=now(),
			`description`='".$description."'
				WHERE `domain`='".$domain."'";
	}
	if (!mysqli_query($link, $UpdateDomainData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars($domain))
	);
}
function mailbox_edit_domainadmin($link, $postarray) {
	global $lang;
	$username		= mysqli_real_escape_string($link, $postarray['username']);
	$password		= mysqli_real_escape_string($link, $postarray['password']);
	$password2		= mysqli_real_escape_string($link, $postarray['password2']);
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	foreach ($postarray['domain'] as $domain) {
		if (!is_valid_domain_name($domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['domain_invalid'])
			);
			return false;
		}
	};
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$DeleteDomainAdminsData = "DELETE FROM `domain_admins` WHERE username='".$username."'";
	if (!mysqli_query($link, $DeleteDomainAdminsData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	foreach ($postarray['domain'] as $domain) {
		$InsertDomainAdminsData = "INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
			VALUES ('".$username."', '".$domain."', now(), '".$active."')";
		if (!mysqli_query($link, $InsertDomainAdminsData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = ssha256($password);
		$UpdateAdminData = "UPDATE admin SET modified=now(), active='".$active."', password='".$password_hashed."' WHERE username='".$username."';";
	}
	else {
		$UpdateAdminData = "UPDATE admin SET modified=now(), active='".$active."' where username='".$username."'";
	}
	if (!mysqli_query($link, $UpdateAdminData)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_modified'], htmlspecialchars($username))
	);
}
function mailbox_edit_mailbox($link, $postarray) {
	global $lang;
	isset($postarray['active']) ? $active = '1' : $active = '0';
	$quota_m		= mysqli_real_escape_string($link, $postarray['quota']);
	$quota_b		= $quota_m*1048576;
	$username		= mysqli_real_escape_string($link, $postarray['username']);
	$name			= mysqli_real_escape_string($link, $postarray['name']);
	$password		= $postarray['password'];
	$MailboxData1	= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT `domain`
			FROM `mailbox`
				WHERE username='".$username."'"));
	$MailboxData2	= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT 
			coalesce(round(sum(quota)/1048576), 0) as quota_m_now 
				FROM `mailbox`
					WHERE username='".$username."'"))
		OR die(mysqli_error($link));
	$MailboxData3	= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT 
			coalesce(round(sum(quota)/1048576), 0) as quota_m_in_use
				FROM `mailbox`
					WHERE domain='".$MailboxData1['domain']."'"))
		OR die(mysqli_error($link));
	$DomainData		= mysqli_fetch_assoc(mysqli_query($link,
		"SELECT `quota`, `maxquota`
			FROM `domain`
				WHERE domain='".$MailboxData1['domain']."'"))
		OR die(mysqli_error($link));

	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $MailboxData1['domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!is_numeric($quota_m) || $quota_m == "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'], htmlspecialchars($quota_m))
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $quota_m, $DomainData['maxquota'])
		);
		return false;
	}
	if (($MailboxData3['quota_m_in_use'] - $MailboxData2['quota_m_now'] + $quota_m) > $DomainData['quota']) {
		$quota_left_m = ($DomainData['quota'] - $MailboxData3['quota_m_in_use'] + $MailboxData2['quota_m_now']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
		);
		return false;
	}
	if (isset($postarray['sender_acl']) && is_array($postarray['sender_acl'])) {
		foreach ($postarray['sender_acl'] as $sender_acl) {
			if (!filter_var($sender_acl, FILTER_VALIDATE_EMAIL) && 
				!is_valid_domain_name(str_replace('@', '', $sender_acl))) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => sprintf($lang['danger']['sender_acl_invalid'])
					);
					return false;
			}
		}
		$DeleteSenderAclData = "DELETE FROM sender_acl
			WHERE logged_in_as='".$username."';";
		if (!mysqli_query($link, $DeleteSenderAclData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
		foreach ($postarray['sender_acl'] as $sender_acl) {
			$InsertSenderAclData = "INSERT INTO `sender_acl`
				(`send_as`, `logged_in_as`)
					VALUES ('".$sender_acl."', '".$username."')";
			if (!mysqli_query($link, $InsertSenderAclData)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
			return false;
			}
		}
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = ssha256($password);
		$UpdateMailboxArray = array(
			"UPDATE `alias` SET
				`modified`=NOW(),
				`active`='".$active."'
					WHERE `address`='".$username."'",
			"UPDATE mailbox SET
				`modified`=NOW(),
				`active`='".$active."',
				`password`='".$password_hashed."',
				`name`='".utf8_decode($name)."',
				`quota`='".$quota_b."'
					WHERE username='".$username."'"
		);
		foreach ($UpdateMailboxArray as $UpdateUserData) {
			if (!mysqli_query($link, $UpdateUserData)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
				return false;
			}
		}
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_modified'], $username)
		);
		return true;
	}
	$UpdateMailboxArray = array(
		"UPDATE `alias` SET
			`modified`=NOW(),
			`active`='".$active."'
				WHERE address='".$username."'",
		"UPDATE `mailbox` SET
			`modified`=NOW(),
			`active`='".$active."',
			`name`='".utf8_decode($name)."',
			`quota`='".$quota_b."'
				WHERE `username`='".$username."'"
	);
	foreach ($UpdateMailboxArray as $UpdateUserData) {
		if (!mysqli_query($link, $UpdateUserData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function mailbox_delete_domain($link, $domain) {
	global $lang;
	$domain 	= mysqli_real_escape_string($link, strtolower(trim($domain)));
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	$MailboxString = "SELECT `username` FROM `mailbox`
		WHERE domain='".$domain."';";
	$MailboxResult = mysqli_query($link, $MailboxString)
		OR die(mysqli_error($link));
	$MailboxCount = mysqli_num_rows($MailboxResult);
	if ($MailboxCount != 0 || !empty($MailboxCount)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_not_empty'])
		);
		return false;
	}
	$DeleteDomainArray = array(
		"DELETE FROM domain WHERE domain='".$domain."';",
		"DELETE FROM domain_admins WHERE domain='".$domain."';",
		"DELETE FROM alias WHERE domain='".$domain."';",
		"DELETE FROM sender_acl WHERE logged_in_as LIKE '%@".$domain."';",
		"DELETE FROM quota2 WHERE username LIKE '%@".$domain."';",
		"DELETE FROM alias_domain WHERE target_domain='".$domain."';",
		"DELETE FROM mailbox WHERE domain='".$domain."';",
		"DELETE FROM userpref WHERE username LIKE '%@".$domain."';",
		"DELETE FROM spamalias WHERE address LIKE '%@".$domain."';",
		"DELETE FROM fugluconfig WHERE scope LIKE '%@".$domain."';",
	);
	foreach ($DeleteDomainArray as $DeleteDomainData) {
		if (!mysqli_query($link, $DeleteDomainData)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	exec('sudo /usr/local/sbin/mc_clean_domain '.$domain, $return, $ec);
	if ($ec != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['exit_code_not_null'], $ec)
		);
		return false;
	}
	exec('sudo /usr/local/sbin/mc_dkim_ctrl del default '.$domain, $return, $ec);
	if ($ec != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['exit_code_not_null'], $ec)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_removed'], htmlspecialchars($domain))
	);
	return true;
}
function mailbox_delete_alias($link, $postarray) {
	global $lang;
	$address = mysqli_real_escape_string($link, $postarray['address']);
	$local_part = strstr($address, '@', true);
	$domain = substr(strrchr($address, "@"), 1);
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$mystring = "DELETE FROM alias WHERE address='".$address."' AND address NOT IN (SELECT `username` FROM `mailbox`)";
	if (!mysqli_query($link, $mystring)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_removed'], htmlspecialchars($address))
	);
}
function mailbox_delete_alias_domain($link, $postarray) {
	global $lang;
	$alias_domain = mysqli_real_escape_string($link, $postarray['alias_domain']);
	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$DeleteAliasDomain = "DELETE FROM `alias_domain`
		WHERE alias_domain='".$alias_domain."'";
	if (!mysqli_query($link, $DeleteAliasDomain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_domain_removed'], htmlspecialchars($alias_domain))
	);
}
function mailbox_delete_mailbox($link, $postarray) {
	global $lang;
	$username	= mysqli_real_escape_string($link, $postarray['username']);
	$domain		= substr(strrchr($username, "@"), 1);
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!hasDomainAccess($link, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$DeleteMailboxArray = array(
		"DELETE FROM `alias` 
			WHERE `goto`='".$username."'",
		"UPDATE `alias` SET
			`goto`=REPLACE(`goto`, ',".$username.",', ',')",
		"UPDATE `alias` SET
			`goto`=REPLACE(`goto`, ',".$username."', '')",
		"UPDATE `alias` SET
			`goto`=REPLACE(`goto`, '".$username.",', '')",
		"DELETE FROM `quota2`
			WHERE `username`='".$username."'",
		"DELETE FROM `mailbox`
			WHERE `username`='".$username."'",
		"DELETE FROM `sender_acl`
			WHERE `logged_in_as`='".$username."'",
		"DELETE FROM `fugluconfig`
			WHERE `scope`='".$username."'",
		"DELETE FROM `spamalias`
			WHERE `goto`='".$username."'",
		"DELETE FROM `userpref`
			WHERE `username`='".$username."'"
	);
	foreach ($DeleteMailboxArray as $DeleteMailbox) {
		if (!mysqli_query($link, $DeleteMailbox)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	exec('sudo /usr/local/sbin/mc_clean_mailbox '.$username, $return, $ec);
	if ($ec != "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['exit_code_not_null'], $ec)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_removed'], htmlspecialchars($username))
	);
}
function set_admin_account($link, $postarray) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$name			= mysqli_real_escape_string($link, $postarray['admin_user']);
	$name_now		= mysqli_real_escape_string($link, $postarray['admin_user_now']);
	$password		= mysqli_real_escape_string($link, $postarray['admin_pass']);
	$password2		= mysqli_real_escape_string($link, $postarray['admin_pass2']);

	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $name)) || empty ($name)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $name_now)) || empty ($name_now)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = ssha256($password);
		$UpdateAdmin = "UPDATE `admin` SET 
			`modified`=NOW(),
			`password`='".$password_hashed."',
			`username`='".$name."'
				WHERE `username`='".$name_now."'";
		if (!mysqli_query($link, $UpdateAdmin)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	else {
		$UpdateAdmin = "UPDATE `admin` SET 
			`modified`=NOW(),
			`username`='".$name."'
				WHERE `username`='".$name_now."'";
		if (!mysqli_query($link, $UpdateAdmin)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$UpdateDomainAdmins = "UPDATE `domain_admins` SET
		`username`='".$name."',
		`domain`='ALL'
			WHERE username='".$name_now."'";
	if (!mysqli_query($link, $UpdateDomainAdmins)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL error: '.mysqli_error($link)
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['admin_modified'])
	);
}
function set_time_limited_aliases($link, $postarray) {
	global $lang;
	$username = $_SESSION['mailcow_cc_username'];
	$domain = substr($username, strpos($username, '@'));
	if (($_SESSION['mailcow_cc_role'] != "user" &&
		$_SESSION['mailcow_cc_role'] != "domainadmin") || 
			empty($username) ||
			empty($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['access_denied'])
				);
				return false;
	}
	switch ($postarray["trigger_set_time_limited_aliases"]) {
		case "generate":
			if (!is_numeric($postarray["validity"]) || $postarray["validity"] > 672) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['validity_missing'])
				);
				return false;
			}
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < 16; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
			$SelectArray = array(
				"SELECT `username` FROM `mailbox`
					WHERE `username`='".$randomString.$domain."'",
				"SELECT `address` FROM `spamalias`
					WHERE `address`='".$randomString.$domain."'",
				"SELECT `address` FROM `alias`
					WHERE `address`='".$randomString.$domain."'",
			);
			foreach ($SelectArray as $SelectQuery) {
				$SelectCount = mysqli_query($link, $SelectQuery)
					OR die(mysqli_error($link));
				if (mysqli_num_rows($SelectCount) == 0) {
					continue;
				}
				else {
					$_SESSION['return'] = array(
						'type' => 'warning',
						'msg' => sprintf($lang['warning']['spam_alias_temp_error'])
					);
					return false;
				}
			}
			$SelectSpamalias = "SELECT `goto` FROM `spamalias`
				WHERE `goto`='".$username."'";
			$SpamaliasResult = mysqli_query($link, $SelectSpamalias)
				OR die(mysqli_error($link));
			$SpamaliasCount = mysqli_num_rows($SpamaliasResult);
			if ($SpamaliasCount == 20) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['spam_alias_max_exceeded'])
				);
				return false;
			}
			$InsertSpamalias = "INSERT INTO `spamalias`
				(`address`, `goto`, `validity`) VALUES
					('".$randomString.$domain."', '".$username."', DATE_ADD(NOW(), INTERVAL ".$postarray["validity"]." HOUR));";
			if (!mysqli_query($link, $InsertSpamalias)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "delete":
			$DeleteSpamalias = "DELETE FROM spamalias
				WHERE goto='".$username."'";
			if (!mysqli_query($link, $DeleteSpamalias)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "extend":
			$UpdateSpamalias = "UPDATE `spamalias` SET
				`validity`=DATE_ADD(validity, INTERVAL 1 HOUR)
					WHERE `goto`='".$username."' 
						AND `validity` >= NOW()";
			if (!mysqli_query($link, $UpdateSpamalias)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
	}
}
function set_user_account($link, $postarray) {
	global $lang;
	$username = $_SESSION['mailcow_cc_username'];
	$password_old = $postarray['user_old_pass'];
	if (isset($postarray['user_new_pass']) && isset($postarray['user_new_pass2'])) {
		$password_new = $postarray['user_new_pass'];
		$password_new2 = $postarray['user_new_pass2'];
	}
	if (!check_login($link, $username, $password_old, "yes") == "user") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if ($_SESSION['mailcow_cc_role'] != "user" &&
		$_SESSION['mailcow_cc_role'] != "domainadmin") {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
	}
	if (!empty($password_new2) && !empty($password_new)) {
		if ($password_new2 != $password_new) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		if (strlen($password_new) < "6" ||
			!preg_match('/[A-Za-z]/', $password_new) ||
			!preg_match('/[0-9]/', $password_new)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['password_complexity'])
				);
				return false;
		}
		$password_hashed = ssha256($password_new);
		$UpdateMailboxQuery = "UPDATE mailbox SET modified=NOW(), password='".$password_hashed."' WHERE username='".$username."';";
		if (!mysqli_query($link, $UpdateMailboxQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function add_domain_admin($link, $postarray) {
	global $lang;
	$username		= mysqli_real_escape_string($link, strtolower(trim($postarray['username'])));
	$password		= mysqli_real_escape_string($link, $postarray['password']);
	$password2		= mysqli_real_escape_string($link, $postarray['password2']);
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('.', '-'), '', $username)) || empty ($username)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$SelectQueryArray = array(
		"SELECT `username` FROM `mailbox`
			WHERE `username`='".$username."'",
		"SELECT `username` FROM `admin`
			WHERE `username`='".$username."'",
		"SELECT `username` FROM `domain_admins`
			WHERE `username`='".$username."'"
	);
	foreach ($SelectQueryArray as $SelectQuery) {
		$SelectResult = mysqli_query($link, $SelectQuery)
			OR die(mysqli_error($link));
		$SelectCount = mysqli_num_rows($SelectResult);
		if ($SelectCount != 0 || !empty($SelectCount)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
			);
			return false;
		}
	}
	if (!empty($password) && !empty($password2)) {
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = ssha256($password);
		$DeleteArray = array(
			"DELETE FROM `domain_admins`
				WHERE `username`='".$username."'",
			"DELETE FROM `admin`
				WHERE `username`='".$username."'",
		);
		foreach ($DeleteArray as $DeleteQuery) {
			if (!mysqli_query($link, $DeleteQuery)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
				return false;
			}
		}
		foreach ($postarray['domain'] as $domain) {
			$domain = mysqli_real_escape_string($link, $domain);
			if (!is_valid_domain_name($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['domain_invalid'])
				);
				return false;
			}
			$InsertDomainAdminQuery = "INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
				VALUES ('".$username."', '".$domain."', now(), '".$active."')";
			if (!mysqli_query($link, $InsertDomainAdminQuery)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL error: '.mysqli_error($link)
				);
				return false;
			}
		}
		$InsertAdminQuery = "INSERT INTO `admin` (`username`, `password`, `superadmin`, `created`, `modified`, `active`)
			VALUES ('".$username."', '".$password_hashed."', '0', now(), now(), '".$active."')";
		if (!mysqli_query($link, $InsertAdminQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	else {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['password_empty'])
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_added'], htmlspecialchars($username))
	);
}
function delete_domain_admin($link, $postarray) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$username = mysqli_real_escape_string($link, $postarray['username']);
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$DeleteDomainArray = array(
		"DELETE FROM `domain_admins` 
			WHERE `username`='".$username."'",
		"DELETE FROM `admin` 
			WHERE `username`='".$username."'"
	);
	foreach ($DeleteDomainArray as $DeleteDomainQuery) {
		if (!mysqli_query($link, $DeleteDomainQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_removed'], htmlspecialchars($username))
	);
}
function get_spam_score($link, $username) {
	$default		= "5, 15";
	$username		= mysqli_real_escape_string($link, $username);
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		return $default;
	}
	$SelectQuery = "SELECT * FROM `userpref`, `fugluconfig`
		WHERE `username`='".$username."'
		AND `scope`='".$username."'";
	$SelectResult = mysqli_query($link, $SelectQuery)
		OR die(mysqli_error($link));
	$SelectCount = mysqli_num_rows($SelectResult);
	if ($SelectCount == 0 || empty($SelectCount)) {
		return $default;
	}
	else {
		$FugluconfigData = mysqli_fetch_assoc(mysqli_query($link,
			"SELECT `value` FROM `fugluconfig`
				WHERE `option`='highspamlevel'
				AND `scope`='".$username."';"))
			OR die(mysqli_error($link));
		$UserprefData = mysqli_fetch_assoc(mysqli_query($link,
			"SELECT `value` FROM `userpref`
				WHERE `preference`='required_hits'
				AND `username`='".$username."';"))
			OR die(mysqli_error($link));
		return $UserprefData['value'].', '.$FugluconfigData['value'];
	}
}
function set_spam_score($link, $postarray) {
	global $lang;
	$username	= $_SESSION['mailcow_cc_username'];
	$username		= mysqli_real_escape_string($link, $username);
	$lowspamlevel	= explode(',', mysqli_real_escape_string($link, $postarray['score']))[0];
	$highspamlevel	= explode(',', mysqli_real_escape_string($link, $postarray['score']))[1];
	if (!ctype_alnum(str_replace(array('@', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!is_numeric($lowspamlevel) || !is_numeric($highspamlevel)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$DeleteSpamScoreArray = array(
		"DELETE FROM `fugluconfig` 
			WHERE `scope`='".$username."'
			AND (
				`option`='highspamlevel'
				OR `option`='lowspamlevel'
			)",
		"DELETE FROM `userpref`
			WHERE `username`='".$username."'
			AND preference='required_hits'"
	);
	foreach ($DeleteSpamScoreArray as $DeleteSpamScoreQuery) {
		if (!mysqli_query($link, $DeleteSpamScoreQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$InsertSpamScoreArray = array(
		"INSERT INTO `fugluconfig` (`scope`, `section`, `option`, `value`)
			VALUES ('".$username."', 'SAPlugin', 'highspamlevel', '".$highspamlevel."')",
		"INSERT INTO `userpref` (`username`, `preference` ,`value`)
			VALUES ('".$username."', 'required_hits', '".$lowspamlevel."')"
	);
	foreach ($InsertSpamScoreArray as $InsertSpamScoreQuery) {
		if (!mysqli_query($link, $InsertSpamScoreQuery)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL error: '.mysqli_error($link)
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function is_valid_domain_name($domain_name) {
	if (empty($domain_name)) {
		return false;
	}
	$domain_name = idn_to_ascii($domain_name);
	return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name)
		   && preg_match("/^.{1,253}$/", $domain_name)
		   && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name));
}
?>
