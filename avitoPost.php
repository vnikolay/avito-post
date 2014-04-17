<?php

//
//print_r($_SERVER['HTTP_USER_AGENT']);
//exit(0);


$avitoPost = new avitoPost();




$data = array('title' => 'iPhone 5 16Гб белый',
              'description' => 'iPhone продолжает оставаться самым востребованным смартфоном в мире. Последняя модель iPhone 5 превосходит по сумме показателей любой смартфон на рынке и является самым перспективным выбором. Давайте подробнее остановимся на его достоинствах.',
              'price' => '22990' /*,
              'img' => '@/Users/igorcherkasov/Sites/avitoPost/img/uresource_e20054ca8f2aefc1e494f5315d9413e15214d8417b90f_220x220.jpg'*/
            );

$avitoPost->post($data);


class avitoPost {

    public $debug = 0;
    public $apiKey = '24f84a06b7a508cd06c590836bcc6d73';

    public $avitoLoginUrl = 'https://www.avito.ru/profile/login';

    public $avitoLogin = 'igor.v.cherkasov@gmail.com';
    public $avitoPassword = '3b9ac9ff';

    public $avitoFirstPostUrl = 'http://www.avito.ru/additem';
    public $avitoFinalPostUrl = 'http://www.avito.ru/additem/confirm';

    public $avitoImageUrl = 'http://www.avito.ru/additem/image';

    public $cookieFile = 'cookie.txt';

    private $httpPath = '';

    private $postFirstSubmit = 'main_form_submit';

    public $postSellerName = 'igor.v';
    public $postSellerEmail = 'igor.v.cherkasov@gmail.com';
    public $postAllowEmails = '1';
    public $postSellerPhone = '8 343 345-25-31';
    public $postLocationId = '654070';
    public $postParams = array('iphone' => '623');

    public $postCategory = array('telephone'=>'84');

    public $fileName = 'captcha.jpg';

    function __construct() {
        $this->httpPath = $_SERVER['DOCUMENT_ROOT'];
//        unlink($this->cookieFile);
    }

    private function getPageTitle($pageSource) {
        if (preg_match_all('/<title>(.*?)<\/title>/', $pageSource , $pageTitles)){
//            print_r($pageTitles);
            return $pageTitles[1][0];
        } else return false;
    }

    private function isLoggedIn($pageSource) {

        if (preg_match_all('/<span\sclass\=\"userinfo\-details\">(.*?)<\/span>/s', $pageSource , $pageTitles)){
            print_r($pageTitles);
            return $pageTitles[1][0];
        } else return false;
    }

    public function login() {

        $this->curlExec($this->avitoLoginUrl,
                        $loginFields = array(
                                  "login" => $this->avitoLogin,
                                  "password" => $this->avitoPassword));
        return $this;
    }

    public function post($data) {

        $pingPage = $this->curlExec($this->avitoFirstPostUrl,array(),'get');

        $pingTitle = $this->getPageTitle($pingPage);

        if (trim($this->isLoggedIn($pingPage)) == 'Вход и регистрация') {
            print " \n im not logged in, logginning \n ";
            $this->login();
            sleep(5);
            return $this->post($data);
        }

        $post['seller_name'] = $this->postSellerName;
        $post['email'] = $this->postSellerEmail;
        $post['allow_mails'] = $this->postAllowEmails;
        $post['phone'] = $this->postSellerPhone;

        $post['location_id'] = $this->postLocationId;

        $post['category_id'] = $this->postCategory['telephone'];
        $post['params[143]'] = $this->postParams['iphone'];

        $post['service_code'] = 'free';
        $post['manager'] = '';
        $post['metro_id'] = 0;
        $post['district_id'] = '';

        $post['main_form_submit'] = $this->postFirstSubmit;

        if ($post['img']) {

        $img = realpath('img/uresource_e20054ca8f2aefc1e494f5315d9413e15214d8417b90f_220x220.jpg');

        print ' \n file realpath \n';
        print_r($img);
        print ' \n ';


        $imageId = $this->curlExec($this->avitoImageUrl,
                                        array('image' => "@".$img)

        );

        print '\n image id \n';
        print_r($imageId);


        $imgDecoded = json_decode($imageId);

        print '\n jsondecoded \n';
        print_r($imgDecoded);

        $post['images[]'] = $imgDecoded->id;

        }

        foreach ($data as $k=>$v) {
            if ($k == 'img') continue;
            $post[$k] = $v;
        }

        print " \n posting first part, sleep 10 sec \n ";
        sleep(10);

//        exit(0);
        $result = $this->curlExec($this->avitoFirstPostUrl, $post,'post',$this->avitoFirstPostUrl);

        print " \n after first post title \n ";
        print_r($this->getPageTitle($result));
        print " \n";

        $this->getCaptcha($result);

        $captchaText = $this->recognize($this->fileName, $this->apiKey, true, "antigate.com");

        print "\n CAPTHA TEXT \n";
        print_r($captchaText);
        print " \n";

        if ($captchaText) {
//            $finalPost['email'] = $post['email'];
//            $finalPost['location_id'] = $this->postLocationId;
            $finalPost['captcha'] = $captchaText;
            $finalPost['subscribe-position'] = 0;
            $finalPost['done'] = 'Далее &#8594;';
        } else {
            print " \n No captcha text provided, return \n ";
            return false;
        }

        $finalContent = $this->curlExec($this->avitoFinalPostUrl, $finalPost);

        print " \n Final Content \n ";
        print_r($finalContent);


        print " \n Final title \n ";
        print_r($this->getPageTitle($finalContent));
        print " \n ";

        return $this;
    }

    private function getCaptcha($content) {
        $captchaimages = array();

        if (preg_match_all('/<img[^>]*id\=\"captcha_image\"[^>]*src\=\"([^\"]*)\"[^>]*>/s', $content , $captchaimages)){

//            print_r($captchaimages);

            $url = 'http://www.avito.ru';
            $captcha = $captchaimages[1][0];

            //echo "<img src='$url"."$captcha'>"; //when echoed the pic shows correctly

            if ($this->debug) {
                echo "$url"."$captcha";
            }
            $file = $this->curlExec("$url"."$captcha",array(), "get");

            if ($this->debug) {
                print "file length: ".strlen($file)." \n";
                print_r($file);
            }

            //save the pic on HDD
            file_put_contents($this->fileName,  $file);

    }
}

    private function curlExec($url, $post, $type = "post", $referer = '') {

        $ch = curl_init();

        if(strtolower((substr($url ,0,5))=='https')) { // если соединяемся с https
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        if (!$referer) $referer = $url;
        // откуда пришли на эту страницу
        curl_setopt($ch, CURLOPT_REFERER, $referer);


        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);


        $user_agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)";
//        $post = "username=Andrey+z668&password=dAJsB1lu&redirect=&login=%C2%F5%EE%E4";
        $header [] = "Accept: text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/jpeg, image/jpg,image/gif, image/x-xbitmap, */*;q=0.1";
        $header [] = "Accept-Language: ru-RU,ru;q=0.9,en;q=0.8";
        $header [] = "Accept-Charset: Windows-1251, utf-8, *;q=0.1";
        $header [] = "Accept-Encoding: deflate, identity, *;q=0";

//        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.102 Safari/537.36"


        curl_setopt($ch, CURLOPT_HTTPHEADER, $header );
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);


        if ($type == 'post') {
//            curl_setopt($ch, CURLOPT_HEADER, 1);
            // cURL будет выводить подробные сообщения о всех производимых действиях
//            curl_setopt($ch, CURLOPT_VERBOSE, 1);

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //сохранять полученные COOKIE в файл

        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
//        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);

        $result = curl_exec($ch);

        print_r($this->httpPath.'/'.$this->cookieFile);

        // Убеждаемся что произошло перенаправление после авторизации
        //if(strpos($result,"Location: home.php")===false) die('Login incorrect');
        curl_close($ch);

        return $result;
    }

    function recognize(
        $filename,
        $apikey,
        $is_verbose = true,
        $domain="antigate.com",
        $rtimeout = 5,
        $mtimeout = 120,
        $is_phrase = 0,
        $is_regsense = 0,
        $is_numeric = 0,
        $min_len = 0,
        $max_len = 0,
        $is_russian = 0
    )
    {
        if (!file_exists($filename))
        {
            if ($is_verbose) echo "file $filename not found\n";
            return false;
        }
        $postdata = array(
            'method'    => 'post',
            'key'       => $apikey,
            'file'      => '@'.$filename,
            'phrase'	=> $is_phrase,
            'regsense'	=> $is_regsense,
            'numeric'	=> $is_numeric,
            'min_len'	=> $min_len,
            'max_len'	=> $max_len,
            'is_russian'	=> $is_russian

        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,             "http://$domain/in.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,     1);
        curl_setopt($ch, CURLOPT_TIMEOUT,             60);
        curl_setopt($ch, CURLOPT_POST,                 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,         $postdata);
        $result = curl_exec($ch);
        if (curl_errno($ch))
        {
            if ($is_verbose) echo "CURL returned error: ".curl_error($ch)."\n";
            return false;
        }
        curl_close($ch);
        if (strpos($result, "ERROR")!==false)
        {
            if ($is_verbose) echo "server returned error: $result\n";
            return false;
        }
        else
        {
            $ex = explode("|", $result);
            $captcha_id = $ex[1];
            if ($is_verbose) echo "captcha sent, got captcha ID $captcha_id\n";
            $waittime = 0;
            if ($is_verbose) echo "waiting for $rtimeout seconds\n";
            sleep($rtimeout);
            while(true)
            {
                $result = file_get_contents("http://$domain/res.php?key=".$apikey.'&action=get&id='.$captcha_id);
                if (strpos($result, 'ERROR')!==false)
                {
                    if ($is_verbose) echo "server returned error: $result\n";
                    return false;
                }
                if ($result=="CAPCHA_NOT_READY")
                {
                    if ($is_verbose) echo "captcha is not ready yet\n";
                    $waittime += $rtimeout;
                    if ($waittime>$mtimeout)
                    {
                        if ($is_verbose) echo "timelimit ($mtimeout) hit\n";
                        break;
                    }
                    if ($is_verbose) echo "waiting for $rtimeout seconds\n";
                    sleep($rtimeout);
                }
                else
                {
                    $ex = explode('|', $result);
                    if (trim($ex[0])=='OK') return trim($ex[1]);
                }
            }

            return false;
        }
    }
}

?>