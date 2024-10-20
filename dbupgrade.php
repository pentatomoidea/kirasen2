<?php
require_once('./lib/common.php');
$db = db_connect();

$db->exec('CREATE TABLE IF NOT EXISTS fav (id INTEGER PRIMARY KEY AUTOINCREMENT, id_user INTEGER, ip_post INTEGER, val INTEGER, dt_add INTEGER)');
$db->exec('CREATE TABLE IF NOT EXISTS user (id INTEGER PRIMARY KEY AUTOINCREMENT, user TEXT, pass TEXT, name TEXT, pam INTEGER, dt_add INTEGER, dt_del INTEGER, theme TEXT)');

echo '変換が完了しました。このファイルを削除して下さい。';
