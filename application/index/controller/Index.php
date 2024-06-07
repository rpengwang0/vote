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

namespace app\index\controller;

use app\admin\service\NodeService;
use library\Controller;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class Index extends Controller
{
    /**
     * 入口跳转链接
     */
    public function index()
    {
        $this->redirect('@admin/login');
    }

    public function test()
    {
        $classMap = NodeService::getAuthTree();
//        dump($classMap);
        // $classMap = NodeService::getMenuTree();
        dump($classMap);
        exit;
    }

}
