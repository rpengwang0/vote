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

namespace app\admin\service;

use library\tools\Node;
use think\Db;

/**
 * 系统日志服务管理
 * Class LogService
 * @package app\admin\service
 */
class OplogService
{
    /**
     * 写入操作日志
     * @param string $action
     * @param string $content
     * @return bool
     */
    public static function write($action = '行为', $content = "内容描述")
    {
        return Db::name('SystemLog')->insert([
            'node'     => Node::current(), 'action' => $action, 'content' => $content,
            'geoip'    => PHP_SAPI === 'cli' ? '127.0.0.1' : request()->ip(),
            'username' => PHP_SAPI === 'cli' ? 'cli' : (string)session('admin_user.username'),
        ]);
    }

    /**
     * 清理系统日志
     * @return boolean
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function clear()
    {
        return Db::name('SystemLog')->where('1=1')->delete() !== false;
    }
}