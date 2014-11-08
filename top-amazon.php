##
## Amazon APIを使用して、売れ筋ランキング情報を取得し、
## 独自アルゴリズムで重み付けをして商品データベーすに登録する。
##
<?php
// 開発中のため、引数が設定されていなければtwitterに実際に投稿しない。
// また、データベースの更新も行わない
$debug=true;
if($argc>2){
   $debug=false;
}

function search_paa($nodeId,$debug) {
    
    echo $debug;

    //PostgreSQLパラメータ
    $dsn = 'pgsql:dbname=rankdb host=localhost port=5432';
    $user = 'postgres';
    $password = 'postgres';

    try{
    $dbh = new PDO($dsn, $user, $password);

    //Read My Amazon API access keys.
    $sql = "select associate_tag,accesskey_id,secret_accesskey from  amazon_apiinfo where appname='Brachiosaurus';";
    $stmt = $dbh->query($sql);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    $AccesskeyID = $record['accesskey_id'];
    $SecretAccessKey = $record['secret_accesskey'];
 
    $method = "GET";
    $host = 'webservices.amazon.co.jp';
    $uri = "/onca/xml";
 
    $params['AWSAccessKeyId'] = $AccesskeyID;
    $params['Service'] = 'AWSECommerceService';
    $params['Version'] = '2011-08-01';
    //$params['Operation'] = 'ItemSearch';
    $params['Operation'] = 'BrowseNodeLookup';
    $params['BrowseNodeId'] = $nodeId;
    //$params['ResponseGroup'] = 'Small,Images'; //詳細画像へのリンクを含むレスポンスグループ
    $params['ResponseGroup' ] = 'TopSellers'; //売れ筋ランキングのレスポンスグループ
    $params['AssociateTag'] = $record['associate_tag'];
    $params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
    $params['Availability'] = 'Available';
    $params['Sort'] = 'salesrank';

    }catch (PDOException $e){
    	   print('Error:'.$e->getMessage());
   	   die();
    }
    $dbh = null;


    ksort($params);
 
    //売れ筋ランキング用クエリ
    $query = array();
    foreach ($params as $param=>$value) {
        $param = str_replace("%7E", "~", rawurlencode($param));
        $value = str_replace("%7E", "~", rawurlencode($value));
        $query[] = $param . "=" . $value;
    }
    $query = implode("&", $query);
 
    $Signature = $method . "\n" . $host . "\n" . $uri . "\n" . $query;
    $Signature = base64_encode(hash_hmac("sha256", $Signature, $SecretAccessKey, True));
    $Signature = str_replace("%7E", "~", rawurlencode($Signature));
 
    $url = "http://" . $host . $uri . "?" . $query . "&Signature=" . $Signature;   //売れ筋ランキング用URL
    echo $url . "\n";
 
    $ret = "";
    $xml = simplexml_load_file($url);

    try{
    $dbh = new PDO($dsn, $user, $password);
        
	        if ($xml->BrowseNodes->Request->Errors->Error) {
	            echo "ERROR:" . $xml->BrowseNodes->Request->Errors->Error->Message . " \n";
        	    return false;
   	        }
    		else {
        	    foreach ($xml->BrowseNodes->BrowseNode->TopItemSet->TopItem as $Item) {
	    	        echo "Title :" . $Item->Title . "\n";
            	    echo "URL  : " . $Item->DetailPageURL . "\n";
            	    echo "ASIN : " . $Item->ASIN . "\n";
            	    echo "Group:" . $Item->ProductGroup . "\n";
            	    echo "\n";
            	    //それぞれの商品情報を取得する
            	        //ItemLookup用Params;
                        $params['Operation'] = 'ItemLookup';
                        $params['ResponseGroup'] = 'Large'; //詳細画像へのリンクを含むレスポンスグループ
                        $params['ItemId']=$Item->ASIN;
                        $params['IdType']='ASIN';
                        $params['Condition']='All';
                        $params['MerchantId']='All';
                        
                        ksort($params);
                        
                         //ItemLookup
                        $query2 = array();
                        foreach ($params as $param=>$value) {
                            $param = str_replace("%7E", "~", rawurlencode($param));
                            $value = str_replace("%7E", "~", rawurlencode($value));
                            $query2[] = $param . "=" . $value;
                        }
                        $query2 = implode("&", $query2);
                     
                        $Signature = $method . "\n" . $host . "\n" . $uri . "\n" . $query2;
                        $Signature = base64_encode(hash_hmac("sha256", $Signature, $SecretAccessKey, True));
                        $Signature = str_replace("%7E", "~", rawurlencode($Signature));
                     
                        $url2 = "http://" . $host . $uri . "?" . $query2 . "&Signature=" . $Signature; 
                        //echo $url2 . "\n";
                        //exit();
                     
                        $ret = "";
                        $xml2 = simplexml_load_file($url2);
                        //sleep(1);
                        //$xmlstr = file_get_contents($url2);
                         foreach ($xml2->Items->Item as $oneItem) {
                          $rank=$oneItem->SalesRank;
                          if(!$rank) $rank=99999;
                          $price=$oneItem->Offers->Offer->OfferListing->Price->Amount;
                          if(!$price) $price=0;
                          $saved=$oneItem->Offers->Offer->OfferListing->PercentageSaved;
                          if (!$saved) $saved=0;
                          $image=$oneItem->SmallImage->URL;
                          $content=str_replace(array("\r\n","\n","\r"), "", strip_tags($oneItem->EditorialReviews->EditorialReview->Content)); //タグおよび改行は取り除いて保存
                          if (!$content) $content=$Item->Title; //商品説明がないものはタイトルをcontent文字列に登録
                          // echo "SalesRank:" . $rank . "\n";
                          // echo "Price:" . $price . "\n";
                          // echo "Saved:" . $saved . "\n";
                          // echo "Image:" . $image . "\n";
                          // echo "Content :" . $content . "\n";
                          
                          //[todo]                         
                          // add score
                          $score = 0;   //add score
                          if ( $rank < 50 ) $score ++;
                          if ( $rank < 100 ) $score ++ ;
                          if ( $rank < 500 ) $score ++ ;
                          if ( $rank == 1 ) $score = $score + 3;   //ランキング１位は必ず紹介されるスコアに。
                          if ( $saved > 30 ) $score ++ ;
                          if ( $saved > 20 ) $score ++ ;
                          if ( $saved > 10 ) $score ++ ;
                          if ( $price < 1000 ) $score -- ;    //万が一売れても利益にならないため。
                          if ( $price > 10000 ) $score = $score + 2 ;   //売れたら100円以上の利益になるため
                          if ( $score < 3 ) $score = $score + mt_rand(0,4);  //規則的ではつまらないのでボーナススコアをスコアが低いアイテムにランダムに加える
                          if ( $nodeId == "896246" ) $score = $score + 3;  //xxxは売れやすいので+3
                          // 売れない商品はスコアを0にする。
                          if ( $asin == "B004OR2EHM" ) $score = 0; //メリーズパンツ
                          if ( $asin == "B00139A7I2" ) $score = 0; //カシオ腕時計
                          if ( $asin == "B006M9U08K" ) $score = 0; //Office 2011
                          
                          
                          
                          
                          //DEBUGでない場合は、PostgreSQLにデータを登録する
                          $sql = "insert into ranktbl (title,url,asin,rank,price,saved,imgurl,content,pgroup,node,update,score) " . 
                          "values('" .  $Item->Title .
            	              "','" . $Item->DetailPageURL . 
            	              "','" . $Item->ASIN . 
            	              "','" . $rank  .
            	              "','" . $price  .
            	              "','" . $saved  .
            	              "','" . $image  .
            	              "','" . $content  .
              	              "','" . $Item->ProductGroup  .
              	              "','" . $nodeId .
              	              "',now()" .
              	              "," . $score . 
							  ")";
						  echo $sql . "\n";
		
			  
                          if (! $debug ){                          			
            	             $dbh->exec($sql);
                            echo "##########  NO DEBUG $debug ############: inserted.\n";
			  }else{
			    echo "##########   DEBUG   $debug ############: no insert.\n";
			  }
            	        }
                 
                     //sleep (繰り返しのリクエストでDOS攻撃と思われないようにする。)
                     sleep(1);
        	}
    	} 
	}catch (PDOException $e){
    		print('Error:'.$e->getMessage());
    		die();
    	}
    $dbh = null;
}
 
 //指定されたノードIDの売れ筋リストをDBに登録する
$nodeId = htmlspecialchars($argv[1], ENT_QUOTES, 'UTF-8');
 
echo search_paa($nodeId,$debug);

?>
