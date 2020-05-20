<?php
/**
 * 服务端
 *
 * 非阻塞式 + 同步
 */

set_time_limit(0);

class socketServerNIO
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

        // 将服务的socket设置为非阻塞
        socket_set_nonblock($sock);

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
            // 非阻塞
            if (($conn = socket_accept($this->serverSocket)) !== false) {
                // 将客户端连接设置为非阻塞
                socket_set_nonblock($conn);
                $no                 = (int)$conn;
                $this->clients[$no] = $conn;

                if (isset($this->events['connect']) && $conn) {
                    call_user_func($this->events['connect'], $this, $no);
                }
            }

            // 非阻塞读取所有客户端信息，如果对方没有发送数据包过来，那么该fd将是不可读的，因此需要对read的内容做判断。
            foreach ($this->clients as $no => $c) {
                if (is_resource($c)) {
                    $buf = $this->read($c, $this->len);
                    if ($buf === false) {
                        /**
                         * 如果客户端只没有发送数据，socket_read会返回FALSE，错误码为11，表示不可读。
                         *
                         * 如果客户端异常断开了(客户端没有调用socket_close)，socket_read会返回FALSE，错误码为11，表示不可读。
                         */
                        if (socket_last_error() != 11) {
                            $this->deleteConn((int)$c);
                        }
                    } elseif ($buf === '') {
                        /**
                         * 判定为客户端断开
                         *
                         * 如果客户端正常断开(客户端调用socket_close)，socket_read会返回空字符串，表示有状态更新可读，也就是说客户端正常断开，服务器会有感知。
                         * 也不排除在线的客户端发送的空字符串。
                         *
                         * 基于此，我们规定客户端不能发送空字符串。
                         */

                        $this->deleteConn((int)$c);
                    } else {
                        if (isset($this->events['receive'])) {
                            call_user_func($this->events['receive'], $this, (int)$c, $buf);
                        }
                    }
                } else {
                    $this->deleteConn((int)$c);
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
            $ret = @socket_write($this->clients[$socketId], $data, strlen($data));
            if ($ret !== false) {
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

$socketServer = new socketServerNIO('0.0.0.0', 8888);

$socketServer->on('connect', function ($socketServer, $socketId) {
    $info = $socketServer->getClientInfo($socketId);

    print_r("
connect in...
    ip: {$info['ip']}
    port: {$info['port']}
    socketId: $socketId
    ");
});

$socketServer->on('receive', function ($socketServer, $socketId, $data) {
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

