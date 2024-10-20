<?php
include('./lib/common.php');

if (!$is_admin)
	exit();

$db = db_connect();

if ($_POST['thread_cmd'] == 'hide' && $_POST['chk_thread'] == '1' && $_POST['submit'] == 'スレッド操作') {
	// スレッドの非表示
	$db->exec('UPDATE thread SET pin = 3 WHERE id = "' . $_POST['thread'] . '"', time());
} else if ($_POST['thread_cmd'] == 'del' && $_POST['chk_thread'] == '1' && $_POST['submit'] == 'スレッド操作') {
	// スレッドの削除
	$db->exec('UPDATE thread SET dt_del = ?, pin = 0 WHERE id = "' . $_POST['thread'] . '"', time());
} else if ($_POST['thread_cmd'] == 'purge' && $_POST['chk_thread'] == '1' && $_POST['submit'] == 'スレッド操作') {
	// スレッドのパージ
	$db->exec('DELETE FROM post WHERE id_thread = "' . $_POST['thread'] . '"');
	$db->exec('DELETE FROM thread WHERE id = "' . $_POST['thread'] . '"');
} else if ($_POST['post_cmd'] == 'del' && $_POST['chk_post'] == '1' && $_POST['submit'] == '投稿操作') {
	// 投稿の削除or復元
	foreach ($_POST['ids'] as $id) {
		$post = $db->query_get('SELECT dt_del, msg FROM post WHERE id = ?', $id);
		if (is_array($post)) {
			if (!$post['dt_del']) {
				// 削除
				$db->exec('UPDATE post set dt_del = ? WHERE id = ?', time(), $id);
			} else if ($post['msg']) {
				// パージされていなければ復元
				$db->exec('UPDATE post set dt_del = NULL WHERE id = ?', $id);
			}
		}
	}
} else if ($_POST['post_cmd'] == 'purge' && $_POST['chk_post'] == '1' && $_POST['submit'] == '投稿操作') {
	// 投稿のパージ
	$db->exec('UPDATE post set dt_del = ?, msg = "" WHERE id IN (' . implode(',', $_POST['ids']) . ')', time());
} else if ($_POST['post_cmd'] == 'rollback' && $_POST['chk_post'] == '1' && $_POST['submit'] == '投稿操作') {
	// ロールバック

	// 特定スレ以降の番号を全て取得
	$nos = $db->query_get_all('SELECT id FROM post WHERE id_thread = ? AND id >= ?', $_POST['thread'], $_POST['ids'][0]);
	foreach ($nos as &$no) {
		$no = $no['id'];
	}

	// 取得した一覧を全て削除
	$db->exec('DELETE FROM post WHERE id IN (' . implode(',', $nos) . ')');

	// 最終更新日を修正
	$upd = $db->query_get_one('SELECT dt_add FROM post WHERE id_thread = ? AND dt_add > 0 ORDER BY id DESC LIMIT 1', $_POST['thread']);
	$db->exec('UPDATE thread SET dt_upd = ? WHERE id = ?', $upd, $_POST['thread']);
}

header('Location: ' . $_POST['burl'] . '#' . $_POST['thread']);
