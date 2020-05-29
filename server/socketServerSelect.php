<?php
/**
 * 服务端
 *
 * 非阻塞式 + 同步 + IO多路复用器select
 */

set_time_limit(0);

require_once __DIR__ . '/../lib/socketServerBase.php';
require_once __DIR__ . '/../lib/socketHelper.php';

class socketServerSelect extends socketServerBase
{
    public function __construct($host, $port)
    {
        parent::__construct($host, $port);
    }

    public function start()
    {
        // 轮训执行系统调用socket_select
        while(true)
        {
            // 可读监听数组，将主socket加入，用以处理客户端连接
            $readFds      = array_merge($this->clients, array($this->serverSocket));
            $writeFds     = null;// 可写监听数组
            $exceptionFds = null;// 异常监听数组

            /**
             * 对于 $readFds, $writeFds, $exceptionFds 如果有返回，那么应用程序应该去处理，如果没有处理，或者在读数据的时候没有
             * 读完，那么后面会重复返回直到被处理为止。
             */
            $ret = socket_select($readFds, $writeFds, $exceptionFds, $this->settings['timeout']);
            if ($ret < 1) {
                continue;
            }

            // 处理异常的状态
            if (!empty($exceptionFds)) {
                foreach ($exceptionFds as $efd) {
                    $this->deleteConn((int)$efd);
                }
            }

            // 处理可读的状态：客户端连接
            if (in_array($this->serverSocket, $readFds)) {
                /**
                 * 1、处理新客户端的连接，此处并不会阻塞等待连接，因为已经有待处理的连接了。
                 * 2、如果多个连接同时达到，内核采用队列来缓存它们，accept方法会一个一个的出队列，比如10个客户端同时发起连接请求，
                 * 第一次select，$readFds中有一个serverSocket对象，调用socket_accept接收第一个连接请求,其他9个请求处于等待状态。
                 * 第二次select，$readFds中有一个serverSocket对象，调用socket_accept接收第二个连接请求,其他8个请求处于等待状态。
                 * ......
                 * 直到所有的请求都accept完毕。
                 */
                $conn = socket_accept($this->serverSocket);
                if ($conn === false) {
                    echo socket_strerror(socket_last_error()) . PHP_EOL;
                } else {
                    socket_set_nonblock($conn);
                    $no                 = (int)$conn;
                    $this->clients[$no] = $conn;

                    if (isset($this->events['connect']) && $conn) {
                        call_user_func($this->events['connect'], $this, $conn);
                    }
                }
                // 处理完就该移除了
                $keyOfThis = array_search($this->serverSocket, $readFds);
                unset($readFds[$keyOfThis]);
            }

            // 遍历剩余的可读fd：读取信息
            if (!empty($readFds)) {
                foreach ($readFds as $fd) {
                    if (is_resource($fd)) {
                        $buf = socketHelper::read($fd, $this->len);
                        if ($buf === false) {
                            $this->deleteConn($fd);
                        } elseif ($buf === '') {
                            /**
                             * 如果客户端正常断开(客户端调用socket_close)，socket_read会返回空字符串，表示有状态更新可读。
                             * 也不排除在线的客户端发送的空字符串。
                             *
                             * 基于此，我们规定客户端不能发送空字符串。
                             */

                            $this->deleteConn($fd);
                        } else {
                            if (isset($this->events['receive'])) {
                                call_user_func($this->events['receive'], $this, $fd, $buf);
                            }
                        }
                    } else {
                        $this->deleteConn($fd);
                    }
                }
            }
        }
    }

    public function __destruct()
    {
        parent::__destruct();
    }
}

$socketServer = new socketServerSelect('0.0.0.0', 8888);

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