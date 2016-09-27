<?php
include_once 'src/T/Curl.php';

$curl = new T\Curl();
print_r( $curl->setUrl('http://www.baidu.com')->run()->getResultContent() );


