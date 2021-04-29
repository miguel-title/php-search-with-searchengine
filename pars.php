<?php
$start = microtime(true);

$fo = fopen("/home/admin/web/364484-cx11106.tmweb.ru/public_html/setup.json", "r");
$setup = json_decode(fread($fo, 9999));
fclose($fo);

$currency = file_get_contents('https://openexchangerates.org/api/latest.json?app_id=ce3506c71f6e46f689288a67a4a25c03', false);
$currency = json_decode($currency);

$setup->currency = $currency->rates->RUB;
$setup->curdate = $currency->timestamp;

$fo = fopen("/home/admin/web/364484-cx11106.tmweb.ru/public_html/setup.json", "w");
fwrite($fo, json_encode($setup));
fclose($fo);

$fcat = true;
$skip = 0;
$json = "";
$i = 0;

$fo = fopen("/home/admin/web/364484-cx11106.tmweb.ru/public_html/log.txt", "a+");
fwrite($fo, "Начато обновление wiki в " . date('Y-m-d H:i:s') . "\n");
fclose($fo);

while($fcat) {

    $headers = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json" . PHP_EOL,
            'content' => '{"operationName":"search","variables":{"skip":' . $skip . ',"limit":16,"q":"","lang":"en"},"query":"query search($q: String!, $lang: String!, $skip: Int, $limit: Int) { search(q: $q, lang: $lang, skip: $skip, limit: $limit) { _id slug category }}"}',
        ),
    ));

    $cat = file_get_contents('https://wiki.cs.money/graphql', false, $headers);
    $cat = json_decode($cat);

    if(count($cat->data->search) > 0) {
        $skip += 16;
    } else {
        $fcat = false;
        break;
    }

    foreach($cat->data->search AS $key => $value) {

        if($value->category == "skin") {

            //echo $value->_id . " - " . $value->category . "\n";

            $headers = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json' . PHP_EOL,
                    'content' => '{"operationName":"skin","variables":{"id":"' . $value->_id . '"},"query":"query skin($id: ID!) {  skin(id: $id) {    price_trader_log {      name      values { price_trader_new }    }  }}"}',
                ),
            ));
            
            $price = file_get_contents('https://wiki.cs.money/graphql', false, $headers);
            $price = json_decode($price);

            if(count($price->data->skin->price_trader_log) > 0) {

                $skins = "";
                foreach($price->data->skin->price_trader_log AS $key1 => $value1) {
                    if($key1 > 0) { $skins .= ","; }
                    $skins .= '{"skin":' . json_encode($value1->name) . ',"price":"' . $value1->values[0]->price_trader_new . '"}';
                }
                if($i > 0) { $json .= ","; }
                $json .= '{"id":"' . $value->_id . '","link":"' . $value->slug . '","skins":[' . $skins . ']}';
                //echo "ok: [" . $i . "] " . $value->_id . "\n";
                $i ++;
            }

        }

    }

    //if($skip > 300) break;
}
$json = '{"create":"' . time() . '","data":[' . $json . ']}';

//var_dump(json_decode($json));

$fo = fopen("/home/admin/web/364484-cx11106.tmweb.ru/public_html/wikijson/wiki_" . time() . ".json", "w+");
fwrite($fo, $json);
fclose($fo);

$fo = fopen("/home/admin/web/364484-cx11106.tmweb.ru/public_html/log.txt", "a+");
fwrite($fo, "Закончено обновление wiki в " . date('Y-m-d H:i:s') . ". Время выполнения скрипта: " . round(microtime(true) - $start, 4) . " сек.\n");
fclose($fo);

//echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек.';
