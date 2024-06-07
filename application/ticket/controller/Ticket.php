<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/9/9 0009
 * Time: 20:40
 *  　　　┏┓　　　┏┓
 * 　　┏┛┻━━━┛┻┓
 * 　　┃　　　　　　　┃
 * 　　┃　　　━　　　┃
 * 　　┃　┳┛　┗┳　┃
 * 　　┃　　　　　　　┃
 * 　　┃　　　┻　　　┃
 * 　　┃　　　　　　　┃
 * 　　┗━┓　　　┏━┛
 * 　　　　┃　　　┃神兽保佑
 * 　　　　┃　　　┃代码无BUG！
 * 　　　　┃　　　┗━━━┓
 * 　　　　┃　　　　　　　┣┓
 * 　　　　┃　　　　　　　┏┛
 * 　　　　┗┓┓┏━┳┓┏┛
 * 　　　　　┃┫┫　┃┫┫
 * 　　　　　┗┻┛　┗┻┛
 */

namespace app\ticket\controller;


use app\wechat\service\WechatService;
use Hashids\Hashids;
use think\Controller;
use think\Db;

class Ticket extends Controller{
    /**
     * 首页
     * @return mixed
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author ph
     * Time: 2019-09-09
     */
    public function wxLogin(){
        $code = input("post.code");
        //获取openid
        $url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxcd22c9b9be188c71&secret=825d967b57e8defb9decd7803dde3fb5&code=$code&grant_type=authorization_code";
        $result= http_request($url);
        $jsoninfo= json_decode($result, true);
        $openid= $jsoninfo["openid"];//从返回json结果中读出openid


        $this->url = $this->request->url(true);
        $result = WechatService::getWebOauthInfo($this->url, 1);
        //查询用户是否存在
        $uid = Db::name("ticket_user")->where("openid","eq",$result['openid'])->value("id");
        if(!$uid){
            //存储用户信息
            $user_data = [];
            $user_data['openid'] = $result['openid'];
            $user_data['nickname'] = $result['fansinfo']['nickname'];
            $user_data['sex'] = $result['fansinfo']['sex'];
            $user_data['country'] = $result['fansinfo']['country'];
            $user_data['province'] = $result['fansinfo']['province'];
            $user_data['city'] = $result['fansinfo']['city'];
            $user_data['headimgurl'] = $result['fansinfo']['headimgurl'];
            $user_data['create_time'] = time();
            $uid = Db::name("ticket_user")->insertGetId($user_data);
            if(!$uid){
                return return_data(0,"请求有误");
            }
        }
        $hs = new Hashids('ticket', 10);
        $uid = $hs->encode($uid);
        return return_data(1,"获取成功",['uid'=>$uid]);
    }

    /**
     * 支付
     * @author ph
     * Time: 2019-09-09
     */
    public function index2(){
        $pay = WechatService::WePayOrder();
        $openid = WechatService::getWebOauthInfo(request()->url(true), 0)['openid'];
        $options = [
            'body'             => '测试商品',
            'out_trade_no'     => time(),
            'total_fee'        => '1',
            'openid'           => $openid,
            'trade_type'       => 'JSAPI',
            'notify_url'       => url('@wechat/api.tools/notify', '', true, true),
            'spbill_create_ip' => request()->ip(),
        ];
        // 生成预支付码
        $result = $pay->create($options);
        // 创建JSAPI参数签名
        $options = $pay->jsapiParams($result['prepay_id']);
        $optionJSON = json_encode($options, JSON_UNESCAPED_UNICODE);
        // JSSDK 签名配置
        $configJSON = json_encode(WechatService::getWebJssdkSign(), JSON_UNESCAPED_UNICODE);

        echo '<pre>';
        echo "当前用户OPENID: {$openid}";
        echo "\n--- 创建预支付码 ---\n";
        var_export($result);
        echo '</pre>';

        echo '<pre>';
        echo "\n\n--- JSAPI 及 H5 参数 ---\n";
        var_export($options);
        echo '</pre>';
        echo "<button id='paytest' type='button'>JSAPI支付测试</button>";
        echo "
        <script src='//res.wx.qq.com/open/js/jweixin-1.2.0.js'></script>
        <script>
            wx.config($configJSON);
            document.getElementById('paytest').onclick = function(){
                var options = $optionJSON;
                options.success = function(){
                    alert('支付成功');
                }
                wx.chooseWXPay(options);
            }
        </script>";
    }

    /**
     * 测试直播页
     * @author ph
     * Time: 2019-09-12
     */
    public function testlive(){
        return $this->fetch();
    }
}