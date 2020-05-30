<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/30 0030 14:38
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */

class socketServerEventHttp
{
    public function __construct()
    {

    }

    public function start($port)
    {
        $base = new EventBase();
        $http = new EventHttp($base);
        $http->setAllowedMethods(EventHttpRequest::CMD_GET | EventHttpRequest::CMD_POST);
        $controller = new IndexController();

        $http->setCallback("/dump", [$controller, "_http_dump"]);
        $http->setCallback("/about", [$controller, "_http_about"]);
        $http->setDefaultCallback([$controller, "_http_default"]);

        $http->bind("0.0.0.0", $port);
        $base->loop();
    }
}

class IndexController
{
    public function _http_dump($req, $data)
    {
        echo "Command:", $req->getCommand(), PHP_EOL;
        echo "URI: ", $req->getUri(), PHP_EOL;
        $req->sendReply(200, "OK");
        $buf = $req->getInputBuffer();

        echo '收到数据：'.PHP_EOL;
        $str = $this->getEventBufferData($buf);
        echo $str;
        echo PHP_EOL;
    }

    public function _http_about($req)
    {
        echo "URI: ", $req->getUri(), PHP_EOL;
        $req->sendReply(200, "OK");
    }

    public function _http_default($req, $data)
    {
        echo "URI: ", $req->getUri(), PHP_EOL;
        $req->sendReply(200, "OK");
    }

    protected function getEventBufferData($eventBuffer)
    {
        $str = '';

        while ($s = $eventBuffer->read(8192)) {
            $str .= $s;
        }

        // readLine 函数要求数据包最后必须有个换行符，否则会丢失最后一行数据
        //while ($s = $eventBuffer->readLine(EventBuffer::EOL_ANY)) {
        //    $str .= $s;
        //}

        return $str;
    }
}

$port = 8888;
if ($argc > 1) {
    $port = (int)$argv[1];
}
if ($port <= 0 || $port > 65535) {
    exit("Invalid port");
}
$l = new socketServerEventHttp();

$l->start($port);
