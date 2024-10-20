<?php
define('STD_BBS_NAME', 'キラセン板');		// 掲示板名
define('STD_ANON_NAME', '匿名さん');		// 名前ナシの表示名
define('STD_THEME', 'light');				// デフォルトテーマ
define('STD_DOMAIN_TOR', '');				// 通常は空。TOR/I2Pの同時運用の際に.onionドメインを入れて下さい。
define('STD_DOMAIN_I2P', '');				// 通常は空。TOR/I2Pの同時運用の際に.i2pドメインを入れて下さい。

// TOPのお知らせ文
define('STD_INFORMATION', <<<HTML
質問の際はフォーラムの情報品質を維持する為にも、ある程度自分で調べてから投稿して下さい。
HTML
);

// スレッド作成の注意事項
define('STD_MKTHREAD_COMMENT', <<<HTML
<br>重複、類似のスレがないか確認して下さい<br><br>
HTML
);

define('BASEDIR', '/');						// ディレクトリ名、/や/aaaa/など
define('MAX_POST', 1000);					// 1スレあたりの最大投稿数
define('MAX_THREAD', 100);					// 一覧に表示するスレッドの最大数
define('MAX_LIFE', 60 * 60 * 24 * 30);		// 最大投稿数を越えたスレッドの表示期間
define('MAX_THREAD_PER_DAY', 20);			// 過去24時間以内に作成可能な最大スレッド数
define('MAX_POST_PER_HOUR', 100);			// 過去1時間以内に投稿可能な最大レス数
define('INDEX_SHOW_THREAD', 10);			// INDEXページに表示されるスレッド数
define('INDEX_SHOW_POST', 10);				// INDEXページに表示される各スレッドのレス数
define('SYS_USE_MORE', true);				// 投稿の一部を隠す機能の有効無効
define('SYS_USE_PRE', true);				// PREモードの有効無効
define('SYS_USE_MARKDOWN', true);			// マークダウンの有効化'
define('SYS_USE_IMAGE_VIEW', true);			// 画像URLの展開機能の有効化
define('SYS_USE_IMAGE_UPLOAD', true);		// 画像アップロード機能の有効化
define('SYS_USE_USER_REGIST', false);		// ユーザー登録機能とそれに関する全機能の有効無効
define('SYS_USE_OUTPUT_JSON', false);		// JSON出力の有効無効
define('SYS_USE_OUTPUT_PDF', false);		// PDF出力の有効無効
define('SYS_USE_OUTPUT_RSS', false);		// RSS出力の有効無効
define('SYS_CAPTCHA_TYPE', 1);				// 1=数字、2=アルファベッド
define('SYS_DATE_FORMAT', 'Y-m-d D H:i:s');	// 通常に加えて、"DD"で「○月」、"ll"で「○曜日」
define('SYS_DELETE_STRING', '削除');		// 削除された時に置換される文字（あぼーん）、未定義で削除文字非表示
define('SYS_PARSE_THREAD', true);			// 内部リンクをスレタイに置換する
define('SYS_PARSE_HYPOTHESIS', true);		// 表層http経由接続の際にHYPOTHESIS接続のオプションを表示する

// テーマの設定
define('SYS_THEME', array(
	'light' => array(						// ディレクトリ名
		'name' => 'ライト',					// 表示名
		'capt_fg' => 0x000000,				// キャプチャのFG色
		'capt_bg' => 0xf8f8f8				// キャプチャのBG色
	),
	'dark' => array(
		'name' => 'ダーク',
		'capt_fg' => 0xffffff,
		'capt_bg' => 0x222222
	)
));

// ユーザー登録時に設定ができない表示名（精査前に半角小文字に変換されるので英数字は半角小文字で指定）
define('SYS_NG_USER_NAME', array(
	'admin', 'administrator', 'root', 'nobody', 'guest', 'everyone', 'anonymous',
	'管理人', '管理者', 'kirasen',
));

// 管理者名。書き込みの際に置換されます（後ろに自動的に◆が付きます）
define('ADMIN_NAME', array(
	'KANRI!!!!' => '管理人',
));

// 管理画面へのログイン情報
define('ADMIN_PAGE', 'adminadmin');				// 管理ページ名、英数字10文字以上、必ず変更して下さい！
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin');

// 投稿時のNGワード（正規表現）
define('POST_NG_WORD', array(
	'/https:\\/\\/html\\.duckduckgo\\.com\\//',
));

// 入力時の制限（2つ同時に設定が必要、SYS_USE_MOREが有効である事。不要な時はいずれかを0にする）
define('POST_MAX_STR', 1000);					// moreまでの最大文字数
define('POST_MAX_LINE', 30);					// moreまでの最大行数

define('POST_IMGBB_KEY', '');					// ImgBBのAPI KEYを指定でImgBBを使用

// Telegram通知機能
// トークンとチャットIDの取得方法は調べて下さい
define('NOTY_TELEGRAM_TOKEN', '');				// APIトークン（空で利用しない）
define('NOTY_TELEGRAM_CHAT', '');				// チャットID
define('NOTY_TITLE', 'KirasenBBS');				// 通知タイトル
define('NOTY_TRIGGER_NEW_THREAD', false);		// 新しいスレッド作成時に通知
define('NOTY_TRIGGER_NEW_POST', false);			// 新しい投稿時に通知
define('NOTY_TRIGGER_MAX_THREAD', false);		// スレッド作成が上限（MAX_THREAD_PER_DAY）に達した時に通知
define('NOTY_TRIGGER_MAX_POST', false);			// 投稿数が上限（MAX_POST_PER_HOUR）に達した時に通知
define('NOTY_TRIGGER_LOGIN_SUCCESS', false);	// 管理画面へのログイン成功時に通知
define('NOTY_TRIGGER_LOGIN_FAILED', false);		// 管理画面へのログイン失敗時に通知

// MySQLサポート（β）
define('MYSQL_HOST', '');		// HOST名（空で利用しない）
define('MYSQL_NAME', '');		// DB名
define('MYSQL_USER', '');		// ログインユーザー
define('MYSQL_PASS', '');		// ログインPASS
