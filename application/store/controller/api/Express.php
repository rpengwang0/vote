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

namespace app\store\controller\api;

use library\Controller;

/**
 * 快递查询结果
 * Class Express
 * @package app\store\controller\api
 */
class Express extends Controller
{
    /**
     * 物流查询结果
     */
    public function query()
    {
        $express_no = $this->request->post('express_no', '');
        $express_code = $this->request->post('express_code', '');
        $result = \library\tools\Express::query($express_code, $express_no);
        $this->success('获取物流查询结果！', $result);
    }

}