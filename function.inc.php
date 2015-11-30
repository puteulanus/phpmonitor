<?php
function tcp_check($host,$port,$timeout=5){
    $return = array(
        'status_code' => 0,
        'status' => '',
        'load_time' => 0,
    );
    //获取IP
    $host = gethostbyname(trim($host));
    if(preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',$host) == 0){
        $return['status_code'] = 2;
        $return['status'] = 'Get ip address error';
        return $return;
    }
    //尝试多次连接
    $time = 0;
    $success_times = 0;
    $oldErrorReporting = error_reporting();
    error_reporting($oldErrorReporting ^ E_WARNING);
    for ($i=0;$i<5;$i++) {
        $tmp_time = microtime(true);
        $sh = stream_socket_client("tcp://${host}:${port}", $errno, $errstr, $timeout);
        $tmp_time = (microtime(true) - $tmp_time) * 1000;
        if($sh){
            $time += $tmp_time;
            $success_times++;
        }else {
            $i++;
        }
    }
    error_reporting($oldErrorReporting);
    // 返回延迟信息
    if($success_times>0){
        $return['status_code'] = 1;
        $return['status'] = 'success';
        $return['load_time'] = round($time / $success_times,2);
        return $return;
    }else {
        $return['status_code'] = 3;
        $return['status'] = 'Connect error';
        return $return;
    }
}

function http_check($response,$timeout=30) {
    $return = array(
        'status_code' => 0,
        'status' => '',
        'load_time' => 0,
        'http_code' => 0,
    );
    // 判断是否存在url
    if(!array_key_exists('url',$response)){
        $return['status_code'] = 2;
        $return['status'] = 'find url error';
        return $return;
    }
    // 设定服务器IP
    // 判断是否能获取IP
    preg_match('#^(https?://)?(?<host>[^/]+)#',trim($response['url']),$host);
    $header = array();
    if(array_key_exists('ip',$response) && strlen(trim($response['ip']))>0){
        if(preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',trim($response['ip'])) == 0){
            $return['status_code'] = 3;
            $return['status'] = 'ip address error';
            return $return;
        }
        $ch = curl_init(preg_replace(preg_quote($host['host']),trim($response['ip']),trim($response['url']),1));
        $header[] = "Host: ${host['host']}";
    }else {
        if(preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',gethostbyname($host['host'])) == 0){
            $return['status_code'] = 3;
            $return['status'] = 'get ip address error';
            return $return;
        }
        $ch = curl_init(trim($response['url']));
    }
    // 初始化curl
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT,$timeout);
    // 设定header
    if(array_key_exists('header',$response) && strlen(trim($response['header']))>0){
        $header = array_merge($header,explode("\n",trim($response['header'])));
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    }
    // 设定请求类型
    if(array_key_exists('type', $response)){
        switch ($response['type']) {
            case 1:
                break;
            case 2:
                curl_setopt($ch, CURLOPT_POST, 1);
                if(!array_key_exists('post_data',$response)){
                    $response['post_data'] = '';
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response['post_data']);
                break;
            case 3:
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
            default:
                $return['status_code'] = 2;
                $return['status'] = 'find type error';
                return $return;
        }
    }else {
        $return['status_code'] = 2;
        $return['status'] = 'find type error';
        return $return;
    }
    // 设定cookie
    if(array_key_exists('cookie',$response) && strlen(trim($response['cookie']))>0){
        curl_setopt($ch,CURLOPT_COOKIE,trim($response['cookie']));
    }
    // 设定用户名密码
    if(array_key_exists('username',$response) && strlen($response['username'])>0){
        if(!array_key_exists('password',$response)){
            $response['password'] = '';
        }
        curl_setopt($ch, CURLOPT_USERPWD, "${response['username']}:${response['password']}");
    }
    // 设定是否允许重定向
    if(array_key_exists('can_301',$response) && $response['can_301']){
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    }
    // 连接
    $html = curl_exec($ch);
    // 获取信息
    $info = curl_getinfo($ch);
    // 根据信息判断修改返回
    $return['load_time'] = $info['total_time'] - $info['namelookup_time'];
    $return['http_code'] = $info['http_code'];
    if(!($info['http_code']>=200 && $info['http_code']<=300)){
        $return['status_code'] = 3;
        $return['status'] = 'Load page error';
        return $return;
    }
    // 设定过滤器
    if($response['type'] != 3 && array_key_exists('filter',$response) && strlen($response['filter'])>0) {
        if (!array_key_exists('filter_type', $response)) {
            $response['filter_type'] = 1;
        }
        switch ($response['filter_type']) {
            case 2:
                if (strpos($html, $response['filter']) == true) {
                    $return['status_code'] = 3;
                    $return['status'] = 'filter catch error';
                }
                break;
            default:
                if (strpos($html, $response['filter']) == false) {
                    $return['status_code'] = 3;
                    $return['status'] = 'filter catch error';
                }
                break;
        }
    }
    // 判断正常
    if($return['status_code'] == 0){
        $return['status_code'] = 1;
        $return['status'] = 'All right';
    }
    return $return;
}


$http = array(
    'url' => 'ip.cn',
    'type' => 1,
    'post_data' => '',
    'cookie' => '',
    'username' => '',
    'password' => '',
    'can_301' => false,
    'ip' => '',
    'filter' => 'IP',
);
//var_dump(http_check($http));
?>