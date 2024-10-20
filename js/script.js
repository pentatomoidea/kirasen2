// HTML形式のデータをTEXTデータに変換
function html2text(html) {
	// 不要な部分を削除
	html = html.replace(/^[\s\S]+<\!--StartFragment-->/m, '');
	html = html.replace(/<\!--EndFragment-->[\s\S]+$/m, '');
	html = html.replace(/^<div class="msg_body">/, '');
	html = html.replace(/<\/div>$/, '');

	// コピー範囲の確認
	if (html.match(/(<div class="msg_body">|<\/div>)/gmi))
		return 'Error';
	
	// 装飾のMD化
	html = html.replace(/<\/?strong>/g, '***');
	html = html.replace(/<\/?del>/g, '~~~');

	// pre解除
	html = html.replace(/<\/?ol>/g, '');
	html = html.replace(/<li>/g, '');
	html = html.replace(/<\/li>/g, '\n');
	html = html.replace(/<pre( +.+?=".*?")*?>/g, '\n');
	html = html.replace(/<\/pre>/g, '');

	// リンクのTEXT化
	html = html.replace(/<\/?b>/g, '');
	html = html.replace(/\(<a .*?href="https:\/\/via.hypothes.is\/.+?".*?>.+?<\/a>\)/g, '');
	html = html.replace(/(<a .*?href="(.+?)" .+?>)?<img .*?alt="(.+?)".+?>(<\/a>)?/g, '$3');
	html = html.replace(/<a .*?>&gt;&gt;(.+?)<\/a>/g, '>>$1');
	html = html.replace(/<a .*?href="(.+?)" .+?<\/a>/g, '$1');

	// 引用のTEXT化
	html = html.replace(/(<\/blockquote>)(?!<\/blockquote>)/gm, '$1\n');
	html = html.replace(/(.)(<blockquote>){1,1}/gm, '$1\n$2');
	while (true) {
		text = html.replace(/<blockquote>([\s\S]*?)<\/blockquote>/gm, function() {
			return arguments[1].replace(/(.+?)(\n|$)/gm, '> $1$2');
		});

		if (text == html) {
			break;
		}

		html = text;
	}

	return text;
}

// 選択されているHTML部分を取得
function getSelHtml() {
    var html = "";
    if (typeof window.getSelection != "undefined") {
        var sel = window.getSelection();
        if (sel.rangeCount) {
            var container = document.createElement("div");
            for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                container.appendChild(sel.getRangeAt(i).cloneContents());
            }
            html = container.innerHTML;
        }
    } else if (typeof document.selection != "undefined") {
        if (document.selection.type == "Text") {
            html = document.selection.createRange().htmlText;
        }
	}

	return html;
}

$(function() {
	// キャプチャ切り替え
	var capt_no = 1;
	$('.post_capt_img').click(function(e) {
		capt_no++;
		if (capt_no > 3)
			capt_no = 1;

		$('.post_capt_img').attr('src', $('#capt_img_' + capt_no).attr('src'));
		$('input[name="capt"]').val($('#capt_img_' + capt_no).attr('capt_id'));
	});

	function score_chg(pid, val, tar) {
		// 前準備
		tar.css('opacity', '0.5');
		var head = $(tar).parent().parent().find('.msg_head');
		var body = $(tar).parent().parent().find('.msg_body');
		var star = $(tar).parent().parent().find('.msg_head').find('.msg_star');

		// スコア指定をサーバーに渡す
		$.ajax({
			url: BASEDIR + 'score.php?pid=' + pid + '&val=' + val,
			type: 'GET',
			dataType: 'text',
			contentType: false,
			processData: false,
			cache: false,
			timeout: 3000000,
			success: function(data) {
				if (data == '@') {
					// @が返ってきたらログインページにリダイレクト
					location.href = BASEDIR + 'user.php';
					return;
				}

				// 戻り値がある場合のみ反映
				if (data)
					tar.html(data);
				tar.css('opacity', '1');

				// 評価結果に応じて表示の切り替え
				if (data <= -6) {
					msg_star = '';
					msg_score = 'msg_score_m2';
				} else if (data <= -3) {
					msg_star = '';
					msg_score = 'msg_score_m1';
				} else if (data >= 9) {
					msg_star = '★★★';
					msg_score = 'msg_score_p3';
				} else if (data >= 6) {
					msg_star = '★★';
					msg_score = 'msg_score_p2';
				} else if (data >= 3) {
					msg_star = '★';
					msg_score = 'msg_score_p1';
				} else {
					msg_star = '';
					msg_score = 'msg_score_zero';
				}

				$(head).removeClass('msg_score_zero msg_score_m1 msg_score_m2 msg_score_p1 msg_score_p2 msg_score_p3');
				$(head).addClass(msg_score);
				$(body).removeClass('msg_score_zero msg_score_m1 msg_score_m2 msg_score_p1 msg_score_p2 msg_score_p3');
				$(body).addClass(msg_score);
				$(star).text(msg_star);
			},
			error: function(data) {
				tar.css('opacity', '1');
			}
		});
	}

	// コスアアップ
	$('.msg_fav_up').on('click', function(e) {
		var pid = $(this).parent().data('pid');
		var tar = $(this).parent().find('.msg_fav_cou');

		score_chg(pid, 1, tar)
	});

	// スコアダウン
	$('.msg_fav_down').on('click', function(e) {
		var pid = $(this).parent().data('pid');
		var tar = $(this).parent().find('.msg_fav_cou');

		score_chg(pid, -1, tar)
	});

	// ログイン中に名前が空の警告ON
	$('input[type=submit]').on('mouseover', function(e) {
		var tar_name = $(this).closest('table').find('.post_name');
		var tar_msg = $(this).closest('table').find('.post_msg');
		var tar_ans = $(this).closest('table').find('.post_capt_ans');

		if ($(tar_name).attr('placeholder')) {
			if (!$(tar_name).val() && $(tar_msg).val() && $(tar_ans).val()) {
				$(tar_name).addClass('dragover');
			}
		}
	});

	// ログイン中に名前が空の警告OFF
	$('input[type=submit]').on('mouseout', function(e) {
		var tar_name = $(this).closest('table').find('.post_name');
		$(tar_name).removeClass('dragover');
	});

	// ログイン中に名前ダブルクリックで＠を入力
	$('.post_name').on('dblclick', function(e) {
		if ($(this).attr('placeholder') && !$(this).val()) {
			$(this).val('@');
		}
	});

	// 選択中のメッセージ部分のhtmlをtextに変換してコピー
	$('.msg_body').on('copy', function(e) {
		html = getSelHtml();
		text = html2text(html)
		e.originalEvent.clipboardData.setData("text/plain" , text);
	
		e.preventDefault();
	});

	// 数字クリックでリプ
	$('.msg_id').on('click', function(e) {
		e.preventDefault();

		html = getSelHtml();
		text = html2text(html);
		ids = this.getAttribute('anc').split(',')

		if (text)
			var addtxt = '>>' + ids[1] + '\n> ' + text.replace(/\n/gm, '\n> ') + '\n';
		else
			var addtxt = '>>' + ids[1];

		var txtarea = document.getElementById('txt_' + ids[0]);
		var cpos = txtarea.selectionStart;
		txtarea.focus();
		txtarea.value = txtarea.value.substr(0, txtarea.selectionStart) + addtxt + txtarea.value.substr(txtarea.selectionEnd, txtarea.value.length);
		txtarea.selectionEnd = cpos + addtxt.length;
	});

	// TABでの移動を無効にしてTAB挿入
	$('textarea').on('keydown', function(e) {
		if (e.keyCode == 9) {
			var cpos = this.selectionStart;
			this.value = this.value.substr(0, this.selectionStart) + '\t' + this.value.substr(this.selectionEnd, this.value.length);
			this.selectionEnd = cpos + 1;

			e.preventDefault();
		}
	});

	if (POST_MAX_STR && POST_MAX_LINE) {
		$('textarea').keyup(function() {
			chkStrCou($(this).attr('id').substring(4));
		});

		$('textarea').keydown(function() {
			chkStrCou($(this).attr('id').substring(4));
		});
	}

	// 文字数と行数を表示する
	function chkStrCou(id) {
		var txts = '#txt_' + id;
		var msgs = '#msg_' + id;

		var poststr = $(txts).val().replace(/^([\s\S]*?)(^|\r\n|\r|\n)---($|\r\n|\r|\n)[\s\S]*/gm, '$1');
		var strcou = poststr.length;
		if (poststr.match(/(\r|\n)/g))
			var linecou = poststr.match(/(\r|\n)/g).length + 1;
		else
			var linecou = 1;

		if (strcou > POST_MAX_STR) {
			$(txts).addClass('couover');
			$(msgs).html('<span style="color:#f88">moreを挿入して下さい（文字数オーバー）</span>');
		} else if (linecou > POST_MAX_LINE) {
			$(txts).addClass('couover');
			$(msgs).html('<span style="color:#f88">moreを挿入して下さい（行数オーバー）</span>');
		} else {
			$(txts).removeClass('couover');
			if (strcou)
				$(msgs).html('文字：' + strcou + '/' + POST_MAX_STR + '　行：' + linecou + '/' + POST_MAX_LINE);
			else
				$(msgs).html('');
		}
	}

	// 添付ファイルクリックトリガー
	$('[id^=selbtn_]').click(function(e) {
		$('#selfile_' + $(this).prop('id').substring(7)).click();
	});
	
	// 選択でファイルアップロード
	$('[id^=selfile_]').on('change', function(e) {
		var fd = new FormData();
		for (var i = 0; i < e.target.files.length; i++) {
			fd.append('img_' + i, e.target.files.item(i));
		}
	
		var tid = $(this).prop('id').substring(8);
		$('#txt_' + tid).addClass('dragover');
		img_upload(tid, fd);
	});
	
	// ドロップでファイルアップロード
	$('textarea').each(function(index, elem) {
		elem.addEventListener('dragover', function(e) {
			e.stopPropagation();
			e.preventDefault();

			$(this).addClass('dragover');
		}, false);

		elem.addEventListener('dragleave', function(e) {
			e.stopPropagation();
			e.preventDefault();

			$(this).removeClass('dragover');
		}, false);

		elem.addEventListener('drop', function(e) {
			e.stopPropagation();
			e.preventDefault();

			var fc = 0;
			var fd = new FormData();
			var files = e.dataTransfer.files;

			for (var i = 0; i < files.length; i++) {
				if (files.item(i).type.indexOf('image/') == 0) {
					fc++;
					fd.append('img_' + i, files.item(i));
				}
			}

			if (fc)
				img_upload($(this).attr('id').substring(4), fd);
			else
				$(this).removeClass('dragover');
		}, false);
	});

	// ペーストでクリップボード内の画像を貼り付け
	$('textarea').on('paste', function(e) {
		var items = e.originalEvent.clipboardData.items;
		for (var i = 0 ; i < items.length ; i++) {
			var item = items[i];
			if (item.type.indexOf("image") != -1) {
				var fd = new FormData();
				fd.append('img_' + i, item.getAsFile());

				$(this).addClass('dragover');
				img_upload($(this).attr('id').substring(4), fd);
			}
		}
	});

	// 画像アップロードAJAX
	function img_upload(tid, fd) {
		txts = '#txt_' + tid;
		msgs = '#msg_' + tid;

		$(msgs).html('アップロード中…');
		$.ajax({
			url: BASEDIR + 'dataup.php',
			type: 'POST',
			dataType: 'json',
			contentType: false,
			processData: false,
			cache: false,
			timeout: 3000000,
			data: fd,
			xhr : function() {
				var XHR = $.ajaxSettings.xhr();
				if (XHR.upload) {
					XHR.upload.addEventListener('progress', function(e) {
						if (e.loaded == e.total) {
							$(msgs).html('最適化中…');
						} else {
							$(msgs).html('アップロード中 ' + parseInt(e.loaded / e.total * 100) + '%');
						}
					}, false);
				}
				return XHR;
			},
			success: function(data) {
				if (data['url']) {
					var cpos = txts.selectionStart;
					$(txts).focus();
					$(txts).get(0).value = $(txts).get(0).value.substr(0, $(txts).get(0).selectionStart) + data['url'] + $(txts).get(0).value.substr($(txts).get(0).selectionStart, $(txts).get(0).value.length);
					$(txts).selectionEnd = cpos + data['url'].length;
					$(txts).removeClass('dragover');
					$(msgs).html('');
					$(txts).trigger('keyup');
				} else {
					$(txts).removeClass('dragover');
					$(msgs).html('');
					$(txts).trigger('keyup');
					if (data['msg'])
						alert(data['msg']);
				}
			},
			error: function(data) {
				$(txts).removeClass('dragover');
				$(msgs).html('');
				$(txts).trigger('keyup');
				alert('ネットワークエラー');
			}
		});
	}

	// 新規スレの種別切り替え
	$('#new_pam').on('change', function() {
		if ($(this).val() == 0) {
			// 名前レストア
			$('#new_name').val($('#new_name').data('pname'));
			$('#new_name').data('pname', '');
			$('#new_name').attr('readonly', false);
		} else if (!$('#new_name').data('pname')) {
			// 名前バックアップ
			$('#new_name').data('pname', $('#new_name').val());
			$('#new_name').val('@');
			$('#new_name').attr('readonly', true);
		}
	});

	$('textarea').trigger('keyup');
});
