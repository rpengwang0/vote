<?php


namespace app\traits\controller;

use GatewayClient\Gateway;

trait Walker
{

    public function setRregisterAddress($ip='127.0.0.1:1238')
    {
        return  Gateway::$registerAddress = $ip;
    }
    //发送给所有用户
    public function sendToAll($content,$api='api',$param='param',$num=0)
    {
        $data = [];
        $data['type'] = $api;
        $data['data'] = $content;
        $data['param'] = $param;
        $data['num'] = $num;
        $this->setRregisterAddress();
        return Gateway::sendToAll(json_encode($data));
    }

}