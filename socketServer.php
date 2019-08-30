<?php
set_time_limit(0);

$ip   = '127.0.0.1';
$port = 8888;
static $connList = array();
// 1. 创建
if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == FALSE) {
    echo 'create fail：' . socket_strerror(socket_last_error());
}

// 2. 绑定
if (socket_bind($sock, $ip, $port) == FALSE) {
    echo 'bind fail：' . socket_strerror(socket_last_error());
}

// 3. 监听
if (socket_listen($sock, 4) == FALSE) {
    echo 'listen fail：' . socket_strerror(socket_last_error());
}

$count = 0;
$len   = 8129;// 每次读取数据的字节数

do {
    // 4. 阻塞，等待客户端请求
    if (($msgsock = socket_accept($sock)) == FALSE) {
        echo 'accept fail：' . socket_strerror(socket_last_error());
        break;
    } else {
        array_push($connList, (int)$msgsock);

        // 5. 读取客户端全部信息
        $talkback = '';
        do {
            $buf      = @socket_read($msgsock, $len);
            $talkback .= $buf;
            if (strlen($buf) < $len) {
                break;
            }
        } while (true);

        echo $talkback . PHP_EOL;

        // 6. 向客户端写入信息
        $msg = "server response " . (int)$msgsock;

        socket_write($msgsock, $msg, strlen($msg));

        print_r($connList);
        echo PHP_EOL;

    }

} while (true);

// 6. 关闭socket
socket_close($sock);