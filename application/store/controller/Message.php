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

use library\Controller;

/**
 * 短信发送管理
 * Class Message
 * @package app\store\controller
 */
class Message extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'StoreMemberSmsHistory';

    /**
     * 短信发送管理
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = '短信发送管理';
        $query = $this->_query($this->table)->like('phone,content,result');
        $query->dateBetween('create_at')->order('id desc')->page();
    }

    /**
     * 删除短信记录
     * @auth true
     */
    public function remove()
    {
        $this->_delete($this->table);
    }

}