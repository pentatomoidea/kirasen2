<?php
include('./lib/common.php');

set_time_limit(0);
ignore_user_abort(true);


if (!defined('SYS_USE_IMAGE_UPLOAD') || !SYS_USE_IMAGE_UPLOAD) {
	echo json_encode(array('msg' => 'アップロードエラー'));
	exit();
}

if (isset($_FILES) && count($_FILES)) {
	$url = array();

	if (defined('POST_IMGBB_KEY') && POST_IMGBB_KEY) {
		// ImgBB

		foreach ($_FILES as $key => $val) {
			if (connection_aborted())
				exit();
	
			if ($_FILES[$key]['size'] == 0) {
				echo json_encode(array('msg' => 'サイズエラー'));
				exit();
			}

			if (@getimagesize($_FILES[$key]['tmp_name']) === false) {
				echo json_encode(array('msg' => '未対応の画像'));
				exit();
			}
	
			$type = exif_imagetype($_FILES[$key]['tmp_name']);
			if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG || $type == IMAGETYPE_BMP || $type == IMAGETYPE_WEBP) {
				$tmpfname = tempnam(sys_get_temp_dir(), 'krsn');
				move_uploaded_file($_FILES[$key]['tmp_name'], $tmpfname);
				ob_start();
				passthru('torsocks -i curl -s -F key=' . POST_IMGBB_KEY . ' -F "image=@' . $tmpfname . '" https://api.imgbb.com/1/upload');
				$ret = ob_get_contents();
				ob_end_clean();
				@unlink($tmpfname);
			} else {
				echo json_encode(array('msg' => '未対応の画像形式です'));
				exit();
			}
	
			$ret = json_decode($ret, true);

			//var_dump($ret);
	
			if (isset($ret['data']['url']) && $ret['status'] == 200) {
				$url[] = '![' . $ret['data']['thumb']['url'] . ']' . $ret['data']['url'];
			} else {
				echo json_encode(array('msg' => 'ImgBBアップロードエラー'));
				exit();
			}
		}
	} else {
		// 自サーバー

		$y = date('Y');
		$dir = __DIR__ . '/upload/' . $y . '/';
		$mdir = BASEDIR . 'upload/' . $y . '/';

		if (!file_exists($dir)) {
			@mkdir($dir);
			@chmod($dir, 0777);
		}

		foreach ($_FILES as $key => $val) {
			if (connection_aborted())
				exit();

			if ($_FILES[$key]['size'] == 0) {
				echo json_encode(array('msg' => 'サイズエラー'));
				exit();
			}

			while (true) {
				$fname = str_rand(6);
				if (!file_exists($dir . $fname))
					break;
			}

			$type = exif_imagetype($_FILES[$key]['tmp_name']);
			if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG || $type == IMAGETYPE_BMP || $type == IMAGETYPE_WEBP) {
				if (move_uploaded_file($_FILES[$key]['tmp_name'], $dir . $fname)) {
					chmod($dir . $fname, 0777);
					img_save($dir . $fname, $dir . $fname . '.s', 300);
					chmod($dir . $fname . '.s', 0777);
				}
			} else {
				echo json_encode(array('msg' => '未対応の画像形式です'));
				exit();
			}

			$url[] = '![http://' . $mdir . $fname . '.s]http://' . $mdir . $fname;
		}
	}

	if (count($url)) {
		echo json_encode(array('url' => implode("\n",$url) . "\n"));
		exit();
	}

	echo json_encode(array('msg' => 'ファイル解析エラー'));
	exit();
}

echo json_encode(array('msg' => '不明なエラー'));
