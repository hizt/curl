<?php
namespace T;

/**
 * Class T\Curl
 * version : 2.0.1
 */

class Curl
{
    const CHARSET_UTF8 = 'UTF-8';
    //CURL配置项目
    private $url;
    private $timeout = 20;       //curl_time_out
    private $userAgent = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.48 Safari/537.36';
    private $urlCharset = self::CHARSET_UTF8;
    private $curl;
    private $cookie ;  //array('phpsessionid'=>'hajsdfjksofjo' , ……)
    private $postData ; //array('name'=>'123','password'=>'123123')
    private $referer ;
    private $requestHeader;
    private $multipart_form_data = false;  //当上传了文件的时候为true ，否则为false


    //运行后的结果
    private $resultContent; //curl_exec的结果中的网页内容
    private $resultHeader;  //curl_exec 的结果中的Head头
    private $resultHttpCode;
    private $resultInfo; //curl_exec 的运行信息
    private $lastRequest ;

    private $setContented = false; //con是不是外部设置的，如果是外部设置的那么preg的时候，不需要验证状态码

	function __construct()  //$gzip 表示该网页是否经过压缩,如果为ture则会进行gzip解码
	{
        $this->curl = curl_init();
        //curl默认配置
        curl_setopt($this->curl ,CURLOPT_RETURNTRANSFER , true);
        curl_setopt($this->curl ,CURLOPT_HEADER, true);
	}

    function setUrl($url , $postData = array() , $charset = null , $multipart_form_data = false){
        $this->url = $url;
        $this->setPostData($postData , $charset , $multipart_form_data);
        return $this;
    }

    function getUrl(){
        return $this->url;
    }


    function setTimeout($timeout){
        if(!empty($timeout))
            $this->timeout = $timeout;
        return $this;
    }

    function getTimeout(){
        return $this->timeout;
    }

    function setCharset($charset){
        if(!empty($charset))
            $this->urlCharset = $charset;
        return $this;
    }

    function getCharset(){
        return $this->urlCharset;
    }

    function setUserAgent($agent){
        if(!empty($agent))
            $this->userAgent = $agent;
        return $this;
    }

    function getUserAgent(){
        return $this->userAgent;
    }

    function setReffere($referer){
        $this->referer = $referer;
        return $this;
    }


    function getReffere(){
        return $this->referer;
    }

    function setRequestHeader($headerArr){
        if(is_array($headerArr) && !empty($headerArr))
            $this->requestHeader = $headerArr;
        else
            $this->requestHeader = null;
        return $this;
    }

    private function setPostData(array $postData ,$charset = null , $multipart_form_data = false){
        $this->setCharset($charset);
        $this->multipart_form_data = $multipart_form_data;
        if(empty($postData)){
            $this->postData = null;
            return $this;
        }

        //TODO 部分网站可能数据不需要转换，转换后反而无法正常post
//        if($this->getCharset() !== self::CHARSET_UTF8 )
//            $postData = self::parseDataCharset($postData , self::CHARSET_UTF8 , $this->getCharset() );

        $this->postData = $postData;
        return $this;
    }

    private static  function parsePostDataToString( $postData ){
        $re = '';
        if(!empty($postData)){
            foreach($postData as $k=>$v) {
                $k = urlencode($k);
                $v = urlencode($v);
                $re .= "{$k}={$v}&";
            }
        }
        return trim($re , '&');
    }


    function setCookie($cookie){
        if(empty($cookie))
            $this->cookie = null;
        else
            $this->cookie = $this->parseCookieToArray($cookie);
        return $this;
    }

    function pushCookie($cookie){
        if(!empty($cookie)){
            $cookie = $this->parseCookieToArray($cookie);
            $this->cookie = self::merge($this->cookie , $cookie);
        }
        return $this;
    }

    function getCookie(){
        return $this->cookie;
    }

    function getCookieString(){
        $re = '';
        if($this->cookie){
            foreach($this->cookie as $k=>$v){
                $re .= "{$k}={$v}; ";
            }
        }
        return trim( trim($re) , ';' );
    }

    private function setLastRequest($field , $value ){
        $this->lastRequest[$field] = $value;
    }

    function getLastRequest(){
        return $this->lastRequest;
    }

    private function parseCookieToArray($cookie){
        $re = array();
        if(empty($cookie))
            return $re;

        if(is_string($cookie)) {
            $temp = explode(';', trim($cookie));
            foreach ($temp as $k => $v) {
                $kv = explode('=' , $v,  2);
                $re[$kv[0]] = isset($kv[1]) ? $kv[1] : '';
            }
        }

        if(is_array($cookie)){
            foreach($cookie as $k=>$v){
                $v = trim( trim($v) , ';' );
                if(is_numeric($k)){
                    list($key , $val) = explode( '=',trim($cookie));
                    $re[$key] = $val;
                }

                else
                    $re[$k] = $v;
            }
        }
        return $re;
    }


    public static function parseDataCharset($data , $inCharset = self::CHARSET_UTF8 , $outCharset = self::CHARSET_UTF8 ){
        if( $inCharset == $outCharset)
            return $data;

        if(!is_array($data)){
            $temp =  iconv($inCharset,$outCharset.'//IGNORE',$data);
            if(strtolower( $inCharset ) == 'big5'){ //BIG5特殊处理
                $temp = self::unescape2utf8($temp);
            }
            return $temp;
        }
        else{
            foreach($data as $k=>$v)
                $data[$k] = self::parseDataCharset($v , $inCharset , $outCharset);
            return $data;
        }
    }


    public function run($autoSetCookie = true){
        $this->clear();

        if(!$this->getUrl())
            return false;

        //ini_set('pcre.backtrack_limit', $this->getPregMax() );
        curl_setopt($this->curl , CURLOPT_URL , $this->getUrl() );
        if(strpos($this->getUrl(),'https') !== false)  //https请求
        {
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        if($this->getReffere()){
            curl_setopt($this->curl , CURLOPT_REFERER , $this->getReffere());
        }
        curl_setopt($this->curl , CURLOPT_TIMEOUT, $this->getTimeout() );
        curl_setopt($this->curl , CURLOPT_USERAGENT,$this->getUserAgent());

        curl_setopt($this->curl,CURLOPT_POST, !empty($this->postData) );
        if(!empty($this->postData)){
            if($this->multipart_form_data)  //带有文件的postData
                curl_setopt($this->curl,CURLOPT_POSTFIELDS,  $this->postData );
            else                            //不含文件的postData
                curl_setopt($this->curl,CURLOPT_POSTFIELDS,  self::parsePostDataToString( $this->postData ));
        }

        if(!empty($this->requestHeader) && is_array($this->requestHeader)){
            $headerArr = array();
            foreach($this->requestHeader as $k=>$v){
                if(!is_numeric($k))
                    $headerArr[] = $k .':' . $v;
                else
                    $headerArr[] = $v;
            }
            curl_setopt($this->curl , CURLOPT_HTTPHEADER  , $headerArr);
        }


        curl_setopt($this->curl, CURLOPT_COOKIE , $this->getCookieString() );

        $result = curl_exec($this->curl) ;

        $this->resultHttpCode = curl_getinfo($this->curl,CURLINFO_HTTP_CODE);
        $this->resultInfo = curl_getinfo($this->curl);
        $this->resultHeader =  substr( $result , 0 , $this->getResultHeaderSize()); //ResultHeader 不需要编码转换
        $this->resultContent =  $this->parseDataCharset( substr($result , $this->getResultHeaderSize() ) , $this->getCharset() ,self::CHARSET_UTF8 ); //ResultContent 需要编码转换

        $this->setLastRequestData();
        $this->setReffere(  $this->getUrl() );
        if($autoSetCookie)
            $this->getAndPushResultCookie();

        return $this;
    }

    private function setLastRequestData(){
        $this->setLastRequest('url',$this->getUrl());
        if(!empty($this->postData)){
            $this->setLastRequest('request_method', !empty($this->postData) ? 'post' : 'get');
            $this->setLastRequest('request_post_data',$this->postData );
        }
        if(!empty($this->requestHeader) ){
            $this->setLastRequest('request_header',$this->requestHeader);
        }
        $this->setLastRequest('timeout',$this->getTimeout());
        $this->setLastRequest('user_agent',$this->getUserAgent());
        $this->setLastRequest('charset',$this->getCharset());
        $this->setLastRequest('cookie',$this->getCookieString());
        $this->setLastRequest('referer',$this->getReffere());
    }


    public function getResultHeader(){
        return $this->resultHeader;
    }

    public function getResultHttpCode(){
        return $this->resultHttpCode;
    }


    public function getResultContent(){
        return $this->resultContent;
    }

    public function getResultInfo(){
        return $this->resultInfo;
    }

    public function getResultHeaderSize(){
        return $this->resultInfo['header_size'];
    }

    function getResultRedirect(){
        $url = $this->resultInfo['redirect_url'];
        if(empty($url)){
            preg_match('/Location:(.+)/',$this->resultHeader , $preg);
            if(empty($preg[1])){
                echo "找不到redierct url";
                exit;
            }
            return trim($preg[1]);
        }
        return $url;
    }

    function getUploadSize()
    {
        return $this->resultInfo['size_upload'];
    }

    function getDownloadSize()
    {
        return $this->resultInfo['size_download'];
    }

    function getUploadSpeed(){
        return $this->resultInfo['speed_upload'];
    }

    function getDownloadSpeed()
    {
        return $this->resultInfo['speed_download'];
    }

    function getResultStatus(){
        return $this->resultInfo['http_code'];
    }
	
	private function clear()
	{
		$this->resultHeader = $this->resultContent = $this->resultInfo = $this->lastRequest = null;
		$this->setContented = false;
	} 

	function getResultCookie()
	{
        $cookie = '';
		preg_match_all('/Set-Cookie:(.+);/iU',$this->resultHeader , $re );
		if($re[1])
			$cookie = implode( ';',$re[1] ) . '; ';
		else if ( preg_match('/Set-Cookie:(.+);/iU',$this->resultHeader , $re ) )
			$cookie =  $re[1];
        //$cookie .="test=test;aaa=av;aaa";
        return self::parseCookieToArray($cookie);
	}

    function getAndPushResultCookie(){
        $this->pushCookie( $this->getResultCookie() );
        return $this;
    }


    /**
     * 从外部传入一个resultContent进来
     * @param $content
     */
    function setResultContent($content){
        $this->resultContent = $content;
        $this->setContented = true;
    }


    private static function unescape2utf8($str) {
        $str = rawurldecode($str);
        preg_match_all("/(?:%u.{4})|&#x.{4};|&#\d+;|.+/U",$str,$r);
        $ar = $r[0];
        //print_r($ar);
        foreach($ar as $k=>$v) {
            if(substr($v,0,2) == "%u"){
                $ar[$k] = iconv("UCS-2BE","UTF-8",pack("H4",substr($v,-4)));
            }
            elseif(substr($v,0,3) == "&#x"){
                $ar[$k] = iconv("UCS-2BE","UTF-8",pack("H4",substr($v,3,-1)));
            }
            elseif(substr($v,0,2) == "&#") {

                $ar[$k] = iconv("UCS-2BE","UTF-8",pack("n",substr($v,2,-1)));
            }
        }
        return join("",$ar);
    }


    private static function merge($arr1 , $arr2){
        if(empty($arr1))
            return $arr2;
        if(empty($arr2))
            return $arr2;
        return array_merge($arr1 , $arr2);
    }

    function __destruct(){
        $this->curl = null;
    }

}
