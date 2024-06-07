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

namespace app\service\handler;

use app\service\service\WechatService;

/**
 * 第三方平台测试上线
 *
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/10/27 14:14
 */
class PublishHandler
{

    /**
     * 当前微信APPID
     * @var string
     */
    protected static $appid;

    /**
     * 事件初始化
     * @param string $appid
     * @return string
     * @throws \WeChat\Exceptions\InvalidDecryptException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function handler($appid)
    {
        try {
            $wechat = WechatService::WeChatReceive($appid);
        } catch (\Exception $e) {
            return "Wechat message handling failed, {$e->getMessage()}";
        }
        /* 分别执行对应类型的操作 */
        switch (strtolower($wechat->getMsgType())) {
            case 'text':
                $receive = $wechat->getReceive();
                if ($receive['Content'] === 'TESTCOMPONENT_MSG_TYPE_TEXT') {
                    return $wechat->text('TESTCOMPONENT_MSG_TYPE_TEXT_callback')->reply([], true);
                }
                $key = str_replace("QUERY_AUTH_CODE:", '', $receive['Content']);
                WechatService::instance('Service')->getQueryAuthorizerInfo($key);
                return $wechat->text("{$key}_from_api")->reply([], true);
            case 'event':
                $receive = $wechat->getReceive();
                return $wechat->text("{$receive['Event']}from_callback")->reply([], true);
            default:
                return 'success';
        }
    }

}
