<?php
/**
 * 服务端
 *
 * 阻塞式 + 同步
 *
 * 同步：主动轮训可视为同步的
 * 异步：被动接收通知，一般是事件通知机制
 */

set_time_limit(0);

require_once __DIR__ . '/../lib/socketServerBase.php';
require_once __DIR__ . '/../lib/socketHelper.php';

class socketServerBIO extends socketServerBase
{
    public function __construct($host, $port)
    {
        parent::__construct($host, $port);
    }

    public function start()
    {
        do {
            // 阻塞，等待客户端请求
            if (($conn = socket_accept($this->serverSocket)) === false) {
                echo socket_strerror(socket_last_error()) . PHP_EOL;
                continue;
            } else {
                $no                 = (int)$conn;
                $this->clients[$no] = $conn;

                if (isset($this->events['connect']) && $conn) {
                    call_user_func($this->events['connect'], $this, $conn);
                }

                // 阻塞，读取客户端全部信息，如果此连接不发来数据，那么服务将一直阻塞在这里啥也干不了。
                if (is_resource($conn)) {
                    $buf = socketHelper::read($conn, $this->len);
                    if ($buf === false) {
                        $this->deleteConn($conn);
                    } else {
                        if (isset($this->events['receive'])) {
                            call_user_func($this->events['receive'], $this, $conn, $buf);
                        }
                    }
                } else {
                    $this->deleteConn($conn);
                }
            }

        } while (true);
    }

    public function __destruct()
    {
        parent::__destruct();
    }
}

/*************************************************************************************/

$socketServer = new socketServerBIO('0.0.0.0', 8888);

$socketServer->on('connect', function ($socketServer, $socket) {
    echo 'connect in...' . (int)$socket . PHP_EOL;
});

$socketServer->on('receive', function ($socketServer, $socket, $data) {
    echo 'receive data : ' . $data . ' from ' . (int)$socket . PHP_EOL;

    $re = socketHelper::httpSend($socket, 'I am server...' . PHP_EOL);
    if ($re) {
        echo 'response to ' . (int)$socket . PHP_EOL;
    } else {
        $socketServer->deleteConn($socket);
    }

    // 模拟Http服务器
    $socketServer->deleteConn($socket);
});

$socketServer->on('close', function ($socketServer, $socket) {
    echo 'client close...' . (int)$socket . PHP_EOL;
});

// 启动服务器
$socketServer->start();


