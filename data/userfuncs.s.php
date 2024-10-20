<?php
// ユーザー定義関数とフィルタ関数

/*-----
フィルタ関数
システムが投稿メッセージの修正（エスケープやリンクの適用）を行う前や後、
もしくは完全に独自実装する為の関数です。
-----*/

// メッセージの処理が行われる前に実行される
/*function filter_msg_before($data) {
}*/

// メッセージの処理が行われた後に実行される
/*function filter_msg_after($data) {
	// 表示するIDを4桁にするサンプル
	$data['id'] = substr('000' . $data['id'], -4);
}*/

// メッセージの処理を全て自分で行う（before,afterは呼び出されない）
/*function filter_msg_override($data) {
}*/

/*-----
フック関数
スレッドの作成、もしくは投稿をフックします。
文字列を返すとエラー文字列として扱われ、falseを返すとエラーを出さずに処理を終了します。
trueを返すと処理は継続され、配列を返すとデータの修正として扱われます。
-----*/

// スレッドが作成される時にフックする
/*function hook_thread_new(&$data) {
	// 作成を禁止するサンプル
	return '現在スレッドの作成を停止しています';
}*/

// レスが投稿される時にフックする
/*function hook_post_new(&$data) {
	// 改行を全て消すサンプル
	$data['msg'] = str_replace("\n", '', $data['msg']);
	return true;
}*/
