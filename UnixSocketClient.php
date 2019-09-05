<?php
error_reporting(E_ALL);
set_time_limit(0);

$unix_file = '/tmp/server.sock';

// 1. 创建
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

if ($socket == FALSE) {
    echo 'create fail: ' . socket_strerror(socket_last_error());
}

// 2. 链接
$result = socket_connect($socket, $unix_file);
if ($result == FALSE) {
    echo 'connect failed...' . PHP_EOL . PHP_EOL;
} else {
    echo 'connect success...' . PHP_EOL . PHP_EOL;
}

$in = 'student ' . (int)$socket . ' come in';

// 3. 向服务端写入
if (!socket_write($socket, $in, strlen($in))) {
    echo 'write fail: ' . socket_strerror(socket_last_error());
}

$getMsg = '';
$len    = 8129;// 每次读取数据的字节数

// 3. 从服务端读取全部的数据
do {
    $out    = @socket_read($socket, $len);
    $getMsg .= $out;
    if (strlen($out) < $len) {
        break;
    }
} while (true);

echo $getMsg . PHP_EOL;

// 4. 关闭
echo 'close socket...' . PHP_EOL;
socket_close($socket);
echo 'closed ok....';