<?php
error_reporting(E_ALL);
set_time_limit(0);

class socketClient
{
    protected $len = 8129;// 每次读取数据的字节数
    protected $client = null;
    protected $rcvtimeo = 3;
    protected $sndtimeo = 3;

    public function __construct($host, $port, $rcvtimeo = null, $sndtimeo = null)
    {
        $this->connectTo($host, $port);
        $this->setTimeout($rcvtimeo, $sndtimeo);
    }

    protected function setTimeout($rcvtimeo, $sndtimeo)
    {
        if (!is_null($rcvtimeo) && is_int($rcvtimeo)) {
            $this->rcvtimeo = $rcvtimeo;
        }
        if (!is_null($sndtimeo) && is_int($sndtimeo)) {
            $this->sndtimeo = $sndtimeo;
        }
        $r1 = socket_set_option($this->client, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->rcvtimeo, 'usec' => 0]);  // 发送超时
        if (!$r1) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }
        $r2 = socket_set_option($this->client, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->sndtimeo, 'usec' => 0]);  // 接收超时
        if (!$r2) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }
    }

    protected function connectTo($host, $port)
    {
        // 1. 创建
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        // 2. 链接
        $result = socket_connect($socket, $host, $port);
        if ($result == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        } else {
            $this->client = $socket;
            return true;
        }
    }

    public function read($socket, $len)
    {
        $getMsg = '';

        do {
            $out = socket_read($socket, $len);
            if ($out === false) {
                throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
            }
            $getMsg .= $out;
            if (strlen($out) < $len) {
                break;
            }
        } while (true);

        return $getMsg;
    }

    public function send($msg, $callback)
    {
        // 发送指令
        if (@socket_write($this->client, $msg, strlen($msg)) === false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        // 接收结果
        $getMsg = $this->read($this->client, $this->len);

        return call_user_func($callback, $this, $getMsg);
    }

    public function __destruct()
    {
        @socket_close($this->client);
    }
}

/*************************************************************************************/

try {
    $socketClient = new socketClient('127.0.0.1', 8888);

    $re = $socketClient->send('name', function ($socketClient, $data) {
        echo $data . PHP_EOL;
    });
} catch (\Exception $e) {
    die($e->getMessage());
}


