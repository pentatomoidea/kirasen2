Kirasen 0.2
--

【動作環境】

・apache2 + mod_rewrite、もしくはNginxが動作する環境。
・php5もしくはphp7、php-gd、php-sqlite、php-mbstringが動作するphp環境。


【インストール】

1.設置
dataフォルダ以下のファイルの名前から".s"を省いてリネームします。
これはアップグレードを行った際に上書を防止する為です。
例：data/bbs.s.db → data/bbs.db

2.パーミッション設定
以下に書き込み許可のパーミッション（777）を追加します。
data
data/bbs.db
upload

3.dbupgrade.phpを削除
0.1系からアップグレードする際に必要なファイルです。

4.環境設定
サブディレクトリに設置する場合は、data/conf.phpのBASEDIRを修正して下さい。

5.管理画面
data/conf.phpのADMIN_PAGEとADMIN_USERとADMIN_PASSでログイン情報を設定して下さい。
ADMIN_PAGEに指定した文字列が管理画面になります。

*.アップデート
リネームを行ったファイルについては上書されません。しかし念の為にバックアップを行ってからアップデートして下さい。


【0.1系からのアップrグレード】

0.2は0.1から2つのテーブルが追加されています。
システムのアップグレードと同時にDBのアップグレードが必要です。

1.今まで使ってきたDB(bbs.db)を/data/にコピー
2./lib/の中のdbupgrade.phpをルートに移動してアクセス。
3.ガイダンスに従って削除を行う。
※必ずバックアップを作成してからアップグレードして下さい。


【nginxの場合】

.htaccessの代わりとして以下を設定ファイルに追加して下さい。
※サブディレクトリを利用しない場合の参考です。環境に合わせて修正して下さい。

rewrite ^/data/.* http://$host/ redirect;
rewrite ^/lib/.* http://$host/ redirect;

location / {
	autoindex off;
	error_page 403 http://$host/;
	error_page 404 http://$host/;

	if (!-e $request_filename) {
		rewrite ^/([a-zA-Z0-9]+)(/|/([-_,a-zA-Z0-9]+))?(/|/([-_,a-zA-Z0-9]+))?$ /?__T=$1&__A=$3&__P=$5 break;
	}
}


【faviconについて】

同じディレクトリに置いた180x180のfavicon.pngがサイトのアイコンになります。


【テーマについて】

提供されたテーマを直接触らずにcustom.s.cssをcustom.cssにリネームしてテーマを修正をして下さい。
もしくはlightテーマの複製を作成して修正して下さい。


【PDF書き出し機能について】

xvfb-runとwkhtmltopdfが必要です。
また上手く動作しない場合はhostsファイルで自ドメインを127.0.0.1にすると動くと思います。


【Apacheでエラーが出る場合】

.htaccessが正しく動作しているか確認して下さい。
→　rewriteモジュールが有効になっているか確認して下さい。（"sudo a2enmod rewrite" など）


【ApacheでTOP以外のページでエラーが出る場合】

.htaccessでrewriteモジュールの動作制限を確認して下さい。
→　/etc/apache2/apache.confもしくは/etc/httpd/httpd.confの"<Directory />"セクションで"AllowOverride"が"all"になっているか確認して下さい。


【NginxでTOP以外のページでエラーが出る場合】

rewrite設定が行えているか確認して下さい。
→　/etc/nginx/sites-enabled/default(もしくは作成したサイトの設定ファイル)のrewrite設定を見直して下さい。



【注意事項】

管理画面はデータベースの全てを制御しません。細かなメンテ用にphpliteadminの利用をお勧めします。


ライセンス

MITライセンスです。ご自由に。
