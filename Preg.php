<?php
class Preg{
    private $maxPregByte = 10485760;    //preg允许的匹配最大内容，10M
    private $content ;

    function __construct(){

    }

    function setContent($content){
        $this->content = $content;
        return $this;
    }


    function setPregMax($pregMax){
        if(!empty($pregMax))
            $this->maxPregByte = $pregMax;
        return $this;
    }


    private function getPregMax(){
        return $this->maxPregByte;
    }




    private function checkErr($mat,$split,$err)  //检查错误
    {
        $re["status"]=0;
        if(!is_array($mat) || empty($mat))
        {
            $re["msg"]='匹配的正则表达式必须为数组!';
            return $re;
        }

        foreach($mat as $v)
        {
            if(strpos($v,'|a') === strlen($v)-2)
                $v = substr($v ,  0 ,strpos($v,'|a'));

            if( preg_match($v,"")===false )
            {
                $re["msg"]='匹配的正则表达式语法错误!' . $v;
                return $re;
            }
        }

        if($split && preg_match( $split ,"")===false)
        {
            $re["msg"]="分段的正则表达式结构错误!";
            return $re;
        }

        if(!$this->content) //非外部设置的内容
        {
            $re["msg"]="未设置内容" ;
            return $re;
        }


        if($err)
        {
            $errs = array();
            if(is_string($err))
                $errs[0] = $err;
            else
                $errs = $err;
            foreach($errs as $v)
            {
                if(preg_match($v , '' ) === false)
                {
                    $re["msg"]="匹配错误页面的正则表达式不正确! " . $v;
                    return $re;
                }

                if(preg_match( $v ,$this->resultContent)===1)
                {
                    $re["msg"]="错误的页面!";
                    return $re;
                }

            }
        }

        $re["status"]=1;
        return $re;
    }


    /**
     * 传入正则表达式 截断resultContent
     * @param $reg string 正则表达式
     * @return $this
     */
    function substrContent($reg){
        preg_match($reg ,$this->resultContent , $re );
        if(!empty($re))
            $this->resultContent = end($re);
        return $this;
    }

    /**
     * 匹配内容
     * @param $mat array   如：array('id'=>'lsitid(.+)sss','key'=>'keysdfaslfj(.+)key') ,  返回正则表达式括号内匹配的内容
     * @param string $split 将整个内容分成各个部分来进行匹配多项
     * @param int $count
     * @param null $err 数组或字符串，如果页面内容包含匹配到$err的正则，则表示进入了错误页面
     * @return mixed
     */
    function preg( $mat , $split='', $count = 0, $err=NULL)   //匹配并返回
    {
        $stime = microtime(true);
        $checkRe = $this->checkErr($mat,$split,$err);
        $re = $checkRe;
        if($checkRe["status"]==1)
        {
            if($split)
            {
                if(is_string($split))
                {
                    preg_match_all( $split ,$this->resultContent,$split_con);
                    $s_con = $split_con[0];
                }
                else
                {
                    $re["status"]=0;
                    $re["msg"]="分段的正则表达式必须为字符串!";
                    return $re;
                }
            }
            else
                $s_con[0] = $this->resultContent;

            $num = 0;
            foreach($s_con as $key=>$val)
            {
                $num++;
                if($count && $count < $num)
                    break;

                foreach($mat as $k=>$v)
                {
                    $re['data'][$key][$k] = $this->pregMutil($val , $v);
                }
            }

        }

        $re['preg_time'] =  round( microtime(true) - $stime , 3)  ;
        return $re;
    }


    /**
     *
     * 特殊的正则表达式匹配：
     * 支持模式： /aaaaaa/sU|a  ，此时会匹配所有的内容 ，返回的内容是一个数组
     * @param $con
     * @param $reg
     * @return array|mixed|null|string
     */
    private function pregMutil($con , $reg)
    {
        if(strpos($reg , '|a') === strlen($reg)-2 )
        {
            $reg = substr($reg , 0 , strpos($reg,'|a'));
            preg_match_all($reg, $con, $pipei);
        }
        else
            preg_match($reg,$con,$pipei);

        array_shift($pipei);
        $re = count($pipei) === 0 ? null : end($pipei);

        if(is_string($re))
            return trim($re);
        if(is_array($re) )
            return explode('|' , $re);
        return $re;
    }


}