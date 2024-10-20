<?php
/*
 * Kirasen v.0.2.23
 * Copyright (c) 2020 Taiyakikun
 * Released under the MIT license
 */


include('./lib/common.php');


// 管理画面
if (@$_GET['__T'] == ADMIN_PAGE) {
	include('./lib/admin.php');
	exit();
}

global $db;
$db = db_connect();

$show_no = array();
$show_max = INDEX_SHOW_POST;


// 投稿前に管理者名への変換とトリップの処理を行う
function name_replace($name) {
	$name_temp = str_replace(array_keys(ADMIN_NAME), ADMIN_NAME, $name);
	if ($name_temp != $name)
		return str_replace('◆', '◇', $name_temp) . '◆';
	
	if (!preg_match('/.*?#.+/', $name))
		return str_replace('◆', '◇', $name);

	list($name, $tripkey) = explode('#', $name, 2);
	$tripkey = mb_convert_encoding($tripkey, 'SJIS', 'UTF-8');

	if (strlen($tripkey) >= 12) {
		if ($tripkey[0] == '#') {
			// 10 digits new protocol
			if (preg_match('/^#([0-9a-fA-F]{16})([\.\/0-9a-zA-Z]{0,2})$/', $tripkey, $matches)) {
				$key = pack('H*', $matches[1]);
				if (($index = strpos($key, chr(128))) !== false)
					$key = substr($key, 0, $index);
				$trip = substr(crypt($key, substr($matches[2] . '..', 0, 2)), -10);
			} else {
				$trip = '???';
			}
		} else if ($tripkey[0] == '$') {
			// reserved
			$trip = '???';
		} else {
			// 12 digits
			$trip = str_replace('+', '.', substr(base64_encode(sha1($tripkey, true)), 0, 12));
		}
	} else {
		// 10 digits
		$key = htmlspecialchars($tripkey, ENT_QUOTES, 'SJIS');
		$salt = preg_replace('/[^.-z]/', '.', substr($key.'H.', 1, 2));
		$map = array(':' => 'A', ';' => 'B', '<' => 'C', '=' => 'D', '>' => 'E', '?' => 'F',
			'@' => 'G', '[' => 'a', '\\' => 'b', ']' => 'c', '^' => 'd', '_' => 'e', '`' => 'f');
		$trip = substr(crypt($key, strtr($salt, $map)), -10);
	}

	return $name . '◆' . mb_convert_encoding($trip, 'UTF-8', 'SJIS');
}

// JSON形式で渡されたデータを出力（バックアップ）
function output_json($id, $title, $datas) {
	if (!SYS_USE_OUTPUT_JSON)
		exit();

	$json = array('title' => $title, 'data' => array());

	$repid = 0;
	foreach ($datas as $data) {
		if ($data['dt_del']) {
			$data['name'] = '削除';
			$date['dt_add'] = '削除';
			$date['msg'] = '削除';
		}

		if (!$data['u_del'] && $data['u_name'])
			$data['name'] = '@' . $data['u_name'];
		else if (!$data['name'])
			$data['name'] = STD_ANON_NAME;

		$json['data'][++$repid] = array('name' => $data['name'], 'date' => $data['dt_add'], 'msg' => $data['msg']);
	}

	$json = json_encode($json, JSON_UNESCAPED_UNICODE);

	header('Content-disposition: attachment; filename=' . $id . '.json');
	if (isset($_SERVER['HTTP_ORIGIN']) && strtolower($_SERVER['HTTP_ORIGIN'] == 'null')) {
		header('Access-Control-Allow-Origin: *');
	}
	header('Content-type: application/json');
	header('Content-Length: ' . strlen($json));
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');

	print $json;
	exit();
}

// JSON形式で渡されたデータを出力（バックアップ）
function output_json_all($datas) {
	if (!SYS_USE_OUTPUT_JSON)
		exit();

	$json = json_encode($datas, JSON_UNESCAPED_UNICODE);

	header('Content-disposition: attachment; filename=' . date('YmdHis') . '.json');
	if (isset($_SERVER['HTTP_ORIGIN']) && strtolower($_SERVER['HTTP_ORIGIN'] == 'null')) {
		header('Access-Control-Allow-Origin: *');
	}
	header('Content-type: application/json');
	header('Content-Length: ' . strlen($json));
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');

	print $json;
	exit();
}

// RSS形式で渡されたデータを出力
function output_rss($id, $title, $datas) {
	if (!SYS_USE_OUTPUT_RSS)
		exit();

	$rss = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n<rss version=\"2.0\">\r\n<channel>\r\n"
		. "<title>" . htmlspecialchars(STD_BBS_NAME) . ' - ' . htmlspecialchars($title) . "</title>\r\n"
		. "<link>http://" . $_SERVER['HTTP_HOST'] . "/" . $id . "</link>\r\n"
		. "<category>" . htmlspecialchars($title) . "</category>\r\n"
		. "<description>" . htmlspecialchars(STD_BBS_NAME) . "</description>\r\n";

	$repid = 0;
	foreach ($datas as $data) {
		$repid++;

		if ($data['dt_del'])
			continue;

		if (!$data['u_del'] && $data['u_name'])
			$data['name'] = '@' . $data['u_name'];
		else if (!$data['name'])
			$data['name'] = STD_ANON_NAME;

		$rss .= "<item>\r\n<title>" . $repid . " " . htmlspecialchars($data['name']) . " " . date('y-m-d D H:i:s', $data['dt_add']) . "</title>\r\n"
			. "<link>http://" . $_SERVER['HTTP_HOST'] . "/" . $id . "/" . $repid . "</link>\r\n"
			. "<pubDate>" . date('D, j M Y H:i:s', $data['dt_add']) . ' +0900' . "</pubDate>\r\n"
			. "<description><![CDATA[" . nl2br(htmlspecialchars(mb_strimwidth($data['msg'], 0, 1000, '...'))) . "]]></description>\r\n</item>\r\n";
	}

	$rss .= "\r\n</channel>\r\n</rss>";

	if ($id == 0)
		$id = 'highlight';

	header('Content-type: text/xml');
	header('Content-Length: ' . strlen($rss));
	header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, j M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');

	print $rss;
	exit();
}

// RSS形式で渡されたデータを出力（ヘッドライン）
function output_rss_all($datas) {
	if (!SYS_USE_OUTPUT_RSS)
		exit();

	$rss = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n<rss version=\"2.0\">\r\n<channel>\r\n"
		. "<title>" . htmlspecialchars(STD_BBS_NAME) . "</title>\r\n"
		. "<link>http://" . $_SERVER['HTTP_HOST'] . "</link>\r\n"
		. "<description>" . htmlspecialchars(STD_BBS_NAME) . "</description>\r\n";

	foreach ($datas as $data) {
		if ($data['dt_del'])
			continue;

		if (!$data['u_del'] && $data['u_name'])
			$data['name'] = '@' . $data['u_name'];
		else if (!$data['name'])
			$data['name'] = STD_ANON_NAME;

		$rss .= "<item>\r\n<title>" . htmlspecialchars($data['name']) . " " . date('y-m-d D H:i:s', $data['dt_add']) . "（" . htmlspecialchars($data['title']) . "）</title>\r\n"
			. "<link>http://" . $_SERVER['HTTP_HOST'] . "/" . $data['id_thread'] . "/l50</link>\r\n"
			. "<pubDate>" . date('D, j M Y H:i:s', $data['dt_add']) . ' +0900' . "</pubDate>\r\n"
			. "<category>" . htmlspecialchars($data['title']) . "</category>\r\n"
			. "<description><![CDATA[" . nl2br(htmlspecialchars(mb_strimwidth($data['msg'], 0, 1000, '...'))) . "]]></description>\r\n</item>\r\n";
	}

	$rss .= "\r\n</channel>\r\n</rss>";

	header('Content-type: text/xml');
	header('Content-Length: ' . strlen($rss));
	header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, j M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');

	print $rss;
	exit();
}

// PDF形式で指定スレを出力
function output_pdf($id, $a) {
	if (!SYS_USE_OUTPUT_PDF)
		exit();

	if (!preg_match('/^[-_\\.a-zA-Z0-9]+$/', $_SERVER['HTTP_HOST']))
		exit();

	$filepath = tempnam(sys_get_temp_dir(), 'kirasen');
	$ret = exec('xvfb-run wkhtmltopdf --custom-header-propagation -l -p "socks5://127.0.0.1:9050" ' . escapeshellarg('http://' . $_SERVER['HTTP_HOST'] . '/' . $id . '/' . $a . '/noomit') . ' ' . $filepath);

	if (filesize($filepath) > 0) {
		header('Content-disposition: attachment; filename=' . $id . '.pdf');
		header('Content-Type: application/pdf');
		header('Content-Length: ' . filesize($filepath));
		header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, j M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
		header('Pragma: no-cache');
		readfile($filepath);
	}

	@unlink($filepath);
	exit();
}

// エラー表記の出力
function output_err($title, $desc) {
	header('Content-Type: text/html; charset=UTF-8');
	header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, j M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');
?>
<html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>エラー | <?=htmlspecialchars(STD_BBS_NAME)?></title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon.png">
<link rel="stylesheet" href="<?=BASEDIR?>theme/<?=USER_THEME?>/style.css">
<?php
	if (file_exists('./theme/' .  USER_THEME . '/custom.css'))
		echo '<link rel="stylesheet" href="' . BASEDIR . 'theme/' . USER_THEME . '/custom.css">';
?>
</head>
<body>
<div class="layoutbox"><h2>ERROR! : <?=$title?></h2><br><br><?=$desc?></div>
</body></html>
<?php

	exit();
}

// 投稿前の内容精査
function post_check() {
	global $db;

	//if (!isset(ADMIN_NAME[$_POST['name']]))
		//output_err('書き込み制限', '管理者により書き込みの制限が行われています。');

	//if ($_GET['__T'] == 'tcw5wa1w')
	//	return;
	
	// 海外からのスパム防止
	if (isset($_POST['title'])) {
		if (strlen($_POST['title']) == mb_strlen($_POST['title'])) {
			output_err('スパム判定', 'タイトルにマルチバイト文字が含まれていません。');
		}
	}

	// NGワード
	if (is_array(POST_NG_WORD)) {
		foreach (POST_NG_WORD as $re) {
			if (preg_match($re, $_POST['msg'], $matches)) {
				output_err('スパム判定', '内容にNGワードが含まれています。<br><br><span style="color:gray">' . $matches[0] . '</span>');
			}
		}
	}

	if (@POST_MAX_STR && @POST_MAX_LINE) {
		// 文字数制限
		$msg = preg_split('/(\\r\\n|\\r|\\n)---(\\r\\n|\\r|\\n)/s', $_POST['msg']);
		if (mb_strlen($msg[0]) > POST_MAX_STR) {
			output_err('表示文字数オーバー（' . POST_MAX_STR . '文字まで）', '内容にmoreを追加して回避して下さい');
		}

		// 改行数制限
		$msg = preg_split('/(\\r\\n|\\r|\\n)---(\\r\\n|\\r|\\n)/s', $_POST['msg']);
		$msg = preg_split('/(\\r\\n|\\r|\\n)/s', $msg[0]);
		if (count($msg) > POST_MAX_LINE) {
			output_err('表示行数オーバー（' . POST_MAX_LINE . '行まで）', '内容にmoreを追加して回避して下さい');
		}
	}

	// UTF8確認
	if (isset($_POST['title']) && !mb_check_encoding(@$_POST['title'])) {
		output_err('スパム判定', 'タイトルに不正な文字が含まれています。');
	}
	if (!mb_check_encoding(@$_POST['name'])) {
		output_err('スパム判定', '名前に不正な文字が含まれています。');
	}
	if (!mb_check_encoding($_POST['msg'])) {
		output_err('スパム判定', '内容に不正な文字が含まれています。');
	}

	if (preg_match('/[ʰ-ͯ]+/u', @$_POST['title'])) {
		output_err('スパム判定', 'タイトルに不正な文字が含まれています。');
	}
	if (preg_match('/[ʰ-ͯ]+/u', @$_POST['name'])) {
		output_err('スパム判定', '名前に不正な文字が含まれています。');
	}
	if (preg_match('/[ʰ-ͯ]+/u', $_POST['msg'])) {
		output_err('スパム判定', '内容に不正な文字が含まれています。');
	}

	// TAB、NULL、幅0の文字、文字方向を変えるコード、一部改行
	if (isset($_POST['title']) && preg_match('/[\\r\\n\\0\\x{Cc}\\x{200C}\\x{200D}\\x{061C}\\x{200E}\\x{200F}\\x{202A}\\x{202B}\\x{202C}\\x{202D}\\x{202E}\\x{2066}\\x{2067}\\x{2068}\\x{2069}\\x{FFF0}-\\x{FFFF}]/u', $_POST['title'], $matches)) {
		output_err('スパム判定', 'タイトルに不正とされる制御文字を検出しました。<span style="color:gray"> (U+'
			. base_convert(bin2hex(mb_convert_encoding($matches[0][0], 'UTF-32BE', 'UTF-8')), 16, 16) . ')</span>');
	}
	if (preg_match('/[\\r\\n\\0\\x{Cc}\\x{200C}\\x{200D}\\x{061C}\\x{200E}\\x{200F}\\x{202A}\\x{202B}\\x{202C}\\x{202D}\\x{202E}\\x{2066}\\x{2067}\\x{2068}\\x{2069}\\x{FFF0}-\\x{FFFF}]/u', $_POST['name'], $matches)) {
		output_err('スパム判定', '名前に不正とされる制御文字を検出しました。<span style="color:gray"> (U+'
			. base_convert(bin2hex(mb_convert_encoding($matches[0][0], 'UTF-32BE', 'UTF-8')), 16, 16) . ')</span>');
	}
	if (preg_match('/[\\x{Cc}\\x{200C}\\x{200D}\\x{061C}\\x{200E}\\x{200F}\\x{202A}\\x{202B}\\x{202C}\\x{202D}\\x{202E}\\x{2066}\\x{2067}\\x{2068}\\x{2069}\\x{FFF0}-\\x{FFFF}]/u', $_POST['msg'], $matches)) {
		output_err('スパム判定', '内容に不正とされる制御文字を検出しました。<span style="color:gray"> (U+'
			. base_convert(bin2hex(mb_convert_encoding($matches[0][0], 'UTF-32BE', 'UTF-8')), 16, 16) . ')</span>');
	}

	if (mb_strlen($_POST['msg']) >= 30) {
		if (mb_strlen(preg_replace('/[\\p{Arabic}\\p{Hebrew}\\p{Armenian}\\p{Hangul}\\p{Common}]+/u', '', $new)) < mb_strlen($new) / 2) {
			// 半分以上が全角記号とそれっぽいもの
			output_err('スパム判定', '特殊な記号や文字が多すぎます。');
		}
	
		// 空行とスペースを消して各行をソートする
		$new = preg_replace('/\\n{2,}/s', "\n", $_POST['msg']);
		$new = str_replace(array(' ', '　'), '', $new);
		$new = explode("\n", $new);
		sort($new);
		$new = implode("\n", $new);

		include('./lib/diff.php');

		// 過去5分とスコア-3以下の過去12時間が対象（デリートのみでパージは対象外）
		$sams = $db->query_get_all('SELECT DISTINCT msg, score FROM post WHERE dt_del IS NULL AND id_thread <> "tcw5wa1w" AND (dt_add >= ? OR (score <=3 AND dt_add >= ?))', time() - 60 * 5, time() - 60 * 60 * 12);
		foreach($sams as $sam) {
			$old = preg_replace('/\\n{2,}/s', "\n", $sam['msg']);
			$old = str_replace(array(' ', '　'), '', $old);
			$old = explode("\n", $old);
			sort($old);
			$old = implode("\n", $old);

			if (mb_strlen($old) >= 30) {
				if ($new == $old) {
					// 完全一致
					if ($sam['score'] >= 3)
						output_err('スパム判定', 'スパムサンプルに類似した書き込みです。');
					else
						output_err('スパム判定', '過去5分以内に類似した書き込みが見つかりました。（一致率：100%）');
				}

				$diff = new TextDiff($old, $new);
				$diff = $diff->getData();

				$chlen = 0;
				foreach ($diff as $d) {
					if (!$d['differ'])
						continue;

					if (count($d['words'])) {
						foreach ($d['words'] as $w) {
							if (is_array($w))
								$chlen += max(mb_strlen($w['source']), mb_strlen($w['change']));
						}
					} else if ($d['source'] != $d['change']) {
						$chlen += max(mb_strlen($w['source']), mb_strlen($w['change']));
					}
				}

				if ($chlen / mb_strlen($new) * 100 < 10) {
					// 差分確認でほぼ一致
					if ($sam['score'] >= 3) {
						output_err('スパム判定', 'スパムサンプルに類似した書き込みです。');
					} else {
						output_err('スパム判定', '過去5分以内に類似した書き込みが見つかりました。（一致率：' . number_format(100 - $chlen / mb_strlen($new) * 100)
							. '%）<br><br><span style="color:gray">' . nl2br($old) . '</span>');
					}
				}
			}
		}
	}
}


header('Content-Type: text/html; charset=UTF-8');
header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
header('Last-Modified: ' . gmdate('D, j M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');


if (isset($_GET['__A'])) {
	// 書き込み
	if ($_GET['__A'] == 'write') {
		if (MAX_POST_PER_HOUR > 0) {
			$cou = (int)$db->query_get_one('SELECT COUNT(id) FROM post WHERE dt_add > ?', time() - 60 * 60);
			if ($cou > MAX_POST_PER_HOUR) {
				// 通知
				if (NOTY_TRIGGER_MAX_POST) {
					send_telegram('MAX_POST', "MSG:" . @$_POST['msg']);
				}

				output_err('投稿制限', '過去60分あたりの最大投稿数（' . MAX_POST_PER_HOUR . '）をオーバーしました。');
			}
		}
		
		if ($db->query_get_one('SELECT COUNT(id) FROM thread WHERE id = ?', $_GET['__T']) == '0') {
			output_err('エラー', '指定されたスレッドは存在しません。');
		}

		if ($db->query_get_one('SELECT COUNT(id) FROM post WHERE id_thread = ?', $_GET['__T']) >= MAX_POST) {
			output_err('投稿制限', '最大書き込み数（' . MAX_POST . '）の上限に達しました。');
		}

		if (SYS_CAPTCHA_TYPE == 1) {
			$_POST['ans'] = mb_convert_kana(@$_POST['ans'], 'n');

			if ($db->query_get_one('SELECT COUNT(id) FROM capt WHERE id = ? AND ans = ?', @$_POST['capt'], $_POST['ans']) == '0') {
				output_err('投稿制限', 'キャプチャが間違っています。');
			}
		} else if (SYS_CAPTCHA_TYPE == 2) {
			$_POST['ans'] = mb_convert_kana(@$_POST['ans'], 'r');
			$_POST['ans'] = base_convert(bin2hex($_POST['ans']), 16, 10);

			if ($db->query_get_one('SELECT COUNT(id) FROM capt WHERE id = ? AND ans = ?', @$_POST['capt'], $_POST['ans']) == '0') {
				output_err('投稿制限', 'キャプチャが間違っています。');
			}
		}

		$_POST['name'] = mb_trim(@$_POST['name']);
		$_POST['msg'] = str_replace("\r", "", mb_trim(@$_POST['msg']));

		if (@mb_strlen($_POST['name']) > 50) {
			output_err('投稿制限', '名前が長すぎます。（50文字まで）');
		}

		if (@mb_strlen($_POST['msg']) > 10000) {
			output_err('投稿制限', '内容が長すぎます。（10000文字まで）');
		}

		if (@mb_strlen($_POST['msg']) == 0) {
			output_err('投稿制限', '内容を入力して下さい。');
		}

		if (SYS_USE_IMAGE_UPLOAD && isset($_FILES['img']) && $_FILES['img']['name']) {
			if ($_FILES['img']['size'] == '0') {
				output_err('投稿エラー', '画像ファイルのサイズエラー。');
			}

			$type = exif_imagetype($_FILES['img']['tmp_name']);
			if ($type != IMAGETYPE_GIF && $type != IMAGETYPE_JPEG && $type != IMAGETYPE_PNG && $type != IMAGETYPE_BMP && $type != IMAGETYPE_WEBP) {
				output_err('投稿エラー', '未対応の画像形式です。');
			}
		}

		// ユーザーフック
		$udata = true;
		if (function_exists('hook_post_new')) {
			$udata = array('name' => $_POST['name'], 'msg' => $_POST['msg']);
			$udata = hook_post_new($udata);

			if (is_string($udata)) {
				// 文字列が返ってきたらエラー表記
				output_err('フック関数', $udata);
			} else if (is_array($udata)) {
				// 配列は一端戻す
				$_POST['name'] = @$udata['name'];
				$_POST['msg'] = @$udata['msg'];
			}
			
			if (!is_bool($udata)) {
				// bool型以外はエラー（falseの婆はエラーを出さずに投稿をキャンセルする）
				output_err('フック関数', '内部エラー');
			}
		}

		// 書き込み
		if ($udata) {
			post_check();

			// sage確認
			if ($_POST['name'] == 'sage' || strtolower(mb_substr($_POST['name'], 0, 5)) == 'sage@') {
				$name = mb_substr($_POST['name'], 4);
				$time = 0 - time(); // sageはマイナス
			} else {
				$name = $_POST['name'];
				$time = time();
			}

			// ID確認
			$id_user = null;
			if (mb_substr($name, 0, 1) == '@' || mb_substr($name, 0, 1) == '＠') {
				if (mb_strlen($name) == 1) {
					// @1文字の場合はユーザーIDでの投稿とする
					if (!@$_SESSION['id']) {
						output_err('ログイン確認', '＠指定がされましたが現在ログインしていません。');
					}
	
					$id_user = $_SESSION['id'];
					$name = '';
				} else {
					// @以降がある場合は@を省いた部分を名前と判断する
					$id_user = null;
					$name = preg_replace('/^[@＠]*(.*)$/u', '$1', $name);
				}
			}

			$db->exec('INSERT INTO post (id_thread, id_user, name, msg, dt_add) VALUES (?, ?, ?, ?, ?)', $_GET['__T'], $id_user, name_replace($name), $_POST['msg'], $time);

			if (SYS_USE_IMAGE_UPLOAD && isset($_FILES['img']) && $_FILES['img']['name']) {
				$y = date('Y', abs($time));

				$imgname = __DIR__ . '/img/' . $y . '/upload' . $db->get_last_id();
				if (!file_exists(__DIR__ . '/img/' . $y)) {
					@mkdir(__DIR__ . '/img/' . $y);
					@chmod(__DIR__ . '/img/' . $y, 0777);
				}

				if (move_uploaded_file($_FILES['img']['tmp_name'], $imgname)) {
					chmod($imgname, 0777);
					img_save($imgname, $imgname . '.s', 300);
					chmod($imgname . '.s', 0777);
				}
			}

			if ($time > 0) {
				// sageはスレッドの更新日時を変更しない
				$db->exec('UPDATE thread SET dt_upd = ? WHERE id = ?', $time, $_GET['__T']);
			}
		}

		// 使ったキャプチャを削除
		$db->exec('DELETE FROM capt WHERE id = ?', $_POST['capt']);

		// 定期で古いキャプチャの削除とVACUUM
		if (!rand(0, 19)) {
			$db->exec('DELETE FROM capt WHERE dt_add < ?', time() - 60 * 60 * 12);
			if (!MYSQL_HOST)
				$db->exec('VACUUM');
		}
		
		// 通知
		if (NOTY_TRIGGER_NEW_POST && $udata) {
			send_telegram('NEW_POST', "MSG:" . @$_POST['msg']);
		}

		if ($_POST['thread'])
			header('Location: ' . BASEDIR . $_GET['__T'] . '/l50');
		else
			header('Location: ' . BASEDIR . '#' . $_GET['__T']);

		exit();
	}

	// スレッド作成
	if ($_GET['__A'] == 'new') {
		if (MAX_THREAD_PER_DAY > 0) {
			$cou = (int)$db->query_get_one('SELECT COUNT(id) FROM thread WHERE dt_add > ?', time() - 60 * 60 * 24);
			if ($cou > MAX_THREAD_PER_DAY) {
				if (NOTY_TRIGGER_MAX_THREAD) {
					send_telegram('MAX_THREAD', "TITLE:" . @$_POST['title'] . "\nMSG:" . @$_POST['msg']);
				}

				output_err('投稿制限', '過去24時間あたりの最大作成数（' . MAX_THREAD_PER_DAY . '）をオーバーしました。');
			}
		}
		
		if (SYS_CAPTCHA_TYPE == 1) {
			$_POST['ans'] = mb_convert_kana(@$_POST['ans'], 'n');
			if ($db->query_get_one('SELECT COUNT(id) FROM capt WHERE id = ? AND ans = ?', @$_POST['capt'], $_POST['ans']) == '0') {
				output_err('投稿制限', 'キャプチャが間違っています。');
			}
		} else {
			$_POST['ans'] = mb_convert_kana(@$_POST['ans'], 'r');
			$_POST['ans'] = base_convert(bin2hex($_POST['ans']), 16, 10);
			if ($db->query_get_one('SELECT COUNT(id) FROM capt WHERE id = ? AND ans = ?', @$_POST['capt'], $_POST['ans']) == '0') {
				output_err('投稿制限', 'キャプチャが間違っています。');
			}
		}

		$_POST['title'] = mb_trim(@$_POST['title']);
		$_POST['name'] = mb_trim(@$_POST['name']);
		$_POST['msg'] = str_replace("\r", "", mb_trim(@$_POST['msg']));

		if (@mb_strlen($_POST['title']) > 50) {
			output_err('投稿制限', 'タイトルが長すぎます。（50文字まで）');
		}

		if (@mb_strlen($_POST['title']) == 0) {
			output_err('投稿制限', 'タイトルを入力して下さい。');
		}

		if (@mb_strlen($_POST['name']) > 50) {
			output_err('投稿制限', '名前が長すぎます。（50文字まで）');
		}

		if (@mb_strlen($_POST['msg']) > 10000) {
			output_err('投稿制限', '内容が長すぎます。（10000文字まで）');
		}

		if (@mb_strlen($_POST['msg']) == 0) {
			output_err('投稿制限', '内容を入力して下さい。');
		}

		if (SYS_USE_IMAGE_UPLOAD && isset($_FILES['img']) && $_FILES['img']['name']) {
			if ($_FILES['img']['size'] == '0') {
				output_err('投稿エラー', '画像ファイルのサイズエラー。');
			}

			$type = exif_imagetype($_FILES['img']['tmp_name']);
			if ($type != IMAGETYPE_GIF && $type != IMAGETYPE_JPEG && $type != IMAGETYPE_PNG && $type != IMAGETYPE_BMP && $type != IMAGETYPE_WEBP) {
				output_err('投稿エラー', '未対応の画像形式です。');
			}
		}

		// ユーザーフック
		$udata = true;
		if (function_exists('hook_thread_new')) {
			$udata = array('title' => $_POST['title'], 'name' => $_POST['name'], 'msg' => $_POST['msg']);
			$udata = hook_thread_new($udata);

			if (is_string($udata)) {
				// 文字列が返ってきたらエラー表記
				output_err('フック関数', $udata);
			} else if (is_array($udata)) {
				// 配列は一端戻す
				$_POST['title'] = @$udata['title'];
				$_POST['name'] = @$udata['name'];
				$_POST['msg'] = @$udata['msg'];
			}
			
			if (!is_bool($udata)) {
				// bool型以外はエラー（falseの婆はエラーを出さずに投稿をキャンセルする）
				output_err('フック関数', '内部エラー');
			}
		}

		// 書き込み
		if ($udata) {
			$time = time();
			post_check();
			
			$id_user = null;
			if (mb_substr($_POST['name'], 0, 1) == '@' || mb_substr($_POST['name'], 0, 1) == '＠') {
				if (mb_strlen($_POST['name']) == 1) {
					// @1文字の場合はユーザーIDでの投稿とする
					if (!@$_SESSION['id']) {
						output_err('ログイン確認', '＠指定がされましたが現在ログインしていません。');
					}
	
					$id_user = $_SESSION['id'];
					$_POST['name'] = '';
				} else {
					$id_user = null;
					$_POST['name'] = preg_replace('/^[@＠]*(.*)$/u', '$1', $_POST['name']);
				}
			}

			while (true) {
				$id = str_rand(8);
				if ($db->exec('INSERT INTO thread (id, title, pam, pin, dt_add, dt_upd) VALUES (?, ?, 0, 0, ?, ?)', $id, $_POST['title'], $time, $time) == 1) {
					$db->exec('INSERT INTO post (id_thread, id_user, name, msg, dt_add) VALUES (?, ?, ?, ?, ?)', $id, $id_user, name_replace($_POST['name']), $_POST['msg'], $time);
					break;
				}
			}

			if (SYS_USE_IMAGE_UPLOAD && isset($_FILES['img']) && $_FILES['img']['name']) {
				$imgname = __DIR__ . '/img/' . date('Y', $time) . '/upload' . $db->get_last_id();
	
				if (move_uploaded_file($_FILES['img']['tmp_name'], $imgname)) {
					chmod($dir . $imgname, 0777);
					img_save($dir . $imgname, $imgname . '.s', 300);
					chmod($dir . $imgname . '.s', 0777);
				}
			}
		}

		// 使ったキャプチャを削除
		$db->exec('DELETE FROM capt WHERE id = ?', $_POST['capt']);

		// 通知
		if (NOTY_TRIGGER_NEW_THREAD && $udata) {
			send_telegram('NEW_THREAD', "TITLE:" . @$_POST['title'] . "\nMSG:" . @$_POST['msg']);
		}

		header('Location: ' . BASEDIR . $id);
		exit();
	}

	// スレの長さ指定
	if (preg_match('/^[lL][0-9]+$/', $_GET['__A'])) {
		$show_max = substr($_GET['__A'], 1);
	} else if ($_GET['__T'] != '0') {
		$show_max = MAX_POST;
	}

	// スレの指定
	if ($_GET['__A'] != '0' && preg_match('/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/', $_GET['__A'])) {
		$res = explode(',', $_GET['__A']);

		foreach ($res as $r) {
			preg_match('/^([0-9]+)(-([0-9]+))?$/', $r, $ms);
			if (isset($ms[3])) {
				for ($i = min($ms[1], $ms[3]); $i <= max($ms[1], $ms[3]); $i++) {
					$show_no[] = $i;
				}
			} else {
				$show_no[] = (int)$ms[1];
			}
		}

		$show_no = array_unique($show_no);
		sort($show_no);
		if ($show_no[0] == 0)
			unset($show_no[0]);
	}

	if (!@$_GET['__A']) {
		$_GET['__A'] = '0';
	}
}

// 表示モード
if (@$_GET['__T'] == '0' && @$_GET['__A'] == 'list')
	$view_mode = 'list'; // スレッド一覧
else if (@$_GET['__T'] == '0' && @$_GET['__A'] == 'archive')
	$view_mode = 'archive'; // アーカイブ一覧
else if (@$_GET['__T'] == '0' && @$_GET['__A'] == 'search')
	$view_mode = 'search'; // 検索画面（未実装）
else if (@$_GET['__T'] && @$_GET['__T'] != '0')
	$view_mode = 'thread'; // 特定のスレ
else
	$view_mode = 'index'; // TOP

if ($view_mode == 'thread' && strtolower(@$_GET['__P']) == 'pdf') {
	// PDFダウンロード
	if ($db->query_get_one('SELECT COUNT(id) FROM thread WHERE dt_del IS NULL AND id = ?', @$_GET['__T']) == '0')
		exit();

	output_pdf(@$_GET['__T'], @$_GET['__A']);
}

$show_morelink = false;
if (@$_GET['__T']) {
	$threads = $db->query_get_all('SELECT thread.id as id, thread.dt_upd, thread.pin, thread.pam, thread.comment, COUNT(thread.id) as cou, thread.title FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE post.dt_del IS NULL AND thread.dt_del IS NULL AND thread.id IS NOT NULL AND thread.pin <> 3 AND thread.id = ? GROUP BY id_thread', $_GET['__T']);
	if (!$threads) {
		// 存在しないスレ
		header('Location: ' . BASEDIR);
		exit();
	}

	if (strtolower(@$_GET['__P']) == 'json') {
		// JSONダウンロード
		$datas = $db->query_get_all('SELECT post.name, post.msg, post.dt_add, post.dt_del, user.name AS u_name, user.dt_del AS u_del FROM post LEFT JOIN user ON post.id_user = user.id LEFT JOIN thread ON post.id_thread = thread.id WHERE id_thread = ? AND (thread.pam = 0 OR thread.pam = 1) ORDER BY post.id LIMIT ' . $show_max, $threads[0]['id']);
		output_json($threads[0]['id'], $threads[0]['title'], $datas);
	} else if (strtolower(@$_GET['__P']) == 'rss') {
		// RSSダウンロード
		$datas = $db->query_get_all('SELECT post.name, post.msg, post.dt_add, post.dt_del, user.name AS u_name, user.dt_del AS u_del FROM post LEFT JOIN user ON post.id_user = user.id LEFT JOIN thread ON post.id_thread = thread.id WHERE id_thread = ? AND (thread.pam = 0 OR thread.pam = 1) ORDER BY post.id LIMIT ' . $show_max, $threads[0]['id']);
		output_rss($threads[0]['id'], $threads[0]['title'], $datas);
	}
} else if (!@$_GET['__T'] || @$_GET['__T'] == '0') {
	if (strtolower(@$_GET['__A']) == 'rss') {
		// RSSダウンロード
		$datas = $db->query_get_all('SELECT post.name, post.msg, post.dt_add, post.dt_del, post.id_thread, thread.title AS title, user.name AS u_name, user.dt_del AS u_del FROM post LEFT JOIN thread ON post.id_thread = thread.id LEFT JOIN user ON post.id_user = user.id WHERE (thread.pam = 0 OR thread.pam = 1) ORDER BY post.id DESC LIMIT 100');
		output_rss_all($datas);
	} else if ($view_mode == 'list') {
		// スレッド一覧画面
		$archive_cou = (int)$db->query_get_one('SELECT COUNT(thread.id) as cou FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.dt_del IS NULL AND thread.pin <> 3 AND thread.id IS NOT NULL GROUP BY id_thread HAVING cou >= ? AND thread.dt_upd < ? ORDER BY thread.dt_upd DESC', INDEX_SHOW_THREAD, time() - 60 * 60 * 24 * 30);
		$threads = $db->query_get_all('SELECT thread.id as id, thread.dt_upd, thread.pin, thread.pam, thread.comment, COUNT(thread.id) as cou, thread.title FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.pam <> 9 AND thread.dt_del IS NULL AND thread.pin <> 3 AND thread.id IS NOT NULL GROUP BY id_thread HAVING NOT (cou >= ? AND thread.dt_upd < ?) ORDER BY thread.dt_upd DESC', INDEX_SHOW_THREAD, time() - 60 * 60 * 24 * 30);
	} else if ($view_mode == 'archive') {
		// アーカイブ一覧画面
		$threads = $db->query_get_all('SELECT thread.id as id, thread.dt_upd, thread.pin, thread.pam, thread.comment, COUNT(thread.id) as cou, thread.title FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.pam <> 9 AND thread.dt_del IS NULL AND thread.pin <> 3 AND thread.id IS NOT NULL GROUP BY id_thread HAVING cou >= ? AND thread.dt_upd < ? ORDER BY thread.dt_upd DESC', INDEX_SHOW_THREAD, time() - 60 * 60 * 24 * 30);
	} else {
		// 上固定スレ
		$threads_top = $db->query_get_all('SELECT thread.id as id, thread.dt_upd, thread.pin, thread.pam, thread.comment, COUNT(thread.id) as cou, thread.title FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.pam <> 9 AND thread.dt_del IS NULL AND thread.pin = 1 GROUP BY id_thread HAVING NOT (cou >= ? AND thread.dt_upd < ?) ORDER BY thread.dt_upd DESC', INDEX_SHOW_THREAD, time() - 60 * 60 * 24 * 30);
		// 下固定スレ
		$threads_bottom = $db->query_get_all('SELECT thread.id as id, thread.dt_upd, thread.pin, thread.pam, thread.comment, COUNT(thread.id) as cou, thread.title FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.pam <> 9 AND thread.dt_del IS NULL AND thread.pin = 2 GROUP BY id_thread HAVING NOT (cou >= ? AND thread.dt_upd < ?) ORDER BY thread.dt_upd DESC', INDEX_SHOW_THREAD, time() - 60 * 60 * 24 * 30);
		// その他の通常スレ（1つ多く取っておく）
		$threads = $db->query_get_all('SELECT thread.id as id, thread.dt_upd, thread.pin, thread.pam, thread.comment, COUNT(thread.id) as cou, thread.title FROM post LEFT JOIN thread ON post.id_thread = thread.id WHERE thread.pam <> 9 AND thread.dt_del IS NULL AND thread.id IS NOT NULL AND thread.pin = 0 GROUP BY id_thread HAVING NOT (cou = ? AND thread.dt_upd < ?) ORDER BY thread.dt_upd DESC LIMIT ' . (INDEX_SHOW_THREAD - count($threads_top) - count($threads_bottom) + 1), INDEX_SHOW_THREAD, time() - 60 * 60 * 24 * 30);

		// INDEX_SHOW_THREAD以上存在するかの確認
		if (count($threads_top) + count($threads_bottom) + count($threads) > INDEX_SHOW_THREAD) {
			array_pop($threads);
			$show_morelink = true;
		}

		// 結合
		$threads = array_merge($threads_top, $threads, $threads_bottom);

		if (strtolower(@$_GET['__A']) == 'json') {
			output_json_all($threads);
		}
	}
}

// 一覧表示
$thread_cou = 0;
$thread_list = '';
foreach($threads as $thread) {
	$thread_cou++;

	if ($thread['pin'] == '1')
		$thread['pin'] = '↑';
	else if ($thread['pin'] == '2')
		$thread['pin'] = '↓';
	else
		$thread['pin'] = '';

	if ($view_mode == 'list' || $view_mode == 'archive')
		$thread_list .= '<div class="thread_title"><a href="' . BASEDIR . $thread['id'] . '">' . htmlspecialchars($thread['title']) . '<span class="thread_count">' . $thread['cou'] . '</span><span class="thread_pin">' . $thread['pin'] . '</span></a></div>';
	else
		$thread_list .= '<div class="thread_title"><a href="' . BASEDIR . '#' . $thread['id'] . '">' . htmlspecialchars($thread['title']) . '<span class="thread_count">' . $thread['cou'] . '</span><span class="thread_pin">' . $thread['pin'] . '</span></a></div>';
}

if ($show_morelink) {
	// INDEXでかつスレッド一覧でなければスレッド一覧へのリンクを付ける
	$thread_list .= '<br><div class="thread_more" style="text-align:right;display:block"><a href="' . BASEDIR . '0/list">全てのスレッド</a></div>';
} else if ($view_mode == 'list' && $archive_cou > INDEX_SHOW_THREAD) {
	// LISTでかつアーカイブが存在すればリンクを付ける
	$thread_list .= '<br><div class="thread_more" style="text-align:right;display:block"><a href="' . BASEDIR . '0/archive">アーカイブされたスレッド</a></div>';
}

if ($view_mode == 'list' || $view_mode == 'archive') {
	// スレッド一覧の場合はスレッドを一切表示しない
	$threads = array();
}

if ($view_mode == 'index' || $view_mode == 'thread') {
	$ph = 'QUOTE：行頭に>を付ける&#13;&#10;';
	if (SYS_USE_MARKDOWN) {
		$ph .= 'BOLD：文字の前後を***で囲む&#13;&#10;DEL：文字の前後を~~~で囲む&#13;&#10;';
	}
	if (SYS_USE_IMAGE_VIEW) {
		$ph .= 'IMAGE：画像URLの前に!を付ける&#13;&#10;';
	}
	if (SYS_USE_PRE) {
		$ph .= 'PRE：コードの上下を```(ﾊﾞｯｸｸｵｰﾄ)で囲む&#13;&#10;';
	}
	if (SYS_USE_MORE) {
		$ph .= 'MORE：---で区切るとそれ以降はクリックしないと見えない&#13;&#10;';
	}

//echo bin2hex(mb_convert_encoding('1', 'UTF-16BE', 'UTF-8'));
//echo mb_convert_encoding(hex2bin('30420031'), 'UTF-8', 'UTF-16BE');
//あ3042
//1=0031

	for ($c = 1; $c <= 3; $c++) {
		if (SYS_CAPTCHA_TYPE == 1) {
			// 整数
			$capt_drw = $capt_ans = rand(1000, 9999);
		} else if (SYS_CAPTCHA_TYPE == 2) {
			// アルファベッド
			$capt_ans = '';
			for ($n = 0; $n < 4; $n++) {
				$capt_ans .= base_convert(rand(65, 90), 10, 16);
			}

			$capt_drw = hex2bin($capt_ans);
			$capt_ans = base_convert($capt_ans, 16, 10);
		}

		// キャプチャの画像と正解を保存
		while (true) {
			$capt_id[$c] = str_rand(8);
			if ($db->exec('INSERT INTO capt (id, ans, dt_add) VALUES (?, ?, ?)', $capt_id[$c], $capt_ans, time()) == 1)
				break;
		}

		// キャプチャ画像の作成
		$img = imagecreatetruecolor(70, 26);
		imagefilledrectangle($img, 0, 0, 69, 26, SYS_THEME[USER_THEME]['capt_bg']);

		// 色の明るさ調整
		$r = SYS_THEME[USER_THEME]['capt_fg'] >> 16;
		$g = SYS_THEME[USER_THEME]['capt_fg'] >> 8 & 0xff;
		$b = SYS_THEME[USER_THEME]['capt_fg'] & 0xff;
		$col = imagecolorallocatealpha($img, $r, $g, $b, 20);

		// ノイズ描画
		for ($i = 0; $i < 8; $i++) {
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
		imagettftext($img, 16, $ang * 2, $left, 19 + $ang, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_drw);
		imagettftext($img, 16, $ang * 2, $left + 1, 19 + $ang, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_drw);
		imagettftext($img, 16, $ang * 2, $left, 19 + $ang + 1, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_drw);
		imagettftext($img, 16, $ang * 2, $left + 1, 19 + $ang + 1, SYS_THEME[USER_THEME]['capt_fg'], __DIR__ . '/lib/MyricaM.TTC', $capt_drw);

		// 画像作成
		ob_start();
		imagejpeg($img, null, 20);
		$capt_img[$c] = 'data:image/jpeg;base64,' . base64_encode(ob_get_contents());
		ob_end_clean();
		imagedestroy($img);
	}
}
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<?php
if (@$_GET['__T']) {
	// スレッド指定
	echo '<title>' . htmlspecialchars($threads[0]['title']) . ' | ' . htmlspecialchars(STD_BBS_NAME) . '</title>';
	if (SYS_USE_OUTPUT_RSS)
		echo '<link rel="alternate" type="application/rss+xml" href="http://' . $_SERVER['HTTP_HOST'] . BASEDIR . $_GET['__T'] . '/0/rss" title="' . htmlspecialchars(STD_BBS_NAME) . ' - ' . htmlspecialchars($threads[0]['title']) . '" />';
} else {
	// INDEX
	echo '<title>' . htmlspecialchars(STD_BBS_NAME) . '</title>';
	if (SYS_USE_OUTPUT_RSS)
		echo '<link rel="alternate" type="application/rss+xml" href="http://' . $_SERVER['HTTP_HOST'] . BASEDIR . '0/0/rss" title="' . htmlspecialchars(STD_BBS_NAME) . ' - ハイライト" />';
}
?>
<link rel="icon" type="image/png" href="<?=BASEDIR?>favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?=BASEDIR?>favicon.png">
<link rel="stylesheet" href="<?=BASEDIR?>theme/<?=USER_THEME?>/style.css">
<?php
	if (file_exists('./theme/' .  USER_THEME . '/custom.css'))
		echo '<link rel="stylesheet" href="' . BASEDIR . 'theme/' . USER_THEME . '/custom.css">';
?>

<script>
	var BASEDIR = '<?=BASEDIR?>';
	var POST_MAX_STR = <?=(int)@POST_MAX_STR?>;
	var POST_MAX_LINE = <?=(int)@POST_MAX_LINE?>;
</script>
<script src="<?=BASEDIR?>js/jquery-3.5.1.min.js"></script>
<script src="<?=BASEDIR?>js/script.js"></script>
</head>
<body>
<div class="layoutbox header">
<?php @include('./data/header.php'); ?>
</div>
<?php
if (!@$_GET['__T']) {
	if ($view_mode == 'index') {
		// TOPのスレ一覧
?>
<div class="layoutbox thread_list">
<?=$thread_list?>
</div>
<?php
		if (defined('STD_INFORMATION') && STD_INFORMATION) {
			// 情報がある場合は表示する
?>
<div class="layoutbox information pconly"><?=STD_INFORMATION?></div>
<?php
		}
	} else {
		// 全てのスレ
?>
<div class="layoutbox thread_list_all">
<?=$thread_list?>
</div>
<?php
	}
}

if ($view_mode == 'index') {
?>
<div class="layoutmain">
<?php
}

// ログイン中は書き込みフォームの名前部分にその旨を表示する
if (@$_SESSION['id'])
	$user_ph = $_SESSION['name'] . 'でログイン中　@のみ(ﾀﾞﾌﾞﾙｸﾘｯｸ)でアカウント投稿';
else
	$user_ph = '';

$thread_cou = 0;
foreach ($threads as $thread) {
	$thread_cou++;
	if ($thread_cou > INDEX_SHOW_THREAD)
		break;
	
	if ($thread['pin'] == '1')
		$thread['pin'] = '↑';
	else if ($thread['pin'] == '2')
		$thread['pin'] = '↓';
	else
		$thread['pin'] = '';

	// データの準備
	if (count($show_no)) {
		// レス指定
		$datas = array();
		$tcou = $db->query_get_one('SELECT COUNT(id) FROM post WHERE id_thread = ? ORDER BY id DESC', $_GET['__T']);
		$datas_src = $db->query_get_all('SELECT post.id, post.name, post.msg, post.score, post.dt_add, post.dt_del, user.name AS u_name, user.dt_del AS u_del FROM post LEFT JOIN user ON post.id_user = user.id WHERE id_thread = ? ORDER BY post.id LIMIT ' . ($show_no[0] - 1) . ', ' . ($show_no[count($show_no) - 1] - $show_no[0] + 1), $thread['id']);

		foreach ($show_no as $row) {
			if (!isset($datas_src[$row - $show_no[0]]))
				break;

			if ($datas_src[$row - $show_no[0]]['u_name'] && !$datas_src[$row - $show_no[0]]['u_del'])
				$datas_src[$row - $show_no[0]]['name'] = '@' . $datas_src[$row - $show_no[0]]['u_name'];

			$datas[$row] = $datas_src[$row - $show_no[0]];
		}
	} else {
		// 全て
		$datas = array();
		$tcou = $db->query_get_one('SELECT COUNT(id) FROM post WHERE id_thread = ? ORDER BY id DESC', $thread['id']);
		$datas_src = $db->query_get_all('SELECT post.id, post.name, post.msg, post.score, post.dt_add, post.dt_del, user.name AS u_name, user.dt_del AS u_del FROM post LEFT JOIN user ON post.id_user = user.id WHERE id_thread = ? ORDER BY post.id DESC LIMIT ' . $show_max, $thread['id']);
		$datas_src = array_reverse($datas_src);

		if (count($datas_src) == $show_max && $tcou != $show_max) {
			// $show_max以上の投稿がある
			for ($i = 1; $i <= count($datas_src); $i++) {
				if ($datas_src[$i - 1]['u_name'] && !$datas_src[$i - 1]['u_del'])
					$datas_src[$i - 1]['name'] = '@' . $datas_src[$i - 1]['u_name'];

				$datas[$i + $tcou - $show_max] = $datas_src[$i - 1];
			}

			// 1だけ先頭に追加
			$data_top = $db->query_get('SELECT post.id, post.name, post.msg, post.score, post.dt_add, post.dt_del, user.name AS u_name, user.dt_del AS u_del FROM post LEFT JOIN user ON post.id_user = user.id WHERE id_thread = ? ORDER BY post.id LIMIT 1', $thread['id']);

			if ($data_top['u_name'] && !$data_top['u_del'])
				$data_top['name'] = '@' . $data_top['u_name'];

			$datas[1] = $data_top;
			ksort($datas);
		} else {
			// $show_max以下しか投稿がない（全て表示）
			for ($i = 1; $i <= count($datas_src); $i++) {
				if ($datas_src[$i - 1]['u_name'] && !$datas_src[$i - 1]['u_del'])
					$datas_src[$i - 1]['name'] = '@' .  $datas_src[$i - 1]['u_name'];

				$datas[$i] = $datas_src[$i - 1];
			}
		}

		if (isset($datas[MAX_POST])) {
			$datas[MAX_POST + 1] = ['name' => '', 'msg' => 'このスレは' . MAX_POST . 'を越えました。新しいスレを作成して下さい。', 'dt_add' => ''];
		}
	}
?>
<div class="layoutbox thread">
<h2 class="thread_title"><a href="<?=BASEDIR?><?=$thread['id']?>" name="<?=$thread['id']?>"><?=htmlspecialchars($thread['title'])?></a><span class="thread_count"><?=$tcou?></span><span class="thread_pin"><?=$thread['pin']?></span></h2>
<div class="thread_comment"><?=$thread['comment']?></div>
<?php
	if ($is_admin) {
		// 管理者用メニュー（上）
?>
<form action="<?=BASEDIR?>admincmd.php" method="post">
<input type="hidden" name="thread" value="<?=$thread['id']?>">
<input type="hidden" name="burl" value="<?=$_SERVER['REQUEST_URI']?>">
<div class="admin_box">
<div><select name="thread_cmd">
<option value=""></option><option value="hide">ハイド</option><option value="del">デリート</option><option value="purge">パージ</option>
</select></div>
<div><input type="checkbox" id="chk_thread_<?=$thread['id']?>" name="chk_thread" value="1"> <label for="chk_thread_<?=$thread['id']?>">確認</label></div>
<div><input type="submit" name="submit" value="スレッド操作"></div>
</div>
<br>
<?php
	}

	// データの表示
	foreach ($datas as $id => $data) {
		$msgurl = 'http://' . $_SERVER['SERVER_NAME'] . BASEDIR . $thread['id'] . '/#' . $id;

		// パージされている場合は飛ばす
		if (!$data['msg'])
			continue;

		$data['dt_add_y'] = date('Y', $data['dt_add']);

		// 削除されている場合は飛ばす（管理者を除く）
		if ((isset($data['dt_del']) && $data['dt_del'] && !$is_admin) && (!defined('SYS_DELETE_STRING') || SYS_DELETE_STRING == ''))
			continue;

		// ユーザーフィルター（上書き）
		if (function_exists('filter_msg_override')) {
			$udata = array('id' => $id, 'name' => $data['name'], 'ctime' => $data['dt_add'], 'dtime' => $data['dt_del'], 'msg' => $data['msg']);
			$udata = filter_msg_before($udata);

			$id = @$udata['id'];
			$data['name'] = @$udata['name'];
			$data['dt_add'] = @$udata['ctime'];
			$data['dt_del'] = @$udata['dtime'];
			$data['msg'] = @$udata['msg'];
		} else {
			// ユーザーフィルター（前処理）
			if (function_exists('filter_msg_before')) {
				$udata = array('id' => $id, 'name' => $data['name'], 'ctime' => $data['dt_add'], 'dtime' => $data['dt_del'], 'msg' => $data['msg']);
				$udata = filter_msg_before($udata);

				$id = @$udata['id'];
				$data['name'] = @$udata['name'];
				$data['dt_add'] = @$udata['ctime'];
				$data['dt_del'] = @$udata['dtime'];
				$data['msg'] = @$udata['msg'];
			}

			if ($id <= MAX_POST) {
				if (!$data['dt_del']) {
					// 通常のデータ
					$is_admin_write = false;
					if ($data['name'] == '') {
						$data['name'] = '<span class="msg_name name_anon">' . htmlspecialchars(STD_ANON_NAME) . '</span>';
					} else if (mb_substr($data['name'], -1) == '◆') {
						$is_admin_write = true; // 管理者の投稿にはスコアを表示させない
						$data['name'] = '<span class="msg_name name_admin">' . htmlspecialchars($data['name']) . '</span>';
					} else if (mb_strpos($data['name'], '◆') !== false) {
						$data['name'] = '<span class="msg_name name_cap">' . htmlspecialchars($data['name']) . '</span>';
					} else if (mb_substr($data['name'], 0, 1) == '@') {
						$data['name'] = '<span class="msg_name name_user">' . htmlspecialchars($data['name']) . '</span>';
					} else {
						$data['name'] = '<span class="msg_name name_std">' . htmlspecialchars($data['name']) . '</span>';
					}

					if (((int)$data['dt_add']) < 0) {
						$data['dt_add'] = abs((int)$data['dt_add']);
						$data['dt_add'] = date_jp(SYS_DATE_FORMAT, $data['dt_add']) . ' sage';
					} else {
						$data['dt_add'] = date_jp(SYS_DATE_FORMAT, $data['dt_add']);
					}


					$data['msg'] = htmlspecialchars($data['msg']);

					// スコア
					if (SYS_USE_USER_REGIST) {
						$data['score'] = (int)@$data['score'];
						if ($data['score'] <= -6) {
							$msg_star = '';
							$msg_score = 'msg_score_m2';
						} else if ($data['score'] <= -3) {
							$msg_star = '';
							$msg_score = 'msg_score_m1';
						} else if ($data['score'] >= 9) {
							$msg_star = '★★★';
							$msg_score = 'msg_score_p3';
						} else if ($data['score'] >= 6) {
							$msg_star = '★★';
							$msg_score = 'msg_score_p2';
						} else if ($data['score'] >= 3) {
							$msg_star = '★';
							$msg_score = 'msg_score_p1';
						} else {
							$msg_star = '';
							$msg_score = 'msg_score_zero';
						}
					}

					// コードブロック
					if (SYS_USE_PRE) {
						$data['msg'] = preg_replace_callback('/(^|\\n)(```)\\n(.+?)(\n(?<![\\\\])\\2(\\n|$)|$)/s', function($matches2) {
							$matches2[3] = preg_replace('/(^|\\n)\\\\```\\n/', "$1```\n", $matches2[3]);
							$code = '';
							foreach (explode("\n", $matches2[3]) as $line) {
								$code .= '<li>' . $line . '</li>';
							}
							return '</markdown><pre class="msg_pre"><ol>' . $code . '</ol></pre><markdown>';
						}, $data['msg']);
					}

					$data['msg'] = '<markdown>' . $data['msg'] . "</markdown>";

					global $thread_id;
					global $use_more;
					global $view_mode;

					$thread_id = $thread['id'];

					if (strtolower(@$_GET['__P']) == 'noomit')
						$use_more = false;
					else
						$use_more = SYS_USE_MORE && count($show_no) ? false : true;
					
					// コードブロック以外のみにマークダウンを適用
					$data['msg'] = preg_replace_callback('/<markdown>(.*?)<\\/markdown>/s', function($matches) {
						global $thread_id;
						global $use_more;
						global $view_mode;

						$rep = $matches[1];

						// moreの場所を特定
						// 混在しているPREの中の---に反応しないように工夫
						if ($use_more) {
							$rep = preg_replace('/(^|\n)---(\n|$)/', '$1<MORE>$2', $rep, 1);
							$rep = preg_replace('/(^|\n)\\\\---(\n|$)/', '$1---$2', $rep);
						}

						if (SYS_USE_MARKDOWN) {
							// ボールド
							$rep = preg_replace_callback('/(\\\\)?(\\*\\*\\*)([^\\*].*?)(\\\\)?\\2/u', function($matches) {
								if (preg_match('/^(\\*\\*\\*)([^\\*].*?)(?<!\\\\)(\\*\\*\\*)$/', $matches[0]))
									return '<strong>' . $matches[3] . '</strong>';
								else if (preg_match('/^(\\\\)?(\\*\\*\\*)([^\\*].*?)(\\\\)?$/', $matches[0]))
									return $matches[2] . $matches[3] . $matches[2];
							}, $rep);

							// 打ち消し
							$rep = preg_replace_callback('/(\\\\)?(~~~)([^~].*?)(\\\\)?\\2/u', function($matches) {
								if (preg_match('/^(~~~)([^~].*?)(?<!\\\\)(~~~)$/', $matches[0]))
									return '<del>' . $matches[3] . '</del>';
								else if (preg_match('/^(\\\\)?(~~~)([^~].*?)(\\\\)?$/', $matches[0]))
									return $matches[2] . $matches[3] . $matches[2];
							}, $rep);
						}

						// 省略されたURLの補完
						$rep = preg_replace('/(https?:\\/\\/)(\\/)/', '$1' . $_SERVER['SERVER_NAME'] . '$2', $rep);

						// Torとi2pの相互変換
						if (defined('STD_DOMAIN_TOR') && STD_DOMAIN_TOR) {
							if (substr($_SERVER['SERVER_NAME'], -6) == '.onion')
								$rep = str_replace(STD_DOMAIN_I2P, STD_DOMAIN_TOR, $rep);
							else if (substr($_SERVER['SERVER_NAME'], -4) == '.i2p')
								$rep = str_replace(STD_DOMAIN_TOR, STD_DOMAIN_I2P, $rep);
						}

						// URLのリンク
						// NOTE:エスケープされたHTMLソースコード内のURLを極力避ける、特別措置として()記号はURLとして扱わない
						$rep = preg_replace_callback('/(?!")(\\\\)?(\\!(\\[(.*?)\\])?)?((https?|ftps?)(:\\/\\/[a-z0-9:\\?\\/\\+\\-_~=;\\.,\\*&@#\\$%\'\\[\\]]+))/ui', function($mt) {
							global $db;

							if (@$mt[5]) {
								$title = '';
								if (SYS_PARSE_THREAD && strpos($mt[5], '://' . $_SERVER['SERVER_NAME'] . '/') !== false) {
									// 名前検索
									preg_match('/^.+?\\/\\/.+?\\/([0-9a-zA-Z]{8,8})(\\/([lL#]?[0-9０-９]+([-－ー][0-9０-９]+)?([,，、][0-9０-９]+([-－ー][0-9０-９]+)?)*))?(\\/(.*))?$/u', $mt[5], $mt2);
									if (@$mt2[1]) {
										$title = $db->query_get_one('SELECT title FROM thread WHERE dt_del IS NULL AND id = ?', $mt2[1]);

										if (@$mt2[3] && strtolower(@$mt2[8]) != 'json' && strtolower(@$mt2[8]) != 'rss') {
											// jsonとrssはスレ指定ができないので省く
											$ank = mb_convert_kana($mt2[3], 'n');
											$ank = str_replace(array('－', 'ー'), '-', $ank);

											if ($ank[0] == 'l' || $ank[0] == 'L')
												$ank = '新着' . substr($ank, 1);

											if ($ank[0] == '#')
												$ank = '#' . substr($ank, 1);
											
											$title .= '(' . $ank . ')';
										}

										if (!$title) {
											$title = $mt[5];
										} else {
											if (strtolower(@$mt2[8]) == 'pdf')
												$title .= '[PDF]';

											if (strtolower(@$mt2[8]) == 'json')
												$title .= '[JSON]';

											if (strtolower(@$mt2[8]) == 'rss')
												$title .= '[RSS]';										}

										return '<a href="' . $mt[5] . '" target="_blank" class="msg_link msg_link_o" name="' . $mt[5] . '">' . $title . '</a>';
									}
								}
							}
							
							// 画像
							if (@$mt[2] && SYS_USE_MARKDOWN && SYS_USE_IMAGE_VIEW) {
								if (@$mt[5]) {
									return preg_replace_callback('/(\\\\)?\\!(\\[(.*?)\\])?(https?:\\/\\/.+)/ui', function($mt2) {
										if (@$mt2[1]) {
											// エスケープ
											return '!' . $mt2[4];
										} if (strpos($mt2[4], 'https://') === 0 || preg_match('/^https?:\\/\\/[-_\\.0-9a-z]+\\.onion\\/.+$/i', $mt2[4])) {
											// サムネ付きと無し
											if (strpos($mt2[3], 'https://') === 0 || preg_match('/^https?:\\/\\/[-_\\.0-9a-z]+\\.onion\\/.+$/i', $mt2[3])) {
												// alt部分はドメイン名無し
												if (STD_DOMAIN_TOR) {
													$alt1 = str_replace('http://' . STD_DOMAIN_TOR . '/', 'http:///', $mt2[3]);
													$alt1 = str_replace('http://' . STD_DOMAIN_I2P . '/', 'http:///', $alt1);
													$alt2 = str_replace('http://' . STD_DOMAIN_TOR . '/', 'http:///', $mt2[4]);
													$alt2 = str_replace('http://' . STD_DOMAIN_I2P . '/', 'http:///', $alt2);
												} else {
													$alt1 = str_replace('http://' . $_SERVER['SERVER_NAME'] . '/', 'http:///', $mt2[3]);
													$alt2 = str_replace('http://' . $_SERVER['SERVER_NAME'] . '/', 'http:///', $mt2[4]);
												}

												return '<a href="' . $mt2[4] . '" target="_blank" class="msg_img_link"><img alt="![' . $alt1 . '.s]' . $alt2 . '" src="' . $mt2[3] . '" class="msg_img_img"></a>';
											} else {
												// alt部分はドメイン名無し
												if (STD_DOMAIN_TOR) {
													$alt1 = str_replace('http://' . STD_DOMAIN_TOR . '/', 'http:///', $mt2[4]);
													$alt1 = str_replace('http://' . STD_DOMAIN_I2P . '/', 'http:///', $mt2[4]);
												} else {
													$alt1 = str_replace('http://' . $_SERVER['SERVER_NAME'] . '/', 'http:///', $mt2[4]);
												}

												return '<a href="' . $mt2[4] . '" target="_blank" class="msg_img_link"><img alt="!' . $alt1 . '" src="' . $mt2[4] . '" class="msg_img_img"></a>';
											}
										} else {
											// セキュリティ的にNG
											return '!' . $mt2[4];
										}
									}, $mt[0]);	
								} else if (@$mt[4]) {
									// サムネイルだけ（リンク先は画像じゃない）
									if ($mt[4][0] == "\\")
										return '![' . $mt[4] . ']';
									else if (strpos($mt[4], 'https://') === 0 || preg_match('/^https?:\\/\\/[-_\\.0-9a-z]+\\.onion\\/.+$/i', $mt[4]))
										return '<a href="' . $mt[4] . '" target="_blank" class="msg_img_link"><img alt="![' . $mt[4] . '.s]" src="' . $mt[4] . '" class="msg_img_img"></a>';
									else
										return '!' . $mt[4]; // セキュリティ的にNG
								}
							}

							if (@$mt[5]) {
								// その他通常のリンク
								if (preg_match('/^((https?|ftps?):\\/\\/[-_\.a-z0-9]+)(\\.onion|\\.i2p)(\\/.*)?$/i', $mt[5], $mt2)) {
									// onion,i2p
									return '<a href="' . $mt[5] . '" target="_blank" class="msg_link msg_link_o">' . $mt2[1] . '<b>' . $mt2[3] . '</b>' . urldecode(@$mt2[4]) . '</a>';
								} else if (preg_match('/^(http:\\/\\/)(127\\.0\\.0\\.1)(:[0-9]+)(\\/.*)?$/i', $mt[5], $mt2)) {
									// zeronet(localhost)
									return '<a href="' . $mt[5] . '" target="_blank" class="msg_link msg_link_o">' . $mt2[1] . '<b>' . $mt2[2] . '</b>'. $mt2[3] . urldecode(@$mt2[4]) . '</a>';
								} else if (preg_match('/^(https|ftps)/', $mt[5])) {
									// https
									return '<a href="' . $mt[5] . '" target="_blank" class="msg_link msg_link_s"><b>' . $mt[6] . '</b>' . urldecode($mt[7]) . '</a>';
								} else {
									// 表層
									if (SYS_PARSE_HYPOTHESIS) {
										return '<a href="' . $mt[5] . '" target="_blank" class="msg_link msg_link_h"><b>' . $mt[6] . '</b>' . urldecode($mt[7]) . '</a>'
											. '(<a href="https://via.hypothes.is/' . $mt[5] . '" target="_blank" class="msg_link msg_link_s">Hypothesis</a>)';
									} else {
										return '<a href="' . $mt[5] . '" target="_blank" class="msg_link msg_link_h"><b>' . $mt[6] . '</b>' . urldecode($mt[7]) . '</a>';
									}
								}
							}
						}, $rep);

						// メールのリンク
						//$rep = preg_replace('/([a-z][-_\\.\\+a-z0-9]*@[-_a-z0-9]+\\.[-_\\.a-z0-9]+)/i', '<a href="mailto:$1" class="msg_link_h">$1</a>', $rep);

						// レスのリンク（全角可）
						$rep = preg_replace_callback('/((&gt;|＞)*)?(((&gt;|＞){2,2})([0-9０-９]+([-－ー][0-9０-９]+)?([,，、][0-9０-９]+([-－ー][0-9０-９]+)?)*))/us', function($mt) {
							global $thread_id;
							global $view_mode;

							$ank = mb_convert_kana($mt[6], 'n');
							$ank = str_replace(array('－', 'ー'), '-', $ank);

							if ($view_mode == 'thread' && !@$_GET['__A'])
								return $mt[1] . '<a href="#' . $ank . '" class="msg_link msg_link_anchor">' . $mt[4] . $mt[6] . '</a>';
							else
								return $mt[1] . '<a href="' . BASEDIR . $thread_id . '/' . $ank . '" target="_blank" class="msg_link msg_link_anchor">' . $mt[4] . $mt[6] . '</a>';
						}, $rep);

						// 引用
						while (true) {
							$rep_new = preg_replace_callback('/((^|\\n|<blockquote>)([ 　\\t]*)(&gt;|＞)([^\\n$]*))+/us', function($mt) {
									return "<blockquote>" . preg_replace('/(^|\\n|<blockquote>)[ 　\\t]*(&gt;|＞) *([^\\n$]*)/us', "$1$3", $mt[0]) . "</blockquote>";
							}, $rep);

							// 引用ネストが終わるまでループ
							if ($rep_new == $rep) {
								$rep = preg_replace('/(<(\\/)?blockquote>)\\n/s', '$1', $rep_new);
								break;
							}

							$rep = $rep_new;
						}
						$rep = preg_replace('/(^|\\n)([ 　\\t]*)\\\\(&gt;|＞)/us', '$1$2$3', $rep);

						return $rep;
					}, $data['msg']);

					// moreを適用
					if ($use_more) {
						@list($data['msg'], $more) = explode("<MORE>", $data['msg'], 2);
						if (strlen($more)) {
							if ($data['msg'])
								$data['msg'] .= '<br>';

							$data['msg'] .= '<a href="' . BASEDIR . $thread['id'] . '/' . $id . '" class="msg_link msg_link_more" target="_blank">続きを見る</a>';
						}
					}

					// 改行は最大3つまで
					$data['msg'] = preg_replace('/\n{4,}/s', "\n\n\n", $data['msg']);
				} else {
					// 削除
					$data['dt_add'] = htmlspecialchars(SYS_DELETE_STRING);
					$data['name'] = '<span class="msg_name name_anon">' . htmlspecialchars(SYS_DELETE_STRING) . '</span>';
					$data['msg'] = htmlspecialchars(SYS_DELETE_STRING);
				}
			}

			// ユーザーフィルター（後処理）
			if (function_exists('filter_msg_after')) {
				$udata = array('id' => $id, 'name' => $data['name'], 'ctime' => $data['dt_add'], 'dtime' => $data['dt_del'], 'msg' => $data['msg']);
				$udata = filter_msg_after($udata);

				$id = @$udata['id'];
				$data['name'] = @$udata['name'];
				$data['dt_add'] = @$udata['ctime'];
				$data['dt_del'] = @$udata['dtime'];
				$data['msg'] = @$udata['msg'];
			}
		}

		if ($data['dt_add'] && $data['msg'] && file_exists(BASEDIR . 'img/' . $data['dt_add_y'] . '/upload' . $data['id'])) {
			// 非JSでアップロードされた画像
			$imgurl = BASEDIR . 'img/' . $data['dt_add_y'] . '/upload' .  $data['id'];
			$data['msg'] .= '<a href="' . $imgurl . '" target="_blank" class="msg_img_link"><br><br><img src="' . $imgurl . '.s" alt="![' . $imgurl . '.s]' . $imgurl . '" class="msg_img_img"></a>';
		}

		if ($id <= MAX_POST) {
			// 管理者の場合は番号の手前にチェックボックスを付ける
			if ($is_admin && $id != 1)
				$admin_chk = '<input type="checkbox" name="ids[]" value="' . $data['id'] . '" class="admin_item"> ';
			else
				$admin_chk = '';

			if (!$data['dt_del']) {
				// 通常のデータ
?>
<div class="msg" id="<?=$id?>">
<div class="msg_head <?=$msg_score?>"><?=$admin_chk?><a class="msg_id" href="<?=$msgurl?>" anc="<?=$thread['id']?>,<?=$id?>"><?=$id?></a><span class="msg_star"><?=$msg_star?></span><?=$data['name']?><span class="msg_date"><?=$data['dt_add']?></span></div>
<div class="msg_body <?=$msg_score?>"><?=$data['msg']?></div>
<?php
				if (SYS_USE_USER_REGIST && !$is_admin_write) {
					if (@$_SESSION['id']) {
						// スコア表示＆操作
?>
<div class="msg_fav_ctrl" data-pid="<?=$data['id']?>"><div class="msg_fav_up">▲</div><div class="msg_fav_cou"><?=$data['score']?></div><div class="msg_fav_down">▼</div></div>
<?php
					} else {
						// スコア表示
?>
<div class="msg_fav_view" data-pid="<?=$data['id']?>">SCORE<div class="msg_fav_cou"><?=$data['score']?></div></div>
<?php
					}
				}
?>
</div>
<?php
			} else {
				// 削除されたデータ
?>
<div class="msg" id="<?=$id?>">
<div class="msg_head msg_del"><?=$admin_chk?><a class="msg_id" href="<?=$msgurl?>" anc="<?=$thread['id']?>,<?=$id?>"><?=$id?></a><span class="msg_name"><?=$data['name']?></span><span class="msg_date"><?=$data['dt_add']?></span></div>
<div class="msg_body msg_del"><?=$data['msg']?></div>
</div>
<?php
			}
		} else {
			// スレ終了
?>
<div class="msg" id="<?=$id?>">
<div class="msg_head msg_end"><span class="msg_id"><?=$id?></span><span class="msg_name"><?=$data['name']?></span><span class="msg_date"><?=$data['dt_add']?></span></div>
<div class="msg_body msg_end"><?=$data['msg']?></div>
</div>
<?php
		}
	}

	if ($view_mode == 'thread') {
		if (SYS_USE_OUTPUT_PDF && test_pdf_output())
			$pdf_link = '<a href="' . BASEDIR . $thread['id'] . '/' . $_GET['__A'] . '/pdf" class="link_btn">PDF</a> ';
		else
			$pdf_link = '';

		if (SYS_USE_OUTPUT_JSON)
			$json_link = '<a href="' . BASEDIR . $thread['id'] . '/0/json" class="link_btn">JSON</a> ';
		else
			$json_link = ' ';

		if (SYS_USE_OUTPUT_RSS)
			$rss_link = '<a href="' . BASEDIR . $thread['id'] . '/0/rss" class="link_btn">RSS</a> ';
		else
			$rss_link = ' ';
?>
<div class="link_btn_area"><?=$pdf_link?><?=$json_link?><?=$rss_link?><a href="javascript:window.scroll(0,0)" class="link_btn">スレTOP</a> <a href="<?=BASEDIR?>" class="link_btn">掲示板TOP</a></div>
<?php
	} else {
?>
<div class="link_btn_area"><a href="<?=BASEDIR?><?=$thread['id']?>/l50" class="link_btn">最新50件</a> <a href="<?=BASEDIR?><?=$thread['id']?>" class="link_btn">全て表示</a> <a href="<?=BASEDIR?>" class="link_btn">掲示板TOP</a></div>
<?php
	}

	if ($is_admin) {
		// 管理者用メニュー（下）
?>
<div class="admin_box">
<div><select name="post_cmd">
<option value=""></option><option value="del">デリート/レストア</option><option value="purge">パージ</option><option value="rollback">ロールバック</option>
</select></div>
<div><input type="checkbox" id="chk_post_<?=$thread['id']?>" name="chk_post" value="1"> <label for="chk_post_<?=$thread['id']?>">確認</label></div>
<div><input type="submit" name="submit" value="投稿操作"></div>
</div>
</form>
<?php
	}

	if (!isset($datas[MAX_POST])) {
?>
<form class="postbox" action="<?=BASEDIR?><?=$thread['id']?>/write" method="post" enctype="multipart/form-data">
<table>
<tr><th>名前</th><td style="width:auto"><div class="sponly">名前</div><input type="text" name="name" class="post_name" placeholder="<?=$user_ph?>"></td></tr>
<tr><th>内容</th><td><div class="sponly">内容</div><textarea id="txt_<?=$thread['id']?>" rows="7" type="text" name="msg" class="post_msg" placeholder="<?=$ph?>"></textarea></td></tr>
<tr><th></th><td><div style="text-align:right">
<?php
	if (SYS_USE_IMAGE_UPLOAD) {
?>
<script>
document.write('<div><input type="button" id="selbtn_<?=$thread['id']?>"  class="post_sel" accept="*/*" value="　<?=(defined("POST_IMGBB_KEY") && POST_IMGBB_KEY ? "ImgBB" : "画像挿入")?>　" /><input type="file" id="selfile_<?=$thread['id']?>" class="post_file" multiple="multiple" />　');
</script>
<noscript>
<input type="file" id="selfile_0" name="img" class="post_file_noscript" multiple="multiple" value="　画像挿入　" />　
</noscript>
<?php
	}
?>
<div id="msg_<?=$thread['id']?>" class="post_msg"></div><img src="<?=$capt_img[1]?>" style="display:inline" class="post_capt_img">　<input type="text" name="ans" autocomplete="off" class="post_capt_ans">　<input type="submit" value="　書き込み　"></div>
</div></td></tr>
</table>
<input type="hidden" name="thread" value="<?=@$_GET['__T']?>">
<input type="hidden" name="capt" value="<?=$capt_id[1]?>">
</form>
<?php
}
?>
</div>
<?php
}
// スレッド作成フォーム
if ($view_mode == 'index') {
?>
<div class="layoutbox thread_new">
<h2>新規スレ作成</h2><br>
<form class="newbox" action="<?=BASEDIR?>0/new" method="post" enctype="multipart/form-data">
<div class="newbox_comment"><?=STD_MKTHREAD_COMMENT?></div>
<table>
<tr><th>スレ名</th><td style="width:auto"><div class="sponly">スレ名</div><input type="text" name="title" class="post_title"></td></tr>
<tr><th>名前</th><td><div class="sponly">名前</div><input type="text" name="name" id="new_name" class="post_name"></td></tr>
<tr><th>内容</th><td><div class="sponly">内容</div><textarea id="txt_0" rows="7" type="text" name="msg" class="post_msg" placeholder="<?=$ph?>"></textarea></td></tr>
<tr><th></th><td><div style="text-align:right">
<?php
	if (SYS_USE_IMAGE_UPLOAD) {
?>
<script>
document.write('<div id="msg_0" class="post_msg"></div><div><input type="button" id="selbtn_0"  class="post_sel" accept="*/*" value="　<?=(defined("POST_IMGBB_KEY") && POST_IMGBB_KEY ? "ImgBB" : "画像挿入")?>　" /><input type="file" id="selfile_0" class="post_file" multiple="multiple" />　');
</script>
<noscript>
<input type="file" id="selfile_0" name="img" class="post_file_noscript" multiple="multiple" value="　画像挿入　" />　
</noscript>
<?php
	}
?>
<div id="msg_0" class="post_msg"></div><img src="<?=$capt_img[1]?>" style="display:inline" class="post_capt_img">　<input type="text" name="ans" autocomplete="off" class="post_capt_ans">　<input type="submit" value="　新規作成　"></div>
</div></td></tr>
</table>
<input type="hidden" name="capt" value="<?=$capt_id[1]?>">
</form>
</div>
<?php
}

if (SYS_USE_USER_REGIST) {
?>
<div class="layoutbox userarea">
<?php
	if ($is_admin) {
?>
サイト管理モード　<a href="<?=BASEDIR.ADMIN_PAGE?>" target="_blank">管理ページ</a>　<a href="<?=BASEDIR?>user.php">ユーザーページ</a>
<?php
	} else if (@$_SESSION['name']) {
?>
<div>ようこそ <b><a href="<?=BASEDIR?>user.php"><?=$_SESSION['name']?></a></b> さん</div>
<?php
	} else {
?>
<div>ようこそ <b><a href="<?=BASEDIR?>user.php"><?=STD_ANON_NAME?></a></b> さん</div>
<div><form action="<?=BASEDIR?>user.php" method="post"><input type="text" name="user" placeholder="USER"> <input type="password" name="pass" placeholder="PASS"> <input type="hidden" name="act" value="login"><input type="submit" value="　ログイン　">　<input type="button" value="　アカウント作成　" onclick="location.href='<?=BASEDIR?>user.php?act=regist'"></form></div>
<?php
	}
?>
</div>
<?php
} else {
	if ($is_admin) {
?>
<div class="layoutbox userarea">
サイト管理モード　<a href="<?=BASEDIR.ADMIN_PAGE?>" target="_blank">管理ページ</a>　<a href="<?=BASEDIR?>user.php">ユーザーページ</a>
</div>
<?php
	}
}
?>
</div>
<div class="layoutbox footer">
<?php @include('./data/footer.php'); ?>
</div>
<img id="capt_img_1" capt_id="<?=$capt_id[1]?>" src="<?=$capt_img[1]?>" style="display:none">
<img id="capt_img_2" capt_id="<?=$capt_id[2]?>" src="<?=$capt_img[2]?>" style="display:none">
<img id="capt_img_3" capt_id="<?=$capt_id[3]?>" src="<?=$capt_img[3]?>" style="display:none">
</body>
</html>
