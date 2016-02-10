<?php
session_start();
if (isset($_POST["logout"])) {
	session_unset();
	session_destroy();
	session_write_close();
	setcookie(session_name(),'',0,'/');
}
require_once "inc/vars.inc.php";
$link = mysqli_connect($database_host, $database_user, $database_pass, $database_name);
if (!$link) {
	die("Connection error: " . mysqli_connect_error());
}
if (!isset($_SESSION['mailcow_locale'])) {
	$_SESSION['mailcow_locale'] = 'de';
}
if (isset($_GET['lang'])) {
	switch ($_GET['lang']) {
		case "de":
			$_SESSION['mailcow_locale'] = 'de';
		break;
		case "en":
			$_SESSION['mailcow_locale'] = 'en';
		break;
	}
}
require_once 'lang/lang.'.$_SESSION['mailcow_locale'].'.php';
require_once 'inc/functions.inc.php';
require_once 'inc/triggers.inc.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $MYHOSTNAME ?></title>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.6/lumen/bootstrap.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/css/bootstrap-select.min.css" rel="stylesheet" />
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/6.0.16/css/bootstrap-slider.min.css" rel="stylesheet" />
<style>
#slider1 .slider-selection {
	background: #FFD700;
}
#slider1 .slider-track-high {
	background: #FF4500;
}
#slider1 .slider-track-low {
	background: #66CD00;
}
</style>
<?php
if (basename($_SERVER['PHP_SELF']) == "mailbox.php" || basename($_SERVER['PHP_SELF']) == "manager"):
?>
<style>
.panel-heading div {
	margin-top: -18px;
	font-size: 15px;
}
.panel-heading div span {
	margin-left:5px;
}
.panel-body {
	display: none;
}
.clickable {
	cursor: pointer;
}
</style>
<?php
endif;
?>
</head>
<body style="padding-top:70px">
<nav class="navbar navbar-default navbar-fixed-top"  role="navigation">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/"><img style="margin-top:-5px;"src="/inc/xs_mailcow.png" /></a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<li><a style="color:#61B847;font-weight:bold;" href="/SOGo"><?=$lang['header']['start_sogo'];?></a></li>
				<?php
				if (isset($_SESSION['mailcow_locale'])) {
				?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><?=$lang['header']['locale'];?><span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
						<li <?=($_SESSION['mailcow_locale'] == 'de') ? 'class="active"' : ''?>><a href="?lang=de"><?=$lang['header']['locale_de'];?></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'en') ? 'class="active"' : ''?>><a href="?lang=en"><?=$lang['header']['locale_en'];?></a></li>
					</ul>
				</li>
				<?php
				}
				if (isset($_SESSION['mailcow_cc_role'])) {
				?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><?=$lang['header']['mailcow_settings'];?><span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
					<?php
						if (isset($_SESSION['mailcow_cc_role'])) {
							if ($_SESSION['mailcow_cc_role'] == "admin") {
							?>
								<li <?=($_SERVER['REQUEST_URI'] == '/admin') ? 'class="active"' : ''?>><a href="/admin"><?=$lang['header']['administration'];?></a></li>
							<?php
							}
							if ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin") {
							?>
								<li <?=($_SERVER['REQUEST_URI'] == '/manager') ? 'class="active"' : ''?>><a href="/manager"><?=$lang['header']['mailboxes'];?></a></li>
							<?php
							}
							if ($_SESSION['mailcow_cc_role'] == "user") {
							?>
								<li <?=($_SERVER['REQUEST_URI'] == '/user') ? 'class="active"' : ''?>><a href="/user"><?=$lang['header']['user_settings'];?></a></li>
							<?php
							}
						}
						?>
					</ul>
				</li>
					<?php
				}
				if (isset($_SESSION['mailcow_cc_username'])):
				?>
				<li><a href="#" onclick="logout.submit()"><?=sprintf($lang['header']['logged_in_as_logout'], $_SESSION['mailcow_cc_username']);?></a></li>
				<?php
				else:
				?>
				<li class="divider"></li>
				<li><a href="/login"><?=$lang['header']['login'];?></a></li>
				<?php
				endif;
				?>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>
<form action="/login" method="post" id="logout"><input type="hidden" name="logout"></form>
<?php
if (isset($_SESSION['return'])):
?>
<div class="container">
	<div style="position:fixed;bottom:8px;right:25px;width:250px;z-index:2000">
		<div id="alert-fade" class="alert alert-<?=$_SESSION['return']['type'];?>" role="alert">
		<a href="#" class="close" data-dismiss="alert"> &times;</a>
		<?=$_SESSION['return']['msg'];?>
		</div>
	</div>
</div>
<?php
unset($_SESSION['return']);
endif;
?>
