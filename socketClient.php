<?php
error_reporting(E_ALL);
set_time_limit(0);

$ip = '127.0.0.1';
$port = 8888;
// $ip = '192.168.1.210';
// $port = 7002;

// 1. 创建
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

if( $socket == FALSE ) {
    echo 'create fail: ' . socket_strerror(socket_last_error());
}

// 2. 链接
$result = socket_connect($socket, $ip, $port);
if ( $result == FALSE) {
    echo 'connect failed...'.PHP_EOL.PHP_EOL;
}else{
	echo 'connect success...'.PHP_EOL.PHP_EOL;
}

$in = 'student '.(int)$socket.' come in';

// 3. 向服务端写入
if( !socket_write($socket, $in, strlen($in)) ) {
    echo 'write fail: ' . socket_strerror(socket_last_error());
}

// 3. 从服务端读取
while ( $out = @socket_read($socket, 8129) ) {
    echo $out.PHP_EOL;
}

// 4. 关闭
// echo 'close socket...'.PHP_EOL;
// socket_close($socket);
// echo 'closed ok....';