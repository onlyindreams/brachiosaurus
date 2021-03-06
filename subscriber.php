##
## 商品データベースからスコアの高いものを優先してつぶやき予約リストテーブルに登録する
## すでに登録されている同一の商品についてつぶやき用メッセージを更新する。
## 
<?php

	$debug=true;
	if($argc>1){
		$debug=false;
	}
	echo $debug;
    
	//PostgreSQLパラメータ
	$dsn = 'pgsql:dbname=rankdb host=localhost port=5432';
	$user = 'postgres';
	$password = 'postgres';

    try{
    $dbh = new PDO($dsn, $user, $password);   //DB接続ハンドルを取得
    
    //   抽出クエリ
    $sql = "select node,score,update,asin,rank,title,saved,price,substring(content,1,60) as scontent,url" . 
     			" from ranktbl where update=now()::date order by update desc,score desc";
     $topscore=0;
     $secondscore=0;
     $limit = 0;
     $oldscore=0;
     foreach ($dbh->query($sql) as $row) {      //ここで全行抽出する。
          if ( $oldscore != $row['score'] ) $limit = 0; //limit値初期化
          $message = $row['scontent'];    //抽出したコンテンツ(60文字)。ここに条件に会わせてキーワードを抽出していく。
          //  scoreは2以上を登録する。
          if ( $row['score'] < 2 ){
              continue;
          }
          //500円以下は記載しない & 値段を追加
          if ( $row['price'] > 500 ){
              $message = "¥" . $row['price'] . " " . $message;
          }else{
              continue;
          }
          if ( $topscore < $row['score'] ){
             $topscore = $row['score'];   //topscoreを登録
             $secondscore = $topscore -1 ;
          }
          //スコアが低いものはここでしぼられる。
          //30%以上の確率 + 最高3つが登録される。
          if ( $topscore != $row['score'] && $secondscore != $row['score'] ){   //top|secondでなかったら登録できる数は3つまで かつ 30%の確率で登録できる
            if ( mt_rand(0,2) != 0 ) continue;  //30%の確率で登録できる
            $limit++;
             if ( $limit > 3 ) {
                $limit = 0;
                continue;   //同じスコア3つ以上は登録しない
             }
          }
          //scoreが3以下は2つしか登録させない
          if ( $row['score'] < 4 ){
            //10%の確率で登録できる
            if ( mt_rand(0,9) != 0 ) continue;
          }
          //ASINもペアで抽出し、登録
          //   if  ( $node == 896246 ) then 【こちらはアダルト商品です】
          if ( $row['node'] == 896246 ) {
                $message = "【18禁】 " . $message;
          }
          //   if ( $saved > 30 ) then 【お得】+ 割引率
          if ( $row['saved'] > 30 ) {
                $message = "【お得 " .  $row['saved']  . "%割引! 】 " . $message;
          }
          //if ( $rank < 20 ) then 【大人気】
          if ( $row['rank'] < 20 ) {
               if ( $row['rank'] == 1 ) {
                   $message = "【ランキング１位】 " . $message;
               }else{
                   $message = "【大人気】" . $message ;
               }
          }
          //   URLを文章に追加
          $message = $message . "...    " . $row['url'];
          $asin = $row['asin'];
          //既にASINが登録済みであれば対象行を新しいメッセージ文で更新する。
          //なければ新しいメッセージを登録する。
          $sql = "with upsert as ( update reservation set count=0,tweet= '" . $message . "' where asin='" . $asin . "' returning *) " .
          			"insert into reservation ( tweet,asin ) select '" . $message . "' ,'" . $asin . "' where not exists (select * from upsert);";
          echo $message . PHP_EOL;
	  //debug中でなければ更新を実施
	  if (! $debug){
             $dbh->exec($sql);
	  }else{
	     echo "DEBUG: no update.\n";
	  }

     
     }
     
     // 1ヶ月前のデータは不要なため削除する
     $sql = "delete from reservation where registime < now() - '1 month'::interval;";
     if (! $debug){
        $dbh->exec($sql);
        echo "OK deleted.\n";
     }else{
        echo "DEBUG: no delete.\n";
     }

    }catch (PDOException $e){
    		print('Error:'.$e->getMessage());
    		die();
    }
    $dbh = null;
    
?>
