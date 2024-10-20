<?php
include_once(__DIR__ . '/common.php');

@session_start();

$db = db_connect();

// ログイン
if (isset($_POST) && isset($_POST['act'])) {
	if ($_POST['act'] == 'login') {
		if ($_POST['user'] == ADMIN_USER && $_POST['pass'] == ADMIN_PASS) {
			$_SESSION['admin'] = true;
			if (NOTY_TRIGGER_LOGIN_SUCCESS)
				send_telegram('LOGIN_SUCCESS', "USER:" . @$_POST['user']);
		} else {
			if (NOTY_TRIGGER_LOGIN_FAILED)
				send_telegram('LOGIN_FAILED', "USER:" . @$_POST['user'] . "\n" . "PASS:" . @$_POST['pass']);
		}

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}
}

// ログアウト
if (@$_GET['__A'] == 'logout') {
	unset($_SESSION['admin']);
	header('Location: ' . BASEDIR);
	exit();
}


if (isset($_POST) && isset($_POST['act']) && @$_SESSION['admin']) {
	// 複数選択でスレッドの削除、パージ
	if ($_POST['act'] == 'checkbox') {
		if (@$_POST['chk_d'] != '1' && @$_POST['chk_p'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		if (!@count($_POST['ids'])) {
			echo 'スレを1つ以上選択して下さい';
			exit();
		}

		foreach ($_POST['ids'] as &$id) {
			$id = '"' . $id . '"';
		}

		if (@$_POST['chk_d'] == '1') {
			// 削除
			$db->exec('UPDATE thread SET dt_del = ?, pin = 0 WHERE id IN (' . implode(',', $_POST['ids']) . ')', time());
		} else {
			// パージ
			$db->exec('DELETE FROM post WHERE id_thread IN (' . implode(',', $_POST['ids']) . ')');
			$db->exec('DELETE FROM thread WHERE id IN (' . implode(',', $_POST['ids']) . ')');	
		}

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}

	// スレッドの編集
	if ($_POST['act'] == 'edit') {
		if (@$_POST['chk'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		if (@$_POST['title'] == '') {
			echo 'タイトルを入力して下さい';
			exit();
		}

		$db->exec('UPDATE thread SET title = ?, comment = ?, pin = ?, pam = ? WHERE id = ?', $_POST['title'], $_POST['comment'], $_POST['pin'], $_POST['pam'], $_POST['id']);

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}

	// スレッドの削除
	if ($_POST['act'] == 'delete') {
		if (@$_POST['chk'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		$db->exec('UPDATE thread SET dt_del = ?, pin = 0, pam = 0 WHERE id = ?', time(), $_POST['id']);

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}

	// スレッドのパージ
	if ($_POST['act'] == 'purge') {
		if (@$_POST['chk'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		$db->exec('DELETE FROM post WHERE id_thread = ?', $_POST['id']);
		$db->exec('DELETE FROM thread WHERE id = ?', $_POST['id']);

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}

	// スレッドのロールバック
	if ($_POST['act'] == 'rollback') {
		if (@$_POST['chk'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		$_POST['no'] = (int)mb_convert_kana($_POST['no'], 'n');

		if ($_POST['no'] <= 1 || @$_POST['no'] == '' || !preg_match('/^[0-9]+$/', $_POST['no'])) {
			echo '開始スレを正しく入力して下さい';
			exit();
		}

		// 特定スレ以降の番号を全て取得
		$nos = $db->query_get_all('SELECT id FROM post WHERE id_thread = ? ORDER BY id LIMIT ?, 100000', $_POST['id'], $_POST['no'] - 1);
		foreach ($nos as &$no) {
			$no = '"' . $no['id'] . '"';
		}
		$db->exec('DELETE FROM post WHERE id IN (' . implode(',', $nos) . ')');

		// 最終更新日を修正
		$upd = $db->query_get_one('SELECT dt_add FROM post WHERE id_thread = ? AND dt_add > 0 ORDER BY id DESC LIMIT 1', $_POST['id']);
		$db->exec('UPDATE thread SET dt_upd = ? WHERE id = ?', $upd, $_POST['id']);

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}
	
	// 複数選択で投稿の削除、パージ
	if ($_POST['act'] == 'checkbox_post') {
		if (@$_POST['chk_d'] != '1' && @$_POST['chk_p'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		if (!@count($_POST['ids'])) {
			echo '投稿を1つ以上選択して下さい';
			exit();
		}

		foreach ($_POST['ids'] as &$id) {
			$id = '"' . $id . '"';
		}

		if (@$_POST['chk_d'] == '1') {
			$db->exec('UPDATE post set dt_del = ? WHERE id IN (' . implode(',', $_POST['ids']) . ')', time());
		} else {
			$db->exec('UPDATE post set dt_del = ?, msg = "" WHERE id IN (' . implode(',', $_POST['ids']) . ')', time());
		}

		header('Location: ' . BASEDIR . $_GET['__T'] . '/posts/' . $_POST['tid']);
		exit();
	}
	
	// 全てのスレッドをロールバック
	if ($_POST['act'] == 'timemachine') {
		if (@$_POST['chk'] != '1') {
			echo '操作確認をして下さい';
			exit();
		}

		if ($_POST['y'] < 2020 || !checkdate($_POST['m'], $_POST['d'], $_POST['y'])) {
			echo '日付が不正です';
			exit();
		} else if ($_POST['h'] < 0 || $_POST['h'] > 23 || $_POST['i'] < 0 || $_POST['i'] > 59 || $_POST['s'] < 0 || $_POST['s'] > 59) {
			echo '時間が不正です';
			exit();
		}

		$utime = mktime($_POST['h'], $_POST['i'], $_POST['s'], $_POST['m'], $_POST['d'], $_POST['y']);
	
		// 投稿削除
		$db->exec('DELETE FROM post WHERE dt_add >= ?', $utime);
	
		// スレッド削除
		$db->exec('DELETE FROM thread WHERE dt_add >= ?', $utime);

		if (!MYSQL_HOST)
			$db->exec('VACUUM');

		$threads = $db->query_get_all('SELECT id FROM thread WHERE dt_del IS NULL');
		foreach($threads as $thread) {
			// 最終更新日を修正
			$upd = $db->query_get_one('SELECT dt_add FROM post WHERE id_thread = ? AND dt_add > 0 ORDER BY id DESC LIMIT 1', $thread['id']);
			$db->exec('UPDATE thread SET dt_upd = ? WHERE id = ?', $upd, $thread['id']);
		}

		header('Location: ' . BASEDIR . $_GET['__T']);
		exit();
	}

	exit();
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title><?=htmlspecialchars(STD_BBS_NAME)?>管理画面</title>
<link rel="icon" type="image/png" href="<?=BASEDIR?>favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?=BASEDIR?>favicon.png">
<style>
* {
    box-sizing: border-box;
}
body {
    color: #000;
    background-color: #fff;
	margin: 16px;
	font-size: 16px;
	line-height: 1.7em;
    word-wrap: break-word;
    font-family: sans-serif;
}
form {
	margin: 0;
	padding: 0;
	display: inline-block;
}
a {
	color: blue;
	text-decoration: none;
}
table {
	max-width: 100%;
    border: solid 1px #666;
    border-collapse: collapse;
}
.posts {
	max-width: 100%;
}
table td, table th {
    border: solid 1px #666;
    padding: 6px;
}
table th {
	text-align: left;
    background-color: #ddd;
}
.posts td {
	word-wrap:break-word;
	vertical-align: top;
}
.posts tr:nth-child(odd) td {
	background-color: #eee;
}
</style>
<script>
window.onload = function() {
	chk_all = document.getElementById('chk_all');
	chk_all.onclick = function(e) {
		ids = document.getElementsByName('ids[]');
		for (var i = 0; i < ids.length; ++i) {
			ids[i].checked = chk_all.checked;
		}
	}
}
</script>
</head>
<body>
<a href="<?=BASEDIR?>" target="_blank"><?=htmlspecialchars(STD_BBS_NAME)?></a>管理画面（Version.<?=get_version()?>） 
<?php
if (@$_SESSION['admin']) {
?>
<a href="<?=BASEDIR.$_GET['__T']?>">書込管理</a>　<a href="<?=BASEDIR.$_GET['__T']?>/timemachine">タイムマシン</a>
<?php
}
?>
<hr><br>
<?php
if (!isset($_SESSION['admin'])) { // ==================== ログイン
?>
<form action="" method="post">
<table>
<tr><th>USER</th><td><input type="text" name="user"></td></tr>
<tr><th>PASS</th><td><input type="password" name="pass"></td></tr>
</table>
<input type="hidden" name="act" value="login">
<br>
<input type="submit" value="　ログイン　">
</form>
<?php
} else if (@$_GET['__A'] == 'edit') { // ==================== スレッド編集



	// スレッド編集画面
	$thread = $db->query_get('SELECT * FROM thread WHERE id = ?', $_GET['__P']);
	if (!$thread) {
		header('Location: ' . $_SERVER['SCRIPT_NAME']);
		exit();
	}

	$pin_s = array('通常', '上固定', '下固定', '非表示');
	$pin_sel = '<select name="pin">';
	foreach ($pin_s as $key=>$val) {
		if ($key == $thread['pin'])
			$pin_sel .= '<option value="' . $key . '" selected="selected">' . $val . '</option>';
		else
			$pin_sel .= '<option value="' . $key . '">' . $val . '</option>';
	}
	$pin_sel .= '</select>';

	$pam_s = array(0 => '通常', 1 => '登録ユーザー書込', 2 => '登録ユーザー書込閲覧', 9 => '非公開', 10 => '凍結');
	$pam_sel = '<select name="pam">';
	foreach ($pam_s as $key=>$val) {
		if ($key == $thread['pam'])
			$pam_sel .= '<option value="' . $key . '" selected="selected">' . $val . '</option>';
		else
			$pam_sel .= '<option value="' . $key . '">' . $val . '</option>';
	}
	$pam_sel .= '</select>';
?>
<h2>編集操作</h2>
<form action="?" method="post" onsubmit="return false;">
<table>
<tr><th>操作確認</th><td><input type="checkbox" id="act_e" name="chk" value="1"><label for="act_e">スレ設定の編集</label></td></tr>
<tr><th>タイトル</th><td><input type="text" name="title" value="<?=htmlspecialchars($thread['title'])?>" style="width:500px"></td></tr>
<tr><th>コメント<t/h><td><input type="text" name="comment" value="<?=htmlspecialchars($thread['comment'])?>" style="width:500px"></td></tr>
<tr><th>ピン</th><td><?=$pin_sel?></td></tr>
<tr><th>許可</th><td><?=$pam_sel?></td></tr>
</table>
<br>
<input type="hidden" name="act" value="edit">
<input type="hidden" name="id" value="<?=$thread['id']?>">
<button type="button" onclick="submit()">　実行　</button>
</form>
<br>
<br>
<h2>削除操作</h2>
<table>
<tr><th>操作確認</th><th>内容</th><th>実行</th></tr>
<tr><form action="?" method="post" onsubmit="return false;"><td><input type="checkbox" id="act_d" name="chk" value="1"><label for="act_d">デリート</label><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=$_GET['__P']?>"></td><td>削除フラグを付けて運営側ログとして残します</td>
<td style="text-align:right"><button type="button" onclick="submit()">実行</button></td></form></tr>
<tr><form action="?" method="post" onsubmit="return false;"><td><input type="checkbox" id="act_p" name="chk" value="1"><label for="act_p">パージ</label><input type="hidden" name="act" value="purge"><input type="hidden" name="id" value="<?=$_GET['__P']?>"></td><td>完全に削除を行いログも残しません（復元不可）</td>
<td style="text-align:right"><button type="button" onclick="submit()">実行</button></td></form></tr>
<tr><form action="?" method="post" onsubmit="return false;"><td><input type="checkbox" id="act_r" name="chk" value="1"><label for="act_r">ロールバック</label><input type="hidden" name="act" value="rollback"><input type="hidden" name="id" value="<?=$_GET['__P']?>"></td><td>特定のスレ以降を削除して途中再開します</td>
<td style="text-align:right">削除開始スレ：<input type="text" style="width:60px" name="no">　<button type="button" onclick="submit()">実行</button></td></form></tr>
</table>
<?php
} else if (@$_GET['__A'] == 'posts') { // ==================== 投稿一覧




	$thread = $db->query_get('SELECT * FROM thread WHERE id = ?', $_GET['__P']);
	$posts = $db->query_get_all('SELECT * FROM post WHERE id_thread = ?', $_GET['__P']);
?>
<h2>投稿一覧（<?=$thread['title']?>）</h2>
<form action="?" method="post" onsubmit="return false;">
<input type="hidden" name="act" value="checkbox_post">
<input type="hidden" name="tid" value="<?=$thread['id']?>">
<table class="posts" style="width:100%">
<tr><th style="width:10px"><input type="checkbox" id="chk_all"></th><th style="width:40px;text-align:right">No</th><th style="width:150px">名前</th><th style="width:auto">内容</th><th style="width:240px">投稿日時</th><th style="width:240px">削除日時</th></tr>
<?php
	$no = 1;
	foreach ($posts as $post) {
		$post['name'] = htmlspecialchars($post['name']);
		$post['msg'] = htmlspecialchars($post['msg']);

		if ($post['dt_del']) {
			$post['dt_del'] = date_jp(SYS_DATE_FORMAT, $post['dt_del']);
			if ($post['msg'])
				$post['msg'] = '<span style="color:gray">削除済み<br>' . mb_strimwidth($post['msg'], 0, 100, '...') . '</span>';
			else
				$post['msg'] = '<span style="color:gray">削除済み</span>';
		} else {
			$post['msg'] = mb_strimwidth($post['msg'], 0, 100, '...');
		}
?>
<tr><td><input type="checkbox" name="ids[]" value="<?=$post['id']?>"></td><td style="text-align:right"><?=$no++?></td><td><?=$post['name']?></td><td><?=$post['msg']?></td><td><?=date_jp(SYS_DATE_FORMAT, $post['dt_add'])?></td><td><?=$post['dt_del']?></td></tr>
<?php
	}
?>
</table>
<br>
チェックした項目を…<br>
<table>
<tr><th>操作確認</th><th>内容</th><th>実行</th></tr>
<tr><td><input type="checkbox" id="act_d" name="chk_d" value="1"><label for="act_d">デリート</label></td><td>削除フラグを付けて運営側ログとして残します</td>
<td style="text-align:right" rowspan="2"><button type="button" onclick="submit()">実行</button></td></tr>
<tr><td><input type="checkbox" id="act_p" name="chk_p" value="1"><label for="act_p">パージ</label></td><td>投稿内容を空にします（復元不可）</td></tr>
</table>
</form>
<?php
} else if (@$_GET['__A'] == 'timemachine') { // ==================== タイムマシン

$y = '<select name="y"><option value="">----</option>';
for ($i = date('Y') - 3; $i <= date('Y'); $i++) {
	$y .= '<option value="' . $i . '">' . $i . '年</option>';
}
$y .= '</select>';

$m = '<select name="m"><option value="">----</option>';
for ($i = 1; $i <= 12; $i++) {
	$m .= '<option value="' . $i . '">' . $i . '月</option>';
}
$m .= '</select>';

$d = '<select name="d"><option value="">----</option>';
for ($i = 1; $i <= 31; $i++) {
	$d .= '<option value="' . $i . '">' . $i . '日</option>';
}
$d .= '</select>';

$h = '<select name="h"><option value="">----</option>';
for ($i = 0; $i <= 23; $i++) {
	$h .= '<option value="' . $i . '">' . $i . '時</option>';
}
$h .= '</select>';

$j = '<select name="i"><option value="">----</option>';
for ($i = 0; $i <= 59; $i++) {
	$j .= '<option value="' . $i . '">' . $i . '分</option>';
}
$j .= '</select>';

$s = '<select name="s"><option value="">----</option>';
for ($i = 0; $i <= 59; $i++) {
	$s .= '<option value="' . $i . '">' . $i . '秒</option>';
}
$s .= '</select>';

?>
<h2>タイムマシン</h2>
<form action="?" method="post">
<input type="hidden" name="act" value="timemachine">
<span style="color:red">この処理は取り消しができません。十分に注意して下さい！<br>DBのバックアップを推奨します。</span><br>
<br>
<table>
<tr><th>日時</th><td><?=$y?> <?=$m?> <?=$d?>　<?=$h?> <?=$j?> <?=$s?></td></tr>
<tr><th>確認</th><td><input type="checkbox" id="chk" name="chk" value="1"><label for="chk">指定日時の状態まで復元する</label></td></tr>
<tr><th>実行</th><td><input type="submit" value="　スレッドと投稿を削除する　"></td></tr>
</table>
</form>
<br>
<?php
} else { // ==================== INDEX
	$where_i = '';
	$where_k = array();

	if (@$_POST['w']) {
		$where_i .= ' AND thread.title LIKE ?';
		$where_k[] = '%' . $_POST['w'] . '%';
	}

	if (@$_POST['ts']) {
		$where_i .= ' AND thread.dt_add >= ?';
		$where_k[] = time() - 60 * 60 * (int)@$_POST['ts'];
	}

	if (@$_POST['te']) {
		$where_i .= ' AND thread.dt_add <= ?';
		$where_k[] = time() - 60 * 60 * (int)@$_POST['te'];
	}

	if (!@$_POST['id']) {
		$where_i .= ' AND thread.dt_del IS NULL';
	}

	$threads = $db->query_get_all('SELECT thread.id as id, thread.dt_add, thread.dt_upd, thread.dt_del, thread.pin, thread.pam, thread.comment, thread.title, COUNT(post.id) as cou FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.id IS NOT NULL ' . $where_i . ' GROUP BY id_thread ORDER BY thread.pin, thread.dt_upd DESC LIMIT 1000', $where_k);

	$qt_sel_s = '<select name="ts"><option value=""></option>';
	for ($i = 1; $i <= 48; $i++) {
		if ($i == @$_GET['ts'])
			$qt_sel_s .= '<option value="' . $i . '" selected="selected">' . $i . '時間前から</option>';
		else
			$qt_sel_s .= '<option value="' . $i . '">' . $i . '時間前から</option>';
	}
	$qt_sel_s .= '</select>';

	$qt_sel_e = '<select name="te"><option value=""></option>';
	for ($i = 1; $i <= 48; $i++) {
		if ($i == @$_GET['te'])
			$qt_sel_e .= '<option value="' . $i . '" selected="selected">' . $i . '時間前まで</option>';
		else
			$qt_sel_e .= '<option value="' . $i . '">' . $i . '時間前まで</option>';
	}
	$qt_sel_e .= '</select>';
?>
<h2>スレ検索</h2>
<form action="?" method="post" style="width:100%">
<table>
<tr><th>タイトル</th><td><input type="text" name="w" value="<?=@$_GET['w']?>" style="width:400px"></td></tr>
<tr><th>作成時間</th><td><?=$qt_sel_s?>　<?=$qt_sel_e?></td></tr>
<tr><th>区分</th><td>
<input type="checkbox" id="is_del" name="id" value="1" <?=@$_GET['id']?'checked="checked"':''?>><label for="is_del">削除済みを含む</label>
　<input type="checkbox" id="is_acv" name="ia" value="1" <?=@$_GET['ia']?'checked="checked"':''?>><label for="is_acv">アーカイブ済みを含む</label></td></tr>
</table>
<br>
<input type="submit" value="　検索　">
</form><br>
<br>
<h2>スレ一覧（最大1000件まで表示）</h2>
<form action="?" method="post" onsubmit="return false;" style="width:100%">
<input type="hidden" name="act" value="checkbox">
<table class="posts" style="width:100%">
<tr><th style="width:10px"><input type="checkbox" id="chk_all"></th><th style="width:100px">ID（操作）</th><th style="width:auto">タイトル（表示）</th><th style="width:60px;text-align:right">投稿数</th><th style="width:240px">作成日時</th><th style="width:240px">最終更新</th>
<th style="width:60px;text-align:center">ピン</th><th style="width:60px;text-align:center">許可</th><th style="width:auto">コメント</th><th style="width:40px;text-align:center">操作</th></tr>
<?php
	foreach ($threads as $thread) {
		if ($thread['pin'] == '1')
			$thread['pin'] = '上固定';
		else if ($thread['pin'] == '2')
			$thread['pin'] = '下固定';
		else if ($thread['pin'] == '3')
			$thread['pin'] = '非表示';
		else
			$thread['pin'] = '';
		
		if ($thread['pam'] == '1')
			$thread['pam'] = '書禁';
		else
			$thread['pam'] = '';
		
		if ($thread['dt_del']) {
			$thread['title'] = '<del style="color:gray">' . htmlspecialchars($thread['title']) . '</del>';
			$thread['pin'] = '<del style="color:gray">削除済</del>';
		} else {
			$thread['title'] = '<a href="' . BASEDIR . $thread['id'] . '" target="_blank">' . htmlspecialchars($thread['title']) . '</a>';
		}

		// アーカイブの除外
		if (!@$_GET['ia'] && $thread['cou'] == MAX_POST && ((int)$thread['dt_upd']) < time() - MAX_LIFE && !$thread['dt_del'])
			continue;
?>
<tr><td style="width:10px"><input type="checkbox" name="ids[]" value="<?=$thread['id']?>"></td><td><a href="<?=BASEDIR.$_GET['__T']?>/posts/<?=$thread['id']?>"><?=$thread['id']?></a></td><td><?=$thread['title']?></td><td style="text-align:right"><?=$thread['cou']?></td><td><?=date_jp(SYS_DATE_FORMAT, $thread['dt_add'])?></td><td><?=date_jp(SYS_DATE_FORMAT, $thread['dt_upd'])?></td>
<td style="text-align:center"><?=$thread['pin']?></td><td style="text-align:center"><?=$thread['pam']?></td><td><?=$thread['comment']?></td><td style="text-align:center"><a href="<?=BASEDIR.$_GET['__T']?>/edit/<?=$thread['id']?>">編集</td>
</tr>
<?php
	}
?>
</table>

<br>
チェックした項目を…<br>
<table>
<tr><th>操作確認</th><th>内容</th><th>実行</th></tr>
<tr><td><input type="checkbox" id="act_d" name="chk_d" value="1"><label for="act_d">デリート</label></td><td>削除フラグを付けて運営側ログとして残します</td>
<td style="text-align:right" rowspan="2"><button type="button" onclick="submit()">実行</button></td></tr>
<tr><td><input type="checkbox" id="act_p" name="chk_p" value="1"><label for="act_p">パージ</label></td><td>完全に削除を行いログも残しません（復元不可）</td></tr>
</table>
</form><br>
<?php
}
?>
<br>
<br>
<hr>
<a href="<?=BASEDIR.$_GET['__T']?>/logout">ログアウト</a>
</body>
</html>
