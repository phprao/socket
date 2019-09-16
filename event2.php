<?php
/**
 * ----------------------------------------------------------
 * date: 2019/9/12 17:47
 * ----------------------------------------------------------
 * author: Raoxiaoya
 * ----------------------------------------------------------
 * describe:
 * ----------------------------------------------------------
 */
set_time_limit(0);

class EpollSocketServer
{
    private static $socket;
    private static $connections;
    private static $buffers;

    function __construct($port)
    {
        global $errno, $errstr;

        if (!extension_loaded('event')) {
            die("Please install event extension firstly/n");
        }

        if ($port < 1024) {
            die("Port must be a number which bigger than 1024/n");
        }

        $socket_server = stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr);
        if (!$socket_server) die("$errstr ($errno)");

        stream_set_blocking($socket_server, 0); // 非阻塞
        // 创建Base
        $base  = new EventBase();

        // 创建Event
        $event = new Event($base, $socket_server, Event::READ | Event::PERSIST, [$this, 'ev_accept']);

        $event->add();
        // 进入事件循环
        $base->loop();

        $base->dispatch();
    }

    function ev_accept($socket, $flag, $base)
    {
        $connection = stream_socket_accept($socket);
        stream_set_blocking($connection, 0);

        $buffer = new EventBuffer();


        // $buffer = event_buffer_new($connection, array(__CLASS__, 'ev_read'), array(__CLASS__, 'ev_write'), array(__CLASS__, 'ev_error'), $id);
        // event_buffer_base_set($buffer, $base);
        // event_buffer_timeout_set($buffer, 30, 30);
        // event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        // event_buffer_priority_set($buffer, 10);
        // event_buffer_enable($buffer, EV_READ | EV_PERSIST);
        //
        // // we need to save both buffer and connection outside
        // self::$connections[$id] = $connection;
        // self::$buffers[$id]     = $buffer;
    }

    function ev_error($buffer, $error, $id)
    {
        // event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
        // event_buffer_free(self::$buffers[$id]);
        // fclose(self::$connections[$id]);
        // unset(self::$buffers[$id], self::$connections[$id]);
    }

    function ev_read($buffer, $id)
    {
        var_dump($buffer);
        var_dump($id);


        // static $ct = 0;
        // $ct_last = $ct;
        // $ct_data = '';
        // while ($read = event_buffer_read($buffer, 1024)) {
        //     $ct      += strlen($read);
        //     $ct_data .= $read;
        // }
        // $ct_size = ($ct - $ct_last) * 8;
        // echo "[$id] " . __METHOD__ . " > " . $ct_data . "/n";
        // event_buffer_write($buffer, "Received $ct_size byte data./r/n");
    }

    function ev_write($buffer, $id)
    {
        echo "[$id] " . __METHOD__ . "/n";
    }
}

new EpollSocketServer(8888);