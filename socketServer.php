<?php
set_time_limit(0);

$ip = '127.0.0.1';
$port = 8888;
static $connList = array();
// 1. 创建
if( ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == FALSE ){
    echo 'create fail：' . socket_strerror(socket_last_error());
}

// 2. 绑定
if ( socket_bind($sock, $ip, $port) == FALSE ) {
    echo 'bind fail：' . socket_strerror(socket_last_error());
}

// 3. 监听
if( socket_listen($sock, 4) == FALSE ){
    echo 'listen fail：' . socket_strerror(socket_last_error());
}

$count = 0;

do{
    // 4. 阻塞，等待客户端请求
    if ( ($msgsock = socket_accept($sock)) == FALSE ) {
        echo 'accept fail：' . socket_strerror(socket_last_error());
        break;
    } else {
        array_push($connList,(int)$msgsock);
        // 5. 向客户端写入信息
        $msg = 'server response '.(int)$msgsock;
        socket_write($msgsock, $msg, strlen($msg));

        // 5. 读取客户端信息
        $buf = socket_read($msgsock, 8192);
        $talkback = $buf.PHP_EOL;
        echo $talkback;
        print_r($connList);
        echo PHP_EOL;
        
    }

    // 6. 关闭socket
    //socket_close($msgsock);

}while(true);

// 6. 关闭socket
socket_close($sock);