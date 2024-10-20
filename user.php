<?php
include('./lib/common.php');

if (!SYS_USE_USER_REGIST)
	exit();

if (@$_REQUEST['act'] == 'logout') {
	// ログアウト
	@session_destroy();
	setcookie('PHPSESSID', '', time() - 100);
	header('Location: ' . BASEDIR . 'user.php');
	exit();
}

global $db;
$emsg = '';

if (isset($_POST) && count($_POST)) {
	@session_start();

	$db = db_connect();

	if ($_POST['act'] == 'login') {
		$user = $db->query_get('SELECT * FROM user WHERE dt_del IS NULL AND user = ?', $_POST['user']);
		if (!$user || !password_verify($_POST['pass'], $user['pass'])) {
			$emsg = 'ユーザーIDもしくはパスワードが異なります';
		} else {
			$_SESSION['id'] = $user['id'];
			$_SESSION['name'] = $user['name'];
			$_SESSION['pam'] = $user['pam'];
			$_SESSION['theme'] = $user['theme'];

			header('Location: ' . BASEDIR);
			exit();
		}
	} else if ($_POST['act'] == 'regist') {	
		$_POST['ans'] = mb_convert_kana(@$_POST['ans'], 'n');
		if ($db->query_get_one('SELECT COUNT(id) FROM capt WHERE id = ? AND ans = ?', @$_POST['capt'], $_POST['ans']) == '0') {
			$emsg = 'キャプチャが正しくありません';
		} else if (!preg_match('/^[a-zA-Z0-9]{8,}$/', $_POST['user'])) {
			$emsg = 'ユーザーIDを正しく入力して下さい';
		} else if (strlen($_POST['pass']) < 8) {
			$emsg = 'パスワードを正しく入力して下さい';
		} else if (preg_match('/[\\r\\n\\t\\x{200C}\\x{200D}\\x{061C}\\x{200E}\\x{200F}\\x{202A}\\x{202B}\\x{202C}\\x{202D}\\x{202E}\\x{2066}\\x{2067}\\x{2068}\\x{2069}]/u', mb_trim($_POST['name']))) {
			$emsg = '表示名を正しく入力して下さい';
		} else if (mb_strlen($_POST['name']) < 2 || mb_strlen($_POST['name']) > 20) {
			$emsg = '表示名を正しく入力して下さい';
		} else if (is_array(SYS_NG_USER_NAME) && array_search(mb_strtolower(mb_convert_kana($_POST['name'], 'a')), SYS_NG_USER_NAME) !== false) {
			$emsg = '指定された表示名は許可されていません';
		} else if ($db->query_get_one('SELECT COUNT(*) FROM user WHERE user = ?', $_POST['user']) != '0') {
			$emsg = '指定されたユーザーIDは既に存在します';
		} else if ($db->query_get_one('SELECT COUNT(*) FROM user WHERE name = ?', $_POST['name']) != '0') {
			$emsg = '指定された表示名は既に存在します';
		} else {
			$db->exec('INSERT INTO user (user, pass, name, pam, dt_add) VALUES (?, ?, ?, 0, ?)', $_POST['user'], password_hash($_POST['pass'], PASSWORD_BCRYPT), $_POST['name'], time());

			// 使ったキャプチャを削除
			$db->exec('DELETE FROM capt WHERE id = ?', $_POST['capt']);

			header('Location: ' . BASEDIR . 'user.php?act=login');
			exit();
		}
	} else if ($_POST['act'] == 'update' && @$_SESSION['id']) {
		if ($_POST['pass'] && strlen($_POST['pass']) < 8) {
			$emsg = 'パスワードを正しく入力して下さい';
		} else if (mb_strlen($_POST['name']) < 2 || mb_strlen($_POST['name']) > 20) {
			$emsg = '表示名を正しく入力して下さい';
		} else if (@is_array(SYS_NG_USER_NAME) && array_search(mb_strtolower(mb_convert_kana($_POST['name'], 'a')), SYS_NG_USER_NAME) !== false) {
			$emsg = '指定された表示名は許可されていません';
		} else if ($db->query_get_one('SELECT COUNT(*) FROM user WHERE id <> ? AND name = ?', $_SESSION['id'], $_POST['name']) != '0') {
			$emsg = '指定された表示名は既に存在します';
		} else {
			$user = $db->query_get('SELECT * FROM user WHERE dt_del IS NULL AND id = ?', $_SESSION['id']);
			if (!$user || !password_verify($_POST['pass'], $user['pass'])) {
				$emsg = '現在のパスワードが異なります';
			} else {
				if ($_POST['pass'])
					$db->exec('UPDATE user SET name = ?, theme = ?, pass = ? WHERE id = ?', $_POST['name'], $_POST['theme'], password_hash($_POST['pass'], PASSWORD_BCRYPT), $_SESSION['id']);
				else
					$db->exec('UPDATE user SET name = ?, theme = ? WHERE id = ?', $_POST['name'], $_POST['theme'], $_SESSION['id']);

				session_destroy();
				header('Location: ' . BASEDIR . 'user.php?act=login');
				exit();
			}
		}
	} else if ($_POST['act'] == 'delete' && @$_SESSION['id']) {
		if ($_POST['chk'] != '1') {
			$emsg = '確認のチェックをして下さい';
		} else {
			$db->exec('UPDATE post SET id_user = NULL WHERE id_user = ?', $_SESSION['id']);
			$db->exec('UPDATE user SET pass = NULL, pam = 0, dt_del = ? WHERE id = ?', time(), $_SESSION['id']);
			session_destroy();

			header('Location: ' . BASEDIR . 'user.php');
			exit();
		}
	}
} else {
	$_POST['name'] = @$_SESSION['name'];
	$_POST['theme'] = @$_SESSION['theme'];
}
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>ユーザーページ | <?=htmlspecialchars(STD_BBS_NAME)?></title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon.png">
<link rel="stylesheet" href="<?=BASEDIR?>theme/<?=USER_THEME?>/style.css">
<?php
	if (file_exists('./theme/' .  USER_THEME . '/custom.css'))
		echo '<link rel="stylesheet" href="' . BASEDIR . 'theme/' . USER_THEME . '/custom.css">';
?>
<script src="/jquery-3.5.1.min.js"></script>
<script src="/script.js"></script>
</head>
<body>
<div class="layoutbox header">
<?php @include('./data/header.php'); ?>
</div>
<?php
if ($emsg) {
?>
<div class="layoutbox userarea_error">
<?=$emsg?>
</div>
<?php
}
?>
<?php
if (@$_REQUEST['act'] != 'regist') {
	if (!@$_SESSION['id']) {
?>
<div class="layoutbox userarea">
<h2>ログイン</h2>
<br>
<form action="" method="post">
<table>
<tr><td>ユーザーID</td><td><input type="text" name="user" value="<?=@$_POST['user']?>" style="width:220px"></td></tr>
<tr><td>パスワード</td><td><input type="password" name="pass" value="" style="width:220px"></td></tr>
</table>
<br>
<input type="hidden" name="act" value="login">
<input type="submit" value="　ログイン　">
</form>
</div>
<?php
	} else {
		$theme_sel = '<select name="theme">';
		foreach (SYS_THEME as $id => $theme) {
			if ($id == $_POST['theme'])
				$theme_sel .= '<option value="' . $id . '" selected="selected">' . $theme['name'] . '</option>';
			else
				$theme_sel .= '<option value="' . $id . '">' . $theme['name'] . '</option>';
		}
		$theme_sel .= '</select>';
?>
<div class="layoutbox userarea">
<h2>ログアウト</h2>
<br>
<form action="" method="post">
<input type="hidden" name="act" value="logout">
<input type="submit" value="　ログアウト　">
</form>
<br>
<br>
<h2>情報変更</h2>
<br>
<form action="" method="post">
<table>
<tr><td>現在のパスワード</td><td><input type="password" name="pass" value="" style="width:220px"></td><td style="color:#55f"></td></tr>
<tr><td>新しいパスワード</td><td><input type="password" name="pass_new" value="" style="width:220px"></td><td style="color:#55f">8文字以上（空白で無変更）</td></tr>
<tr><td>表示名</td><td><input type="text" name="name" value="<?=@$_POST['name']?>" style="width:220px"></td><td style="color:#55f">2文字以上20文字以内</td></tr>
<tr><td>テーマ</td><td><?=$theme_sel?></td><td style="color:blue"></td></tr>
</table>
<br>
<input type="hidden" name="act" value="update">
<input type="submit" value="　更新　">
</form>
<br>
<br>
<h2>アカウント削除</h2>
<br>
<form action="" method="post">
<table>
<tr><td>現在のパスワード</td><td><input type="password" name="pass" value="" style="width:220px"></td><td style="color:blue"></td></tr>
<tr><td colspan="2"><input type="checkbox" id="chk" name="chk" value="1"><label for="chk">アカウントを削除します</label></td></tr>
</table>
<br>
<input type="hidden" name="act" value="delete">
<input type="submit" value="　削除　">
</form>
</div>
<?php
	}
}

if (@$_REQUEST['act'] != 'login' && !@$_SESSION['id']) {
	$db = db_connect();

	$capt_ans = rand(1000, 9999);

	// キャプチャの式と答の組み合わせを保存
	while (true) {
		$capt_id = str_rand(8);
		if ($db->exec('INSERT INTO capt (id, ans, dt_add) VALUES (?, ?, ?)', $capt_id, $capt_ans, time()) == 1)
			break;
	}

	// キャプチャ画像の作成
	$img = imagecreatetruecolor(70, 26);
	imagefilledrectangle($img, 0, 0, 69, 26, SYS_THEME[USER_THEME]['capt_bg']);

	// 色の明るさの変換
	$r = SYS_THEME[USER_THEME]['capt_fg'] >> 16;
	$g = SYS_THEME[USER_THEME]['capt_fg'] >> 8 & 0xff;
	$b = SYS_THEME[USER_THEME]['capt_fg'] & 0xff;
	$col = imagecolorallocatealpha($img, $r, $g, $b, 15);

	// ノイズ描画
	for ($i = 0; $i < 10; $i++) {
		imageline($img, rand(-10, 69 - 10), 0, rand(-10, 69 - 10), 25, $col);
	}
	for ($i = 0; $i < 5; $i++) {
		imageline($img, 0, rand(0, 25), 69, rand(0, 25), SYS_THEME[USER_THEME]['capt_fg']);
	}
	for ($i = 0; $i < 7; $i++) {
		imageline($img, rand(-10, 69 - 10), 0, rand(-10, 69 - 10), 25, SYS_THEME[USER_THEME]['capt_bg']);
	}

	// テキスト描画
	$ang = rand(-6, 6);
	$left = rand(2, 20);
	imagettftext($img, 16, $ang * 2, $left, 19 + $ang, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_ans);
	imagettftext($img, 16, $ang * 2, $left + 1, 19 + $ang, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_ans);
	imagettftext($img, 16, $ang * 2, $left, 19 + $ang + 1, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_ans);
	imagettftext($img, 16, $ang * 2, $left + 1, 19 + $ang + 1, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_ans);

	// 画像作成
	ob_start();
	imagejpeg($img, null, 15);
	$capt_img = 'data:image/jpeg;base64,' . base64_encode(ob_get_contents());
	ob_end_clean();
	imagedestroy($img);
?>
<div class="layoutbox userarea">
<h2>アカウント作成</h2>
<br>
<form action="" method="post">
<table>
<tr><td>ユーザーID</td><td><input type="text" name="user" value="<?=@$_POST['user']?>" style="width:220px"></td><td style="color:#55f">8文字以上の半角英数字</td></tr>
<tr><td>パスワード</td><td><input type="password" name="pass" value="<?=@$_POST['pass']?>" style="width:220px"></td><td style="color:#55f">8文字以上</td></tr>
<tr><td>表示名</td><td><input type="text" name="name" value="<?=@$_POST['name']?>" style="width:220px"></td><td style="color:#55f">2文字以上20文字以内</td></tr>
<tr><td>キャプチャ</td><td><img src="<?=$capt_img?>" style="display:inline" style="vertical-align:bottom">　<input type="text" name="ans" class="post_capt_ans" autocomplete="off"></td><td style="color:blue"></td></tr>
</table>
<br>
<input type="hidden" name="capt" value="<?=$capt_id?>">
<input type="hidden" name="act" value="regist">
<input type="submit" value="　登録　">
</form>
<br>
<br>
<h2>アカウント作成について</h2>
<br>
・アカウント作成は必須ではありません。<br>
・アカウント作成を行っても匿名で利用できます。<br>
</div>
<?php
}
?>
<div class="layoutbox footer">
<?php @include('./data/footer.php'); ?>
</div>
</body>
</html>
