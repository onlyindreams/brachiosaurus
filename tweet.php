## 
## つぶやき商品データベースから投稿優先度の低い順でランダムに1件抽出し、twitterに投稿する
## tweetの重複をさけるため、投稿したらcountをインクリメントし、投稿優先度を下げる。
##
<?php


   // 開発中のため、引数が設定されていなければtwitterに実際に投稿しない。
   // また、データベースの更新も行わない
   $debug=true;
   if($argc>1){
      $debug=false;
   }
   
   echo $debug;
  
    echo "メッセージ抽出" . "\n";
	//PostgreSQLパラメータ
	$dsn = 'pgsql:dbname=rankdb host=localhost port=5432';
	$user = 'postgres';
	$password = 'postgres';
	
	try{
        $dbh = new PDO($dsn, $user, $password);

        //countが最も低いtweetからランダムに1件抽出する
        //全てが1になったら次は1の中からランダムに抽出される
        $sql = "select id,tweet from reservation order by count,random()";
        
	//echo $sql . "\n";
        $stmt = $dbh->query($sql);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $tweet = $record['tweet'];
        
        $sql = "update reservation set count=count+1,tweetime=now() where id=" . $record['id'];
        //抽出したtweetレコードはDEBUGでなければカウントアップ
        if(! $debug){            			
            $dbh->exec($sql);
        }else{
		echo "DEBUG: no updated.";
        }
    	
	}catch (PDOException $e){
    		print('Error:'.$e->getMessage());
    		die();
        }
       $dbh = null;

    echo "このメッセージを投稿します。" . "\n";
    echo "$tweet" . "\n";

//twitteroauth.phpを読み込む。
//パスはあなたが置いた適切な場所に変更してください
//cronなどで実行する場合はフルパスで書いてあげたほうが良いと思う…
require_once("/Users/naoya/Program/brachiosaurus/TwitterOAuth/twitteroauth.php");


//read twitter_appinfo from database.
try{
	$dbh = new PDO($dsn, $user, $password);
    $sql = "select api_key,api_secret,access_token,access_token_secret from twitter_appinfo where appname='Brachiosaurus'";
	$stmt = $dbh->query($sql);
	$record = $stmt->fetch(PDO::FETCH_ASSOC);
}catch(PDOException $e){
	print('Error:'.$e->getMessage());
	die();
}
 
//アプリ情報
//これはTwitterのDeveloperサイトで登録すると取得できます。
// API-keyの値
$consumer_key = $record['api_key'];
// API-secretの値
$consumer_secret = $record['api_secret'];
 
//アカウント情報
//これもTwitterのDeveloperサイトで登録すると取得できます。
// Access Tokenの値
$access_token = $record['access_token'];
// Access Token Secretの値
$access_token_secret = $record['access_token_secret'];

if ($debug){
  echo "consumer_key=$consumer_key \n";
  echo "consumer_secret=$consumer_secret \n";
  echo "access_token=$access_token \n";
  echo "access_token_secret=$access_token_secret \n";
}

$dbh=null;
 
// OAuthオブジェクト生成
$to = new TwitterOAuth(
        $consumer_key,
        $consumer_secret,
        $access_token,
        $access_token_secret);
 

    // 1.1向け。2013/06/12以降の版であれば不要
    $to->host = 'https://api.twitter.com/1.1/';

    // DEBUG状態でない場合は、つぶやく
    if (! $debug){
      $status = $to->post('statuses/update', ['status' =>"$tweet"]);
    }else{
      echo "I do not tweet a message.\n";
    }
?>
