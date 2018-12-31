<?php

/*-------------------------------
	ログ
-------------------------------*/
//ログを取るか
ini_set('log_errors','on');
//ログの出力ファイルを指定
ini_set('error_log','php.log');
/*-------------------------------
	デバッグ関数
-------------------------------*/
// デバッグフラグ
$debug_flg = true;
// デバッグログ関数
function debug($str){
	global $debug_flg;
	if(!empty($debug_flg)){
		error_log('デバッグ：'.$str);
	}
}
/*-------------------------------
	セッション準備・セッション有効期限を延ばす
-------------------------------*/
// セッションファイルの置き場を変更(30日は削除されない デフォルト有効期限は24分)
session_save_path("/var/tmp/");
// ガーベージコレクションが削除するセッションの有効期限を設定
ini_set('session.gc_maxlifetime', 60*60*24*30);
// ブラウザを閉じても削除されないようにクッキー自体の有効期限を延ばす
ini_set('session.cookie_lifetime', 60*60*24*30);
// セッション使用
session_start();
// セッションIDを新しく発行(なりすまし対策)
session_regenerate_id();
/*-------------------------------
	画面表示処理開始ログ吐き出し関数
-------------------------------*/
function debugLogStart(){
	debug('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> 画面表示処理開始');
	debug('セッションID：'.session_id());
	debug('センション変数の中身：'.print_r($_SESSION,true));
	debug('現在日時のタイムスタンプ：'.time());
	if(!empty($_SESSION['login_date']) && !empty($_SESSION['login_limit'])){
		debug('ログイン期限日時タイムスタンプ：'.($_SESSION['login_date'] + $_SESSION['login_limit']));
	}
}
/*-------------------------------
	定数
-------------------------------*/
// エラーメッセージ
define('MSG01', '入力必須です');
define('MSG02', '半角英数字で入力してください');
define('MSG03', 'パスワード(再入力)が合っていません');
define('MSG04', '文字以上で入力してください');
define('MSG05', 'E-mailの形式で入力してください');
define('MSG06', '文字以内で入力してください');
define('MSG07', 'エラーが発生しました。しばらく経ってからやり直してください');
define('MSG08', 'ユーザー名またはパスワードが違います');
define('MSG09', 'そのEmailは既に登録されています');
// 成功時メッセージ
define('SUC01', '登録しました');

/*-------------------------------
	グローバル変数
-------------------------------*/
// エラーメッセージ格納用の配列
$err_msg = array();

/*-------------------------------
	バリデーションチェック
-------------------------------*/
// 未入力チェック
function validRequired($str, $key){
	if($str === ''){
		global $err_msg;
		$err_msg[$key] = MSG01;
	}
}
// 最大文字数チェック
function validMaxLen($str, $key, $max = 200){
	if(mb_strlen($str) > $max){
		global $err_msg;
		$err_msg[$key] = $max.MSG06;
	}
}
// 最小文字数チェック
function validMinLen($str, $key, $min = 6){
	if(mb_strlen($str) < $min){
		global $err_msg;
		$err_msg[$key] = $min.MSG04;
	}
}
// email形式チェック
function validEmail($str, $key){
	if(!preg_match("/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/", $str)){
		global $err_msg;
		$err_msg[$key] = MSG05;
	}
}
// email重複チェック
function validEmailDup($email){
	global $err_msg;
	try{
		$dbh = dbConnect();
		$sql = 'SELECT count(*) FROM users WHERE email = :email AND delete_flg = 0';
		$data = array(':email' => $email);
		// クエリ実行
		$stmt = queryPost($dbh, $sql, $data);
		// クエリ結果の値を取得
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		debug('クエリ結果の中身'.print_r($result,true));

		if(!empty(array_shift($result))){
			$err_msg['email'] = MSG09;
		}
	}catch(Exception $e){
		error_log('エラー発生：'. $e->getMessage());
		$err_msg['common'] = MSG07;
	}
}
// 半角英数字チェック
function validHalf($str, $key){
	if(!preg_match("/^[a-zA-Z0-9]+$/", $str)){
		global $err_msg;
		$err_msg[$key] = MSG02;
	}
}
// 同値チェック
function validMatch($str1, $str2, $key){
	if($str1 !== $str2){
		global $err_msg;
		$err_msg[$key] = MSG03;
	}
}
// パスワードチェック
function validPass($str, $key){
	// 半角英数字チェック
	validHalf($str, $key);
	// 最小文字数チェック
	validMinLen($str, $key);
	// 最大文字数チェック
	validMaxLen($str, $key);
}
// エラーメッセージ表示
function getErrMsg($key){
	global $err_msg;
	if(!empty($err_msg[$key])){
		echo $err_msg[$key];
	}
}
/*-------------------------------
	データベース
-------------------------------*/
// DB接続関数
function dbConnect(){
	// DBへの接続準備
	$dsn = 'mysql:dbname=PostedOnceADay;host=localhost;charset=utf8';
	$user = 'root';
	$password = '123511kN';
	$options = array(
		// SQL実行失敗時にはエラーコードのみ設定
		PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
		// デフォルトフェッチモードを連想配列形式に設定
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		// バッファードクエリを使う(一度に結果セットをすべて取得し、サーバー負荷を軽減)
		// SELECTで得た結果に対してもrowCountメソッドを使えるようにする
		PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
	);
	// PDOオブジェクト生成（DBへ接続）
	$dbh = new PDO($dsn, $user, $password, $options);
	return $dbh;
}
// SQL実行関数
function queryPost($dbh, $sql, $data){
	// クエリ作成
	$stmt = $dbh->prepare($sql);
	// SQL文を実行
	if(!$stmt->execute($data)){
		debug('クエリ失敗しました。');
		debug('失敗したSQL：'.print_r($stmt,true));
		$err_msg['common'] = MSG07;
		return 0;
	}
	debug('クエリ成功');
	return $stmt;
}
function getPost($u_id, $p_id){
	debug('商品情報を取得します。');
	debug('ユーザID：'.$u_id);
	debug('投稿ID：'.$p_id);

	try{
		// DB接続
		$dbh = dbConnect();
		// SQL文作成
		$sql = 'SELECT * FROM post WHERE user_id = :u_id AND id = :p_id AND delete_flg = 0';
		$data = array(':u_id' => $u_id, ':p_id' => $p_id);

		// クエリ実行
		$stmt = queryPost($dbh, $sql, $data);

		if($stmt){
			return $stmt->fetch(PDO::FETCH_ASSOC);
		}else{
			return false;
		}
	}catch(Exception $e){
		error_log('エラー発生：'.$e->getMessage());
	}
}
/*-------------------------------
	その他
-------------------------------*/
// サニタイズ
function sanitize($str){
	return htmlspecialchars($str,ENT_QUOTES);
}
//フォーム入力保持
function getFormData($str){
	global $dbFormData;
	// ユーザーデータがある場合
	if(!empty($dbFormData)){
		//フォームのエラーがある場合
		if(!empty($err_msg[$str])){
			//POSTにデータがある場合
			if(isset($_POST[$str])){
				return sanitize($_POST[$str]);
			}else{
				return sanitize($dbFormData[$str]);
			}
		}else{
			// POSTにデータがあり、DBの情報と違う場合
			if(isset($_POST[$str]) && $_POST[$str] !== $dbFormData[$str]){
				return sanitize($_POST[$str]);
			}else{
				// 変更しない
				return sanitize($dbFormData[$str]);
			}
		}
	}else{
		if(isset($_POST[$str])){
			return sanitize($_POST[$str]);
		}
	}
}
// 画像処理
function uploadImg($file, $key){
	debug('画像アップロード処理開始');
	debug('FILE情報：'.print_r($file,true));

	if(isset($file['error']) && is_int($file['error'])){
		try{
			switch($file['error']){
				case UPLOAD_ERR_OK: //OK
					break;
				case UPLOAD_ERR_NO_FILE: //ファイル未選択の場合
					throw new RuntimeException('ファイルが選択されていません');
				case UPLOAD_ERR_INI_SIZE: //php.ini定義の最大サイズが超過した場合
				case UPLOAD_ERR_FORM_SIZE: //フォーム定義の最大サイズが超過した場合
					throw new RuntimeException('ファイルサイズが大きすぎます');
				default:
					throw new RuntimeException('その他のエラーが発生しました');
			}
			// upload画像が指定した拡張子と合っているか
			$type =@exif_imagetype($file['tmp_name']);
			if(!in_array($type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)){
				throw new RuntimeException('画像形式が未対応です');
			}
			//ファイル名をハッシュ化しパスを生成
			$path = 'uploads/'.sha1_file($file['tmp_name']).image_type_to_extension($type);
			
			if(!move_uploaded_file($file['tmp_name'], $path)){
				throw new RuntimeException('ファイル保存時にエラーが発生しました');
			}
			//保存したファイルパスのパーミッションを変更する
			chmod($path,0644);

			debug('ファイルは正常にアップロードされました');
			debug('ファイルパス：'.$path);
			return $path;
		}catch(RuntimeException $e){
			debug($e->getMessage());
			global $err_msg;
			$err_msg[$key] = $e->getMessage();
		}
	}
}







