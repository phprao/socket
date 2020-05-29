<?php
/**
 * 服务端
 *
 * 非阻塞式 + 同步
 */

set_time_limit(0);

require_once __DIR__ . '/../lib/socketServerBase.php';
require_once __DIR__ . '/../lib/socketHelper.php';

class socketServerNIO extends socketServerBase
{
    public function __construct($host, $port)
    {
        parent::__construct($host, $port);

        // 将服务的socket设置为非阻塞
        socket_set_nonblock($this->serverSocket);
    }

    public function start()
    {
        do {
            // 非阻塞
            if (($conn = socket_accept($this->serverSocket)) !== false) {
                // 将客户端连接设置为非阻塞
                socket_set_nonblock($conn);
                $no                 = (int)$conn;
                $this->clients[$no] = $conn;

                if (isset($this->events['connect']) && $conn) {
                    call_user_func($this->events['connect'], $this, $conn);
                }
            }

            // 非阻塞读取所有客户端信息，如果对方没有发送数据包过来，那么该fd将是不可读的，因此需要对read的内容做判断。
            foreach ($this->clients as $no => $c) {
                if (is_resource($c)) {
                    $buf = socketHelper::read($c, $this->len);
                    if ($buf === false) {
                        /**
                         * 如果客户端没有发送数据，socket_read会返回FALSE，错误码为11，表示不可读。
                         *
                         * 如果客户端异常断开了(客户端没有调用socket_close)，socket_read会返回FALSE，错误码为11，表示不可读。
                         */
                        if (socket_last_error() != 11) {
                            $this->deleteConn($c);
                        }
                    } elseif ($buf === '') {
                        /**
                         * 判定为客户端断开
                         */

                        $this->deleteConn($c);
                    } else {
                        if (isset($this->events['receive'])) {
                            call_user_func($this->events['receive'], $this, $c, $buf);
                        }
                    }
                } else {
                    $this->deleteConn($c);
                }
            }

            // 如果不调用sleep，那么CPU将会迅速飙升
            usleep(1000);

        } while (true);
    }

    public function __destruct()
    {
        parent::__destruct();
    }
}

/*************************************************************************************/

$socketServer = new socketServerNIO('0.0.0.0', 8888);

$socketServer->on('connect', function ($socketServer, $socket) {
    $info = socketHelper::getClientInfo($socket);
    $id   = (int)$socket;
    echo "connect in...{$info['ip']}:{$info['port']}, id:{$id}" . PHP_EOL;
});

$socketServer->on('receive', function ($socketServer, $socket, $data) {
    echo 'receive data : ' . $data . ' from ' . (int)$socket . PHP_EOL;

    $re = socketHelper::Httpsend($socket, 'I am server...' . PHP_EOL);
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

