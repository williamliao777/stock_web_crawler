<?php

echo __DIR__;

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$request_url = 'https://www.taifex.com.tw/cht/3/futContractsDate';
//法人持倉量
$table_big_man_chip_index = 17;
//小型台指 自營商index(給定第一個後會自行抓取投信跟外資)
$table_person_index = 59;

$coi_file = fopen("coi.txt", "r");
$cursor = -1;

fseek($coi_file, $cursor, SEEK_END);
$char = fgetc($coi_file);

// =================  讀取最後一行 ================
while ($char === "\n" || $char === "\r") {
    fseek($coi_file, $cursor--, SEEK_END);
    $char = fgetc($coi_file);
}

$last_line = "";
while ($char !== false && $char !== "\n" && $char !== "\r") {
    /**
     * Prepend the new char
     */
    $last_line = $char . $last_line;
    fseek($coi_file, $cursor--, SEEK_END);
    $char = fgetc($coi_file);
}
// =================  讀取最後一行end ================

$last_line_explode = explode(",",$last_line);
//檔案裡最後時間+1(起始回補時間)
$last_date_str = date("Y/m/d", strtotime($last_line_explode[0] . "+ 1 day"));
//今日時間
$today_date_str = date("Y/m/d");
//開啟寫檔
$my_coi_file = fopen("coi.txt", "a");
//日期相等在停止
while($today_date_str !== $last_date_str){
    $formParams =  [
        'form_params' => [
            'queryType' => '1009',
            'goDay' => '',
            'doQuery' => 1,
            'dateaddcnt' => -1,
            'queryDate' => $last_date_str,
            'commodityId' => '',
        ],
    ];

    $client = new Client(array (
        'verify' => false
    ));
    $response = $client->request('POST', $request_url, $formParams);
    $html = $response->getBody()->getContents();

    $crawler = new Crawler($html);
    $crawler->filter('font')->each(function (Crawler $node, $i){
        global $chips;
        $chips[] = (int)str_replace(["	"," ", "\r", "\n", ","], "", $node->text());
    });

    $small_person_chip = $chips[$table_person_index] + $chips[$table_person_index + 6] + $chips[$table_person_index + 12];
    //清空參數
    $chips = [];
    if($small_person_chip === 0 ){
        $last_date_str = date("Y/m/d", strtotime($last_date_str . "+ 1 day"));
        continue;
    }
    //寫檔
    $txt = $last_date_str.",0.000000,0.000000,0.000000,". sprintf("%01.6f",$small_person_chip) .",0\n";
    fwrite($my_coi_file, $txt);
    $last_date_str = date("Y/m/d", strtotime($last_date_str . "+ 1 day"));

}
//關閉寫檔
fclose($my_coi_file);


echo "end";



