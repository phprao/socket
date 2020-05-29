<?php

class ZookeeperTest
{
    protected $errorCode;
    protected $errorMsg;
    protected $len = 8129;// 每次读取数据的字节数
    protected $client = null;
    protected $rcvtimeo = 3;
    protected $sndtimeo = 3;

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

    public function __construct($host, $port, $rcvtimeo = null, $sndtimeo = null)
    {
        $this->connectTo($host, $port);
        $this->setTimeout($rcvtimeo, $sndtimeo);
    }

    protected function setTimeout($rcvtimeo, $sndtimeo){
        if(!is_null($rcvtimeo) && is_int($rcvtimeo)){
            $this->rcvtimeo = $rcvtimeo;
        }
        if(!is_null($sndtimeo) && is_int($sndtimeo)){
            $this->sndtimeo = $sndtimeo;
        }
        socket_set_option($this->client, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>$this->rcvtimeo, 'usec'=>0]);  // 发送超时
        socket_set_option($this->client, SOL_SOCKET, SO_SNDTIMEO, ['sec'=>$this->sndtimeo, 'usec'=>0]);  // 接收超时
    }

    protected function connectTo($host, $port)
    {
        // 1. 创建
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket == FALSE) {
            $this->setError(1000, socket_strerror(socket_last_error()));
            return false;
        }

        // 2. 链接
        $result = socket_connect($socket, $host, $port);
        if ($result == FALSE) {
            $this->setError(10001, 'connect failed');
            return false;
        } else {
            $this->client = $socket;
            return true;
        }
    }

    protected function setError($code, $msg)
    {
        $this->errorCode = $code;
        $this->errorMsg  = $msg;
    }

    protected function sendCmdAndReturn($cmd)
    {
        // 发送指令
        if (!socket_write($this->client, $cmd, strlen($cmd))) {
            $this->setError(10002, socket_strerror(socket_last_error()));
            return false;
        }

        // 接收结果
        $getMsg = '';

        // 3. 从服务端读取全部的数据
        do {
            $out    = @socket_read($this->client, $this->len);
            $getMsg .= $out;
            if (strlen($out) < $this->len) {
                break;
            }
        } while (true);

        return $this->initResult($getMsg);
    }

    protected function initResult($msg)
    {
        return $msg;
    }

    public function set($key, $value)
    {
        $cmd = "set {$key} {$value}";
        return $this->sendCmdAndReturn($cmd);
    }

    public function get($key, $value)
    {
        $cmd = "get {$key}";
        return $this->sendCmdAndReturn($cmd);
    }

    public function __destruct()
    {
        socket_close($this->client);
    }
}

$zk = new ZookeeperTest('127.0.0.1', 8888);
var_dump($zk);
$re = $zk->set('name', 'rao');
var_dump($re);
var_dump($zk->getErrorMsg());