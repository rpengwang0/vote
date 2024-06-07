<?php

// +----------------------------------------------------------------------
// | framework
// +----------------------------------------------------------------------
// | 山西东方梅雅
// +----------------------------------------------------------------------
// dfhf.vip
// +----------------------------------------------------------------------
 
// +----------------------------------------------------------------------
 
// +----------------------------------------------------------------------

namespace app\wechat\command\fans;

use app\wechat\command\Fans;

/**
 * 粉丝标签指令
 * Class FansTags
 * @package app\wechat\command\fans
 */
class FansTags extends Fans
{
    /**
     * 配置入口
     */
    protected function configure()
    {
        $this->module = ['tags'];
        $this->setName('xfans:tags')->setDescription('synchronize tags of fans');
    }
}