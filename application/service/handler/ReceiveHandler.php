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
use think\Db;

/**
 * 微信推送消息处理
 *
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/10/27 14:14
 */
class ReceiveHandler
{

    /**
     * 事件初始化
     * @param string $appid
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public static function handler($appid)
    {
        try {
            $service = WechatService::WeChatReceive($appid);
        } catch (\Exception $e) {
            return "Wechat message handling failed, {$e->getMessage()}";
        }
        // 验证微信配置信息
        $config = Db::name('WechatServiceConfig')->where(['authorizer_appid' => $appid])->find();
        if (empty($config) || empty($config['appuri'])) {
            \think\facade\Log::error(($message = "微信{$appid}授权配置验证无效"));
            return $message;
        }
        try {
            list($data, $openid) = [$service->getReceive(), $service->getOpenid()];
            if (isset($data['EventKey']) && is_object($data['EventKey'])) $data['EventKey'] = (array)$data['EventKey'];
            $input = ['openid' => $openid, 'appid' => $appid, 'receive' => serialize($data), 'encrypt' => intval($service->isEncrypt())];
            if (is_string($result = http_post($config['appuri'], $input, ['timeout' => 30]))) {
                return $result;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error("微信{$appid}接口调用异常，{$e->getMessage()}");
        }
        return 'success';
    }

}
