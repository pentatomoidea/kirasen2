<?php
date_default_timezone_set('Asia/Tokyo');
mb_language('ja');
mb_internal_encoding('UTF-8');

//ini_set('display_errors', 'On');

include(__DIR__ . '/../data/conf.php');
include(__DIR__ . '/db.php');
include(__DIR__ . '/funcs.php');
if (file_exists(__DIR__ . '/../data/userfuncs.php')) {
	include(__DIR__ . '/../data/userfuncs.php');
}

if (isset($_COOKIE['PHPSESSID'])) {
	// セッションCookieがある場合のみセッションを有効にする
	// ログインをしていなユーザーに対して無闇にCookieを発行しない
	session_start();

	if (isset($_SESSION['admin']) && $_SESSION['admin'])
		$is_admin = true;
	else
		$is_admin = false;

	if (isset($_SESSION['id']) && $_SESSION['id']) {
		$is_user = true;

		if (@$_SESSION['theme'])
			define('USER_THEME', $_SESSION['theme']);
		else
			define('USER_THEME', STD_THEME);
	} else {
		$is_user = false;
		define('USER_THEME', STD_THEME);
	}
} else {
	$is_admin = false;
	$is_user = false;
	define('USER_THEME', STD_THEME);
}
