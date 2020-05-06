<?php
/**
 * ----------------------------------------------------------
 * date: 2019/9/6 11:45
 * ----------------------------------------------------------
 * author: Raoxiaoya
 * ----------------------------------------------------------
 * describe: socket_select 非阻塞服务器
 * ----------------------------------------------------------
 */

set_time_limit(0);

class socketServerSelect
{
    protected $len = 8129;// 每次读取数据的字节数
    protected $events = [];
    protected $serverSocket;
    protected $settings = [];
    protected $clients = [];// 连接的客户端fd

    public function __construct($host, $port)
    {
        $this->initSettings();
        $this->createServer($host, $port);
    }

    protected function initSettings()
    {
        $this->settings = [
            // 允许等待连接的请求数
            'backlog' => 128,

            /**
             * 每次select阻塞等待多少秒，获取在这段时间内的状态变化；
             * 0表示不等待，获取这个时刻的状态变化；
             * NULL表示阻塞等待直到有返回。
             */
            'timeout' => 3,
        ];
    }

    public function set($settings)
    {
        $this->settings = $settings;
    }

    public function on($event, $callback)
    {
        $this->events[$event] = $callback;
    }

    protected function createServer($host, $port)
    {
        // 1. 创建
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        } else {
            $this->serverSocket = $sock;
        }

        // 2. 绑定
        if (socket_bind($this->serverSocket, $host, $port) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        // 将服务的socket设置为非阻塞
        socket_set_nonblock($this->serverSocket);

        // 3. 监听
        if (socket_listen($this->serverSocket, $this->settings['backlog']) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        return true;
    }

    public function start()
    {
        // 4、轮训执行系统调用socket_select
        do {
            // 可读监听数组，将主socket加入，用以处理客户端连接
            $readFds      = array_merge($this->clients, array($this->serverSocket));
            $writeFds     = null;// 可写监听数组
            $exceptionFds = null;// 异常监听数组

            $ret = socket_select($readFds, $writeFds, $exceptionFds, $this->settings['timeout']);
            if ($ret < 1 || empty($readFds)) {
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
                 * 2、如果多个连接同时达到，那么只会一个一个的接收，比如10个客户端同时发起连接请求，
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
                        call_user_func($this->events['connect'], $this, $no);
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
                        $buf = $this->read($fd, $this->len);
                        if ($buf === false) {
                            $this->deleteConn((int)$fd);
                        } elseif ($buf === '') {
                            /**
                             * 如果客户端正常断开(客户端调用socket_close)，socket_read会返回空字符串，表示有状态更新可读，也就是说客户端正常断开，服务器会有感知。
                             * 也不排除在线的客户端发送的空字符串。
                             *
                             * 基于此，我们规定客户端不能发送空字符串。
                             */

                            $this->deleteConn((int)$fd);
                        } else {
                            if (isset($this->events['receive'])) {
                                call_user_func($this->events['receive'], $this, (int)$fd, $buf);
                            }
                        }
                    } else {
                        $this->deleteConn((int)$fd);
                    }
                }
            }

        } while (true);
    }

    public function read($socket, $len)
    {
        $getMsg = '';

        do {
            $out = socket_read($socket, $len);
            if ($out === false) {
                return false;
            }
            $getMsg .= $out;
            if (strlen($out) < $len) {
                break;
            }
        } while (true);

        return $getMsg;
    }

    public function send($socketId, $data)
    {
        if (isset($this->clients[$socketId]) && is_resource($this->clients[$socketId])) {
            if (@socket_write($this->clients[$socketId], $data, strlen($data)) !== false) {
                return true;
            }
        }

        return false;
    }

    public function formatHttp($data)
    {
        /**
         * HTTP/1.1 200 OK
         * Date: Fri, 01 May 2020 12:00:57 GMT
         * Connection: close
         * X-Powered-By: PHP/7.2.27
         * Content-type: text/html; charset=UTF-8
         */
        $ret = "HTTP/1.1 200 OK\r\n";
        $ret .= "Date: " . gmdate("D, d M Y H:i:s", time()) . " GMT\r\n";
        $ret .= "Connection: close\r\n";
        $ret .= "Content-type: text/html; charset=UTF-8\r\n";
        $ret .= "Content-Length: " . strlen($data) . "\r\n\r\n";

        $ret .= $data;

        return $ret;
    }

    public function deleteConn($socketId)
    {
        @socket_shutdown($this->clients[$socketId]);
        @socket_close($this->clients[$socketId]);
        unset($this->clients[$socketId]);
        if (isset($this->events['close'])) {
            call_user_func($this->events['close'], $this, $socketId);
        }
    }

    public function __destruct()
    {
        @socket_close($this->serverSocket);
    }
}

$socketServer = new socketServerSelect('0.0.0.0', 8888);

$socketServer->on('connect', function ($socketServer, $socketId) {
    echo '[read] ';
    echo 'connect in...' . $socketId . PHP_EOL;
});

$socketServer->on('receive', function ($socketServer, $socketId, $data) {
    echo '[read] ';
    echo 'receive data : ' . $data . ' from ' . $socketId . PHP_EOL;

    $msg = $socketServer->formatHttp('I am server...');
    $re  = $socketServer->send($socketId, $msg);
    if ($re) {
        echo 'response to ' . $socketId . PHP_EOL;
    } else {
        $socketServer->deleteConn($socketId);
    }

    // 模拟Http服务器
    //$socketServer->deleteConn($socketId);
});

$socketServer->on('close', function ($socketServer, $socketId) {
    echo 'client close...' . $socketId . PHP_EOL;
});
// 启动服务器
$socketServer->start();