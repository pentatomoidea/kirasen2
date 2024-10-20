<?php
include('./lib/common.php');

if (!SYS_USE_USER_REGIST)
	exit();

if (!$is_user) {
	// ログインしてない時はログインページにリダイレクトさせるレスポンス
	echo '@';
	exit();
}

$db = db_connect();

$res = sem_get(ftok(__FILE__, 'k'));
if (!sem_acquire($res)) {
	@sem_release($res);
    exit();
}

// 自分が付けたスコア
$stat = $db->query_get_one('SELECT val FROM fav WHERE id_user = ? AND id_post = ?', $_SESSION['id'], $_GET['pid']);

// 指定スレに付けられたスコア
list($tar, $now) = $db->query_get_array('SELECT name, score FROM post WHERE id = ?', $_GET['pid']);

// 管理人にはスコアを設定できない
if (mb_substr($tar, -1) == '◆') {
	sem_release($res);
	echo $now;
	exit();
}

// 要求がおかしい場合は無視
if ($_GET['val'] != '1' && $_GET['val'] != '-1') {
	sem_release($res);
	echo $now;
	exit();
}

// 既にプラス評価済み
if ($stat == '1' && $_GET['val'] == '1') {
	sem_release($res);
	echo $now;
	exit();
}

// すでにマイナス評価済み
if ($stat == '-1' && $_GET['val'] == '-1') {
	sem_release($res);
	echo $now;
	exit();
}

// 投稿のスコアを更新
$db->exec('UPDATE post SET score = ? WHERE id = ?', $now + (int)$_GET['val'], $_GET['pid']);

// 評価済みフラグを一端削除する
$db->exec('DELETE FROM fav WHERE id_user = ? AND id_post = ?', $_SESSION['id'], $_GET['pid']);

// 評価済みフラグの追加
if ($stat + (int)$_GET['val'] != 0) {
	// プラスもしくはマイナスの評価が行われた場合のみ
	$db->exec('INSERT INTO fav (id_user, id_post, val, dt_add) VALUES (?, ?, ?, ?)', $_SESSION['id'], $_GET['pid'], $_GET['val'], time());
}

sem_release($res);

// 評価結果を返す
echo $now += (int)$_GET['val'];
