<?php

class socketClientBase
{
    public $len = 8129;// 每次读取数据的字节数
    public $socket = null;
    public $rcvtimeo = 3;
    public $sndtimeo = 3;

    public function __construct($port = null)
    {
        $this->create($port);
    }

    public function create($port = null)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        $this->socket = $socket;

        // 客户端绑定端口
        if($port){
            $this->setClientAddr('127.0.0.1', $port);
        }

        // 设置端口复用
        $this->setReuseAddr();
    }

    public function setTimeout($rcvtimeo, $sndtimeo)
    {
        if (!is_null($rcvtimeo) && is_int($rcvtimeo)) {
            $this->rcvtimeo = $rcvtimeo;
        }
        if (!is_null($sndtimeo) && is_int($sndtimeo)) {
            $this->sndtimeo = $sndtimeo;
        }
        if(!is_resource($this->socket)){
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }
        $r1 = socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->rcvtimeo, 'usec' => 0]);  // 发送超时
        if (!$r1) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }
        $r2 = socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->sndtimeo, 'usec' => 0]);  // 接收超时
        if (!$r2) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        return $this;
    }

    public function read()
    {
        $getMsg = '';

        do {
            $out = @socket_read($this->socket, $this->len);
            if ($out === false) {
                return false;
            }
            $getMsg .= $out;
            if (strlen($out) < $this->len) {
                break;
            }
        } while (true);

        return $getMsg;
    }

    public function send($msg)
    {
        // 发送指令
        $ret = @socket_write($this->socket, $msg, strlen($msg));
        if ($ret === false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        return $ret;
    }

    public function setClientAddr($addr, $port)
    {
        // 客户端的端口号默认是随机的，也可以指定某个端口号，如果端口已被占用了会bind失败，继而会使用随机端口
        $res = socket_bind($this->socket, $addr, $port);
        if(!$res){
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }
        return $this;
    }

    public function setReuseAddr()
    {
        // 端口复用，只有当原先的连接处于TIME_WAIT时才有用，同一个端口上只能创建一个活动TCP连接
        socket_get_option($this->socket, SOL_SOCKET, SO_REUSEADDR);
        return $this;
    }

    public function setNonBlocking()
    {
        socket_set_nonblock($this->socket);
        return $this;
    }

    public function __destruct()
    {
        @socket_close($this->socket);
    }
}


