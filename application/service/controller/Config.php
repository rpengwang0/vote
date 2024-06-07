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

namespace app\service\controller;

use library\Controller;

/**
 * 开放平台参数配置
 * Class Config
 * @package app\service\controller
 */
class Config extends Controller
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'WechatServiceConfig';

    /**
     * 开放平台配置
     * @auth true
     * @menu true
     */
    public function index()
    {
        $this->applyCsrfToken('save');
        $this->title = '开放平台配置';
        $this->fetch();
    }

    /**
     * 保存参数数据
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function save()
    {
        $this->applyCsrfToken('save');
        if ($this->request->post()) {
            $post = $this->request->post();
            foreach ($post as $k => $v) sysconf($k, $v);
            $this->success('开放平台参数修改成功！');
        } else {
            $this->error('开放平台参数修改失败！');
        }
    }

}
