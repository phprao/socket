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

class socketServerBIO
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
            // 每次select阻塞等待多少秒，获取在这段时间内的状态变化；0表示不等待，获取这个时刻的状态变化
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

        // 设置端口重用，否则每次重启要等待1-2分钟
        socket_get_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR);

        // 3. 监听
        if (socket_listen($this->serverSocket, $this->settings['backlog']) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        return true;
    }

    public function start()
    {
        do {
            // 4. 阻塞，等待客户端请求
            if (($conn = socket_accept($this->serverSocket)) === false) {
                echo socket_strerror(socket_last_error()) . PHP_EOL;
                continue;
            } else {
                $no                 = (int)$conn;
                $this->clients[$no] = $conn;

                if (isset($this->events['connect']) && $conn) {
                    call_user_func($this->events['connect'], $this, $no);
                }

                // 5. 阻塞，读取客户端全部信息，如果此连接不发来数据，那么服务将一直阻塞在这里啥也干不了。
                if (is_resource($conn)) {
                    $buf = $this->read($conn, $this->len);
                    if ($buf === false) {
                        $this->deleteConn((int)$conn);
                    } else {
                        if (isset($this->events['receive'])) {
                            call_user_func($this->events['receive'], $this, (int)$conn, $buf);
                        }
                    }
                } else {
                    $this->deleteConn((int)$conn);
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

    public function getClientInfo($socketId)
    {
        // 获取客户端信息
        socket_getpeername($this->clients[$socketId], $addr, $port);
        return ['ip'=>$addr, 'port'=>$port];
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

/*************************************************************************************/

$socketServer = new socketServerBIO('0.0.0.0', 8888);

$socketServer->on('connect', function ($socketServer, $socketId) {
    echo 'connect in...' . $socketId . PHP_EOL;
});

$socketServer->on('receive', function ($socketServer, $socketId, $data) {
    echo 'receive data : ' . $data . ' from ' . $socketId . PHP_EOL;

    $msg = $socketServer->formatHttp('I am server...' . PHP_EOL);

    $re = $socketServer->send($socketId, $msg);
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


