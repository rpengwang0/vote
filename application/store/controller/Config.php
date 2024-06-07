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

namespace app\store\controller;

use app\store\service\ExtendService;
use library\Controller;

/**
 * 商城参数配置
 * Class Config
 * @package app\store\controller
 */
class Config extends Controller
{

    /**
     * 商城参数配置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = '商城参数配置';
        $this->applyCsrfToken('save');
        $this->query = ExtendService::querySmsBalance();
        $this->fetch();
    }

    /**
     * 保存商城参数
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function save()
    {
        if ($this->request->isPost()) {
            $this->applyCsrfToken('save');
            foreach ($this->request->post() as $k => $v) sysconf($k, $v);
            $this->success('商城短信配置保存成功！');
        }
    }

}