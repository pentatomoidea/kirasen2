<?php

// 指定された長さの36進数文字列を返す
function str_rand($length = 48) {
	return substr(base_convert(hash('sha256', uniqid()), 16, 36), 0, $length);
}

// 日本語曜日表記対応date()
function date_jp($format, $timestamp = null) {
	$week_ll = array('日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日');
	$week_dd = array('日', '月', '火', '水', '木', '金', '土');

	$format = str_replace('ll', $week_ll[date('w', $timestamp)], $format);
	$format = str_replace('DD', $week_dd[date('w', $timestamp)], $format);
	$date = date($format, $timestamp);

	return $date;
}

// PDODBライブラリでDBに接続
function db_connect() {
	if (MYSQL_HOST)
		$db = new db('mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_NAME . ';charset=utf8', MYSQL_USER, MYSQL_PASS);
	else
		$db = new db('sqlite:' . __DIR__ . '/../data/bbs.db');

	return $db;
}

// telegramに通知を送る
function send_telegram($title, $msg) {
	if (NOTY_TELEGRAM_TOKEN == '' || NOTY_TELEGRAM_CHAT == '')
		return;

	$json = json_encode(array(
		'chat_id' => NOTY_TELEGRAM_CHAT, 'disable_web_page_preview' => true, 'text' => NOTY_TITLE . ':' . $title . "\n" . $msg
	));

	$context = array(
		'http' => array(
			'method'  => 'POST', 'header'  => 'Content-Type: application/json\r\n', 'content' => $json
		)
	);

	@file_get_contents('https://api.telegram.org/bot' . NOTY_TELEGRAM_TOKEN . '/sendMessage', '', stream_context_create($context));
}

// マルチバイト対応trim()
function mb_trim($str) {
	return preg_replace('/^[ 　\\r\\n\\t\\v\\0]*(.*?)[ 　\\r\\n\\t\\v\\0]*$/us', '$1', $str);
}

// index.phpのクレジットからバージョンを抜き出す
function get_version() {
	$txt = file_get_contents(__DIR__ . '/../index.php', false, null, 0, 100);
	$ver = preg_replace('/^.+\\n \\* .+?v\\.([\.0-9]+)\\n.+/s', '$1', $txt);

	if ($ver)
		return $ver;
	else
		return 'ERR';
}

// PDFが出力できる環境か確認する
function test_pdf_output() {
	$w = `which xvfb-run`;
	if ($w[0] != '/')
		return false;

	$w = `which wkhtmltopdf`;
	if ($w[0] != '/')
		return false;

	return true;
}

// サイズを変更して画像を保存
function img_save($src_name, $dst_name, $max, $fill = false) {
	list($src_w, $src_h) = getimagesize($src_name);
	
	$exif = @exif_read_data($src_name);
	$rotate = 0;
	$flip = false;

	if (is_array($exif) && isset($exif['Orientation'])) {
		switch ($exif['Orientation']) {
			case 8:		//右に90度
				$rotate = 90;
				//$rotate = 270;
				break;
			case 3:		//180度回転
				$rotate = 180;
				break;
			case 6:		//右に270度回転
				$rotate = 270;
				break;
			case 2:		//反転
				$flip = IMG_FLIP_HORIZONTAL;
				break;
			case 7:		//反転して右90度
				$rotate = 90;
				$flip = IMG_FLIP_HORIZONTAL;
				break;
			case 4:		//反転して180度（縦反転と同じ）
				$flip = IMG_FLIP_VERTICAL;
				break;
			case 5:		//反転して270度
				$rotate = 270;
				$flip = IMG_FLIP_HORIZONTAL;
				break;
		}
	}

	if ($src_w <= $max && $src_h <= $max) {
		// サイズ変換不要
		return @copy($src_name, $dst_name);
	}

	// ファイルを開く
	$src_img = @imagecreatefromstring(file_get_contents($src_name)); // この方法で開くと画像タイプの指定が不要？
	
	if ($src_img === null) {
		return false;
	}

	// アスペクト比を維持したまま縮小後（拡大後）のサイズを計算
	if ($src_w / $max > $src_h / $max) {
		$dst_w = $max;
		$dst_h = (int)($max / $src_w * $src_h);
		$dst_x = 0;
		$dst_y = ($max - $dst_h) / 2;
	} else {
		$dst_h = $max;
		$dst_w = (int)($max / $src_h * $src_w);
		$dst_x = ($max - $dst_w) / 2;
		$dst_y = 0;
	}

	// 新しい画像を作成する
	if ($fill) {
		$dst_img = @imagecreatetruecolor($max, $max);
	} else {
		$dst_img = @imagecreatetruecolor($dst_w, $dst_h);
		$dst_x = 0;
		$dst_y = 0;
	}

	if ($dst_img === null) {
		imagedestroy($src_img);
		return false;
	}

	imagealphablending($dst_img, false);
	imagesavealpha($dst_img, true);

	// 新しい画像の背景を塗りつぶす
	if ($fill) {
		if (!imagefill($dst_img, 0, 0, imagecolorallocatealpha($dst_img, 0, 0, 0, 0))) {
			imagedestroy($src_img);
			imagedestroy($dst_img);
			return false;
		}
	}

	// 新しい画像に元画像をコピーする
	if (!imagecopyresampled($dst_img, $src_img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h)) {
		imagedestroy($src_img);
		imagedestroy($dst_img);
		return false;
	}

	//反転(2,7,4,5)
	if ($flip !== false) {
		$dst_img = imageflip($dst_img, $flip);
	}

	//回転(8,3,6,7,5)
	if ($rotate != 0) {
		$dst_img = imagerotate($dst_img, $rotate, 0);
	}

	// 新しい画像の保存
	if (!imagejpeg($dst_img, $dst_name, 75)) {
		imagedestroy($src_img);
		imagedestroy($dst_img);
		return false;
	}

	// 終了処理
	imagedestroy($src_img);
	imagedestroy($dst_img);

	return true;
}
