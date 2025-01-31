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
use think\Db;
use think\exception\HttpResponseException;

/**
 * Class Wechat
 * @package app\store\controller\api
 */
class Wechat extends Controller
{
    /**
     * 获取小程序配置
     * @return array
     */
    private function config()
    {
        return config('wechat.miniapp');
    }

    /**
     * Code信息换取
     */
    public function session()
    {
        try {
            $code = $this->request->post('code');
            $result = \We::WeMiniCrypt($this->config())->session($code);
            if (isset($result['openid'])) {
                data_save('StoreMember', ['openid' => $result['openid']], 'openid');
                $result['member'] = Db::name('StoreMember')->where(['openid' => $result['openid']])->find();
                $this->success('授权CODE信息换取成功！', $result);
            } else {
                $this->error("[{$result['errcode']}] {$result['errmsg']}");
            }
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error("授权CODE信息换取失败，{$exception->getMessage()}");
        }
    }

    /**
     * 小程序数据解密
     */
    public function decode()
    {
        try {
            $iv = $this->request->post('iv');
            $session = $this->request->post('session');
            $content = $this->request->post('encrypted');
            if (empty($session)) {
                $code = $this->request->post('code');
                $result = \We::WeMiniCrypt($this->config())->session($code);
                $session = isset($result['session_key']) ? $result['session_key'] : '';
            }
            $result = \We::WeMiniCrypt($this->config())->decode($iv, $session, $content);
            if ($result !== false && isset($result['openId'])) {
                data_save('StoreMember', [
                    'openid'   => $result['openId'],
                    'headimg'  => $result['avatarUrl'],
                    'nickname' => emoji_encode($result['nickName']),
                ], 'openid');
                $result['member'] = Db::name('StoreMember')->where(['openid' => $result['openId']])->find();
                $this->success('小程序加密数据解密成功！', $result);
            } else {
                $this->error('小程序加密数据解密失败，请稍候再试！');
            }
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("小程序加密数据解密失败，{$e->getMessage()}");
        }
    }

}