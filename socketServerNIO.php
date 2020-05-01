<?php

set_time_limit(0);

class socketServerNIO
{
    protected $errorCode;
    protected $errorMsg;
    protected $len = 8129;// 每次读取数据的字节数
    protected $events = [];
    protected $serverSocket;
    protected $settings = [];
    protected $clients = [];// 连接的客户端fd

    /**
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return mixed
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    public function __construct($host, $port)
    {
        $this->initSettings();
        $ret = $this->createServer($host, $port);
        if (!$ret) {
            exit($this->getErrorMsg() . PHP_EOL);
        }
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
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == FALSE) {
            $this->setError(1000, socket_strerror(socket_last_error()));
            return false;
        } else {
            $this->serverSocket = $sock;
        }

        // 2. 绑定
        if (socket_bind($this->serverSocket, $host, $port) == FALSE) {
            $this->setError(1001, socket_strerror(socket_last_error()));
            return false;
        }

        // 将服务的socket设置为非阻塞
        socket_set_nonblock($sock);

        // 3. 监听
        if (socket_listen($this->serverSocket, $this->settings['backlog']) == FALSE) {
            $this->setError(1002, socket_strerror(socket_last_error()));
            return false;
        }

        return true;
    }

    protected function setError($code, $msg)
    {
        $this->errorCode = $code;
        $this->errorMsg  = $msg;
    }

    public function start()
    {
        do {
            // 非阻塞
            if (($conn = socket_accept($this->serverSocket)) !== FALSE) {
                // 将客户端连接设置为非阻塞
                socket_set_nonblock($conn);
                $no                 = (int)$conn;
                $this->clients[$no] = $conn;

                if (isset($this->events['connect']) && $conn) {
                    call_user_func($this->events['connect'], $this, $no);
                }
            }

            // 非阻塞读取客户端信息
            foreach($this->clients as $k => $c){
                if (is_resource($c)) {
                    // 读取客户端全部信息
                    $buf = socket_read($c, $this->len);
                    if ($buf === false) {
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

    public function send($socketId, $data)
    {
        if (isset($this->clients[$socketId]) && is_resource($this->clients[$socketId])) {
            if (@socket_write($this->clients[$socketId], $data, strlen($data))) {
                return true;
            }
        }

        $this->deleteConn($socketId);
        return false;
    }

    public function formatHttp($data)
    {
        $ret = "HTTP/1.1 200 ok\r\n";
        $ret .= "Content-Type: text/html; charset=utf-8\r\n";
        $ret .= "Content-Length: " . strlen($data) . "\r\n\r\n";

        $ret .= $data . "\r\n";

        return $ret;
    }

    protected function deleteConn($socketId)
    {
        @socket_shutdown($this->clients[$socketId]);
        socket_close($this->clients[$socketId]);
        unset($this->clients[$socketId]);
        if (isset($this->events['close'])) {
            call_user_func($this->events['close'], $this, $socketId);
        }
    }

    public function __destruct()
    {
        socket_close($this->serverSocket);
    }
}

$socketServer = new socketServerNIO('127.0.0.1', 8888);

$socketServer->on('connect', function ($socketServer, $socketId) {
    echo 'connect in...' . $socketId . PHP_EOL;
});

$socketServer->on('receive', function ($socketServer, $socketId, $data) {
    echo 'receive data : ' . $data . ' from ' . $socketId . PHP_EOL;

    $msg = $socketServer->formatHttp('I am server...');
    $re  = $socketServer->send($socketId, $msg);
    if ($re) {
        echo 'response to ' . $socketId . PHP_EOL;
    }
});

$socketServer->on('close', function ($socketServer, $socketId) {
    echo 'client close...' . $socketId . PHP_EOL;
});
// 启动服务器
$socketServer->start();