<?php
ini_set('memory_limit', '256M');

$fo = fopen("./setup.json", "r");
$fr = fread($fo, 9999);
$setup = json_decode($fr);
fclose($fo);

function reverse_parse_url(array $parts)
{
    $url = '';
    if (!empty($parts['scheme'])) {
        $url .= $parts['scheme'] . ':';
    }
    if (!empty($parts['user']) || !empty($parts['host'])) {
        $url .= '//';
    }   
    if (!empty($parts['user'])) {
        $url .= $parts['user'];
    }   
    if (!empty($parts['pass'])) {
        $url .= ':' . $parts['pass'];
    }
    if (!empty($parts['user'])) {
        $url .= '@';
    }   
    if (!empty($parts['host'])) {
        $url .= $parts['host'];
    }
    if (!empty($parts['port'])) {
        $url .= ':' . $parts['port'];
    }   
    if (!empty($parts['path'])) {
        $url .= $parts['path'];
    }   
    if (!empty($parts['query'])) {
        if (is_array($parts['query'])) {
            $url .= '?' . http_build_query($parts['query']);
        } else {
            $url .= '?' . $parts['query'];
        }
    }   
    if (!empty($parts['fragment'])) {
        $url .= '#' . $parts['fragment'];
    }
    
    return $url;
}

$imgarr = [];

if ($_GET["page"] == "" && $_POST) {
    function ftpDeleteDirectory($remotedir, $conn_id)
    {
        $files = ftp_nlist($conn_id, $remotedir);
        foreach ($files as $file)
        {
            ftp_delete($conn_id, $file);
        }   


        ftp_close($conn_id); // close the FTP stream 
    };

    function manageFTPFolder($setup, $remotedir){
        $ftp_server = $setup->ftpservername; // Address of FTP server.
        $ftp_port = $setup->ftpportnumber; // port of the FTP server
        $ftp_user_name = $setup->ftpusername; // Username
        $ftp_user_pass = $setup->ftppassword; // Password

        $conn_id = ftp_connect($ftp_server) or die("<span style='color:#FF0000'><h2>Couldn't connect to $ftp_server</h2></span>");        // set up basic connection

        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass) or die("<span style='color:#FF0000'><h2>You do not have access to this ftp server!</h2></span>");   // login with username and password, or give invalid user message

        if ((!$conn_id) || (!$login_result)) {  // check connection
            // wont ever hit this, b/c of the die call on ftp_login
            echo "<span style='color:#FF0000'><h2>FTP connection has failed! <br />";
            echo "Attempted to connect to $ftp_server for user $ftp_user_name</h2></span>";
            exit;
        } else {
            //echo "Connected to $ftp_server, for user $ftp_user_name <br />";
        }

        ftpDeleteDirectory($remotedir, $conn_id);


    }

    function getdoc ($url, $setup)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PROXY, "localhost:9050");
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $setup->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $output = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        
        //print_r($curl_error);

        return $output;
    }

    function grab_image ($url, $setup)
    {
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
        curl_setopt($ch, CURLOPT_PROXY, "localhost:9050");
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $setup->timeout);
        $raw = curl_exec($ch);
        curl_close ($ch);
        //if (file_exists($saveto)){
        //    unlink($saveto);
        //}

        $path = "./tmp/" . time() . basename($url);
        $remotedir = "./tmp/test/";
        $remotepath = $remotedir . time() . basename($url);
        
        $fp = @fopen($path, 'x');
        @fwrite($fp, $raw);
        @fclose($fp);

        $img = @getimagesize($path);
        $fsize = (@filesize($path) / 1024);

        if ($img[0] != "" && $img[1] != "") {
            if ($img[0] < $setup->width || $img[1] < $setup->height || $img[0] > $setup->maxwidth || $img[1] > $setup->maxheight) {
                unlink($path);
            }
        }
        if (file_exists($path) && ($fsize < $setup->size || $fsize > $setup->maxsize)) {
            unlink($path);
        }

        if (file_exists($path)) {
            upload_file($setup, $path, $remotepath, $remotedir);
            return array($path, $img[0], $img[1], $img["mime"], $fsize);
        } else {
            return false;
        }
        
    }    

    function upload_file($setup, $path, $remotepath, $remotedir){  
        $ftp_server = $setup->ftpservername; // Address of FTP server.
        $ftp_port = $setup->ftpportnumber; // port of the FTP server
        $ftp_user_name = $setup->ftpusername; // Username
        $ftp_user_pass = $setup->ftppassword; // Password

        $conn_id = ftp_connect($ftp_server) or die("<span style='color:#FF0000'><h2>Couldn't connect to $ftp_server</h2></span>");        // set up basic connection

        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass) or die("<span style='color:#FF0000'><h2>You do not have access to this ftp server!</h2></span>");   // login with username and password, or give invalid user message

        if ((!$conn_id) || (!$login_result)) {  // check connection
            // wont ever hit this, b/c of the die call on ftp_login
            echo "<span style='color:#FF0000'><h2>FTP connection has failed! <br />";
            echo "Attempted to connect to $ftp_server for user $ftp_user_name</h2></span>";
            exit;
        } else {
            //echo "Connected to $ftp_server, for user $ftp_user_name <br />";
        }

        $upload = @ftp_put($conn_id, $remotepath, $path, FTP_BINARY);  // upload the file
        if (!$upload) {  // check upload status
            echo "<span style='color:#FF0000'><h2>FTP upload of $path has failed!</h2></span> <br />";
        } 
        //else {
        //    echo "<span style='color:#339900'><h2>Uploading $remotepath Completed Successfully!</h2></span><br /><br />";
        //}
        ftp_close($conn_id); // close the FTP stream    
    }

    $remotedir = "./tmp/test/";
    manageFTPFolder($setup, $remotedir);
    $output = getdoc($setup->site . "/search/?q=" . str_replace(" ", "+", $_POST["q"]), $setup);
    preg_match_all('/&redirect_url=([^"]+)/u', $output, $out, PREG_PATTERN_ORDER);

    
    $limitCount = 30;
    
    foreach ($out[1] AS $key => $value) {
        $limitCount --;
        if ($limitCount == 0){
            break;
        }

        if ($key > $setup->searchcount) { break; }
        $site = getdoc($value, $setup);
        preg_match_all('/<img.*src="([^"]+)/u', $site, $siteimg, PREG_PATTERN_ORDER);
        foreach ($siteimg[1] AS $key1 => $value1) {
            if (strpos($value1, "http", 0) === false) {
                $url = parse_url($value);
                
                if (isset($url["path"])){
                    $url["path"] = preg_replace('/\/[a-zA-Z0-9-_]+\.[a-zA-Z0-9-_]+/u', '/', $url["path"]);
                }

                if (isset($url["query"])){
                    unset($url["query"]);
                }
                $value = reverse_parse_url($url);
                if (substr($value1, 0, 1) == "/") {
                    $value1 = $url["scheme"] . "://" . $url["host"] . $value1;
                } else {
                    $value1 = (substr($value, -1) == "/" ? $value : $value . "/") . $value1;
                }
            } else if (strpos($value1, "data:", 0) === false && strpos($value1, "data:", 1) === false) {
                continue;
                $value1 = "BASE64";
            }
            $img = grab_image($value1, $setup);
            if ($img === false) {
                continue;
            }
            $imgarr[] = array($value, $value1, $img[1], $img[2], $img[3], $img[4]);
        }
        preg_match_all("/<img.*src='([^']+)/u", $site, $siteimg, PREG_PATTERN_ORDER);
        foreach ($siteimg[1] AS $key1 => $value1) {
            if (strpos($value1, "http", 0) === false) {
                $url = parse_url($value);
                
                if (isset($url["path"])){
                    $url["path"] = preg_replace('/\/[a-zA-Z0-9-_]+\.[a-zA-Z0-9-_]+/u', '/', $url["path"]);
                }

                
                if (isset($url["query"])){
                    unset($url["query"]);
                }
                
                $value = reverse_parse_url($url);
                if (substr($value1, 0, 1) == "/") {
                    $value1 = $url["scheme"] . "://" . $url["host"] . $value1;
                } else {
                    $value1 = (substr($value, -1) == "/" ? $value : $value . "/") . $value1;
                }
            } else if (strpos($value1, "data:", 0) === false && strpos($value1, "data:", 1) === false) {
                continue;
                $value1 = "BASE64";
            }
            $img = grab_image($value1, $setup);
            if ($img === false) {
                continue;
            }
            $imgarr[] = array($value, $value1, $img[1], $img[2], $img[3], $img[4]);
        }
        
    }

}

?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" />

    <script src="https://kit.fontawesome.com/0b11900295.js" crossorigin="anonymous"></script>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <title>Onion parser</title>
  </head>
  <body>
    <div class="container mb-4 pb-4">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="/">.onion parser</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="/">Запрос</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/?page=setup">Настройки</a>
            </li>
            </ul>
        </div>
    </nav>

<?php
if($_GET["page"] == "") {
    if(isset($_POST["procent"])) {
        $setup->procent = $_POST["procent"];
    }
    if(isset($_POST["minprice"])) {
        $setup->minprice = $_POST["minprice"];
    }
?>

    <div class="jumbotron">
        <h1 class="display-4">Запрос</h1>
    </div>

    <form class="mt-4 form-inline" method="post" action="/?page=">
        <div class="form-group">
            <label for="q">Query</label>
            <input type="text" name="q" value="<?php echo $_POST["q"]; ?>" id="q" class="form-control mx-sm-3" aria-describedby="">
        </div>

        <label class="my-1 mr-2" for="save">save</label>
        <select class="custom-select my-1 mr-sm-2" name="save" id="save">
            <option value="0">В общей папке</option>
            <option value="1">В отдельной папке для запроса</option>
            <option value="2">Папки с url результата</option>
        </select>

        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="inlineCheckbox1" value="option1">
            <label class="form-check-label" for="inlineCheckbox1">jpg</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="inlineCheckbox1" value="option1">
            <label class="form-check-label" for="inlineCheckbox1">gif</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="inlineCheckbox1" value="option1">
            <label class="form-check-label" for="inlineCheckbox1">png</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="inlineCheckbox1" value="option1">
            <label class="form-check-label" for="inlineCheckbox1">svg</label>
        </div>

        <button type="submit" class="btn btn-primary my-1">Выполнить</button>
    </form>

<?php

echo '<table class="table mt-4">';
echo '<thead class="thead-dark"><tr><th>URL</th><th>Изображение</th><th>W</th><th>H</th><th>Тип</th><th>Вес</th></tr></thead>';
echo '<tbody>';

foreach ($imgarr AS $key => $value) {
    echo '<tr><td><a href="' . $value[0] . '" target="_blank">' . $value[0] . '</a></td><td><a href="' . $value[1] . '" target="_blank">' . $value[1] . '</a></td><td>' . $value[2] . '</td><td>' . $value[3] . '</td><td>' . $value[4] . '</td><td>' . $value[5] . '</td></tr>';
}

echo '</tbody></table>';

?>

<?php
} else if($_GET["page"] == "setup") {
    if($_POST) {
        if($_POST["size"]) {
            $setup->size = $_POST["size"];
        }
        if($_POST["width"]) {
            $setup->width = $_POST["width"];
        }
        if($_POST["height"]) {
            $setup->height = $_POST["height"];
        }
        if($_POST["maxsize"]) {
            $setup->maxsize = $_POST["maxsize"];
        }
        if($_POST["maxwidth"]) {
            $setup->maxwidth = $_POST["maxwidth"];
        }
        if($_POST["maxheight"]) {
            $setup->maxheight = $_POST["maxheight"];
        }
        if($_POST["searchcount"]) {
            $setup->searchcount = $_POST["searchcount"];
        }
        if($_POST["timeout"]) {
            $setup->timeout = $_POST["timeout"];
        }
        if($_POST["site"]) {
            $setup->site = $_POST["site"];
        }
        
        if($_POST["FTPServerName"]) {
            $setup->ftpservername = $_POST["FTPServerName"];
        }
        
        if($_POST["FTPPortNumber"]) {
            $setup->ftpportnumber = $_POST["FTPPortNumber"];
        }
        
        if($_POST["FTPUserName"]) {
            $setup->ftpusername = $_POST["FTPUserName"];
        }
        
        if($_POST["FTPPassword"]) {
            $setup->ftppassword = $_POST["FTPPassword"];
        }

        $fo = fopen("./setup.json", "w");
        fwrite($fo, json_encode($setup));
        fclose($fo);
    }
    ?>
        <div class="jumbotron">
            <h1 class="display-4">Настройки / Settings / Налаштування</h1>
        </div>

        <form method="post" action="/?page=setup">
        <div class="row">
            <div class="col-md-6">
                <h3>Настройки изображения / Image settings / Налаштування зображення</h3>
                <div class="form-row">
                    <div class="form-group col-md-4">
                    <label for="size">Размер / Size / Розмір, kbyte</label>
                    <input type="text" name="size" value="<?=$setup->size;?>" class="form-control" id="size">
                    </div>
                    <div class="form-group col-md-4">
                    <label for="width">Мин. ширина / Min width / Мін. ширина, px</label>
                    <input type="text" name="width" value="<?=$setup->width;?>" class="form-control" id="width">
                    </div>
                    <div class="form-group col-md-4">
                    <label for="height">Мин. высота / Min height / Мін. висота, px</label>
                    <input type="text" name="height" value="<?=$setup->height;?>" class="form-control" id="height">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h3>Настройки выдачи / Dispensing settings / Налаштування видачі</h3>
                <div class="form-row">
                    <div class="form-group col-md-6">
                    <label for="searchcount">Кол-во ссылок / Number of links / Кількість посилань</label>
                    <input type="text" name="searchcount" value="<?=$setup->searchcount;?>" class="form-control" id="searchcount">
                    </div>
                    <div class="form-group col-md-6">
                    <label for="timeout">Timeout</label>
                    <input type="text" name="timeout" value="<?=$setup->timeout;?>" class="form-control" id="timeout">
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-row">
                    <div class="form-group col-md-4">
                    <label for="maxsize">Макс. размер / Max size / Макс. розмір, kbyte</label>
                    <input type="text" name="maxsize" value="<?=$setup->maxsize;?>" class="form-control" id="maxsize">
                    </div>
                    <div class="form-group col-md-4">
                    <label for="maxwidth">Макс. ширина / Max width / Макс. ширина, px</label>
                    <input type="text" name="maxwidth" value="<?=$setup->maxwidth;?>" class="form-control" id="maxwidth">
                    </div>
                    <div class="form-group col-md-4">
                    <label for="maxheight">Макс. высота / Max height / Макс. висота, px</label>
                    <input type="text" name="maxheight" value="<?=$setup->maxheight;?>" class="form-control" id="maxheight">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-row">
                    <div class="form-group col-md-12">
                    <label for="site">URL поисковика / Search URL / URL пошукача</label>
                    <input type="text" name="site" value="<?=$setup->site;?>" class="form-control" id="site">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <h3>FTP settings</h3>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="FTPServerName">FTP Server Name</label>
                        <input type="text" name="FTPServerName" value="<?=$setup->ftpservername;?>" class="form-control" id="ftpservername">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="FTPPortNumber">FTP Port Number</label>
                        <input type="text" name="FTPPortNumber" value="<?=$setup->ftpportnumber;?>" class="form-control" id="FTPPortNumber">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="FTPUserName">UserName</label>
                        <input type="text" name="FTPUserName" value="<?=$setup->ftpusername;?>" class="form-control" id="FTPUserName">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="FTPPassword">Password</label>
                        <input type="password" name="FTPPassword" value="<?=$setup->ftppassword;?>" class="form-control" id="FTPPassword">
                    </div>
                </div>
            </div>

        </div>
        <button type="submit" class="btn btn-primary">Сохранить / Save / Зберегти</button>
        </form>
    
    <?php
    }
?>
    </div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
        <?php
        if(!isset($_GET["page"]) || $_GET["page"] == "") {
        ?>

        <?php
        } else if(isset($_GET["page"]) && $_GET["page"] == "setup") {
        ?>

        <?php
        }
        ?>
        } );
    </script>
  </body>
</html>