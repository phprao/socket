<?php
/**
 * ----------------------------------------------------------
 * date: 2019/9/6 11:45
 * ----------------------------------------------------------
 * author: Raoxiaoya
 * ----------------------------------------------------------
 * describe: socket_select 非阻塞客户端
 * ----------------------------------------------------------
 */

set_time_limit(0);

$ip   = '127.0.0.1';
$port = 8888;

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

$count   = 0;
$len     = 8129;// 每次读取数据的字节数
$clients = [];// 连接的客户端fd
$timeout = 3;// 每次select阻塞等待多少秒，获取在这段时间内的状态变化；0表示不等待，获取这个时刻的状态变化

// 4、轮训执行系统调用socket_select
do {

    $readFds      = array_merge($clients, array($sock));// 可读监听数组，将主socket加入，用以处理客户端连接
    $writeFds     = $clients;// 可写监听数组
    $exceptionFds = $clients;// 异常监听数组

    $ret = socket_select($readFds, $writeFds, $exceptionFds, $timeout);
    // echo PHP_EOL.'select---------'.PHP_EOL;
    if ($ret < 1 || empty($readFds)) {
        continue;
    }

    // 处理异常的状态
    if (!empty($exceptionFds)) {
        echo '[except] ';
        foreach ($exceptionFds as $efd) {
            deleteConn($efd, $clients);
        }
    }

    // 处理可读的状态
    if (in_array($sock, $readFds)) {
        echo '[read] ';
        // 处理新客户端的连接，如果并发连接该如何？todo
        echo 'connect in...' . PHP_EOL;
        $conn = socket_accept($sock);
        if ($conn == FALSE) {
            echo 'accept fail：' . socket_strerror(socket_last_error()) . PHP_EOL;
        } else {
            $no            = (int)$conn;
            $clients[$no]  = $conn;
            $writeFds[$no] = $conn;
        }
        // 处理完就该移除了
        $keyOfThis = array_search($sock, $readFds);
        unset($readFds[$keyOfThis]);
    }

    // 遍历剩余的可读fd，读取信息
    if (!empty($readFds)) {
        echo '[read] ';
        foreach ($readFds as $fd) {
            // 读取客户端全部信息
            $buf = @socket_read($fd, $len);
            if ($buf === false) {
                deleteConn($fd, $clients);
            } elseif ($buf) {
                echo $buf . PHP_EOL;
            }
        }
    }

    // 处理可写的状态
    if (!empty($writeFds)) {
        echo '[write] ';
        foreach ($writeFds as $wfd) {
            $msg = "server response from writefds " . (int)$wfd;
            if (@socket_write($wfd, $msg, strlen($msg)) === false) {
                deleteConn($wfd, $clients);
            }
        }
    }

    echo PHP_EOL . '----------------' . PHP_EOL;

} while (true);

function deleteConn($fd, &$clients)
{
    $no = (int)$fd;
    @socket_shutdown($clients[$no]);
    socket_close($clients[$no]);
    unset($clients[$no]);
}

// 5. 关闭socket
socket_close($sock);