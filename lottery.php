<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/30 0030 13:35
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */

class lottery
{
    public $param = [];
    public $postCodeUrl = 'http://ceshi.lsyl8899.com/lottery/edit/';
    public $postOpenUrl = 'http://ceshi.lsyl8899.com/main/message/agreeopen';

    public function __construct($param = [])
    {
        $this->param = $param;
    }

    public function getLotteryCode()
    {

    }

    public function postCode()
    {

    }

    public function postOpen($id)
    {

    }

    public function post($url, $data = [], $sessionId = '', $headerArr = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => $headerArr,
            CURLOPT_COOKIE => "ASP.NET_SessionId={$sessionId}",
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res == NULL) {
            curl_close($ch);
            return false;
        } else if($code  != "200") {
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $res;
    }
}