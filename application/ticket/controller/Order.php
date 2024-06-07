<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/9/6 0006
 * Time: 16:25
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


use app\basic\event\ActivityEvent;
use app\basic\event\MapEvent;
use app\wechat\service\WechatService;
use Hashids\Hashids;
use think\Controller;
use think\Db;
use think\facade\Env;
use think\facade\Request;
use wxpay\WxPayApi;
use wxpay\WxPayAppApiPay;
use wxpay\WxPayConfig;
use wxpay\WxPayUnifiedOrder;
use AliPay\Wap;
class Order extends Controller{
    /**
     * 测试订单页
     * @author ph
     * Time: 2019-09-11
     */
    public function index(){
        return $this->fetch();
    }
    public function tt(){
        halt(url('@ticket/order/notify', '', true, true));
    }
    public function index2(){
        $money = input("post.money");
        $pay = WechatService::WePayOrder();
        $openid = "oEFSYxAOHomHDMGqhFStO6bDOFxU";
        $options = [
            'body'             => '测试商品',
            'out_trade_no'     => time(),
            'total_fee'        => $money,
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
        return return_data(1,"成功",['configjson'=>$configJSON,'optionjson'=>$optionJSON]);
    }
    /**
     * 创建订单
     * @author ph
     * Time: 2019-09-06
     */
    public function createOrder(){
        try{
            //活动id
            $activity_id = input("post.activity_id");
            if(!$activity_id){
                return return_data(0,"缺少参数");
            }
            //礼物id
            $gift_id = input("post.gift_id");
            if(!$gift_id){
                return return_data(0,"缺少参数");
            }
            //用户id
            $uid = input("post.uid");
            if(!$uid){
                return return_data(0,"缺少参数");
            }
            $hs = new Hashids('ticket', 10);
            $uid = $hs->decode($uid)[0];
            $user_info = Db::name("ticket_user")->where("id","eq",$uid)->find();
            if(empty($user_info)){
                return return_data(0,"用户不存在");
            }
            //选手id
            $player_id = input("post.player_id");
            if(!$player_id){
                return return_data(0,"缺少参数");
            }
            $activity_event = new ActivityEvent();

            //获取选手信息
            $player_info = $activity_event->getPlayerInfo($player_id);
            if(empty($player_info)){
                return return_data(0,'未获取到选手信息');
            }
            if(!isset($player_info['activity_id'])){
                return return_data(0,'参数错误');
            }
            //接口传回的活动id不可信   通过选手活动关联表重新获取活动id
            $activity_id = $player_info['activity_id'];

            //数量
            $num = intval(input("post.num",1));

            //获取活动信息
            $activity_info = $activity_event->getActivityInfo($activity_id);
            //判断活动开启或关闭
            if($activity_info['status'] != 1){
                return return_data(0,"活动已关闭");
            }
            //判断活动时间
            $check_time_result = $activity_event->checkActivityTime($activity_info);
            if($check_time_result['code'] != 1){
                return return_data(0,$check_time_result['msg']);
            }
            //礼物信息
            $gift_info = $activity_event->getGiftInfo($gift_id);

            if(empty($gift_info)){
                return return_data(0,"未获取到礼物信息");
            }
            //添加订单
            $order = [];
            $order['order_id'] = generateOrderSn();
            $order['uid'] = $uid;
            $order['player_id'] = $player_id;
            $order['gift_id'] = $gift_id;
            $order['create_time'] = time();
            $order['pay_type'] = 1;
            $order['amount'] = $gift_info['gift_price'] * $num;
            $order['num'] = $num;
            $re = Db::name("ticket_order")->insert($order);
            if($re === false){
                return return_data(0,"创建订单失败");
            }
            //创建订单成功返回订单号和订单金额
            $return = [];
            $return['order_id'] = $order['order_id'];
            $return['order_price'] = $order['amount'];

            return return_data(1,"创建订单成功",$return);
        }catch (\Exception $e){
            return return_data(0,"创建订单失败");
        }
    }

    /**
     * 微信支付 JSAPI 公众号支付
     * @author ph
     * Time: 2019-09-25
     */
    public function createWxPay(){
        $order_id = input("post.order_id");
        if(!$order_id){
            return return_data(0,"缺少参数");
        }
        $order_info = Db::name("ticket_order a")
            ->field("
                a.order_id,
                a.amount,
                a.status,
                b.openid,
                c.gift_name
            ")
            ->join("ticket_user b","a.uid = b.id")
            ->join("ticket_activity_gift c","a.gift_id = c.id")
            ->where("a.order_id","eq",$order_id)
            ->find();
        if($order_info['status'] != 0){
            return return_data(0,"订单已支付");
        }

        //回调地址
        $notify_url = Request::domain()."/ticket/order/notify";
        //王志鹏修改  如果地址是http  强制改成 https
        $notify_url = str_replace("http://","https://",$notify_url);

        $pay = WechatService::WePayOrder();
        //这里的openid 要重新获取，要不然会出现openid 不匹配
        $options = [
            'body'             => $order_info['gift_name'],
            'out_trade_no'     => $order_info['order_id'],
            'total_fee'        => $order_info['amount'] * 100,
            'openid'           => $order_info['openid'],
            'trade_type'       => 'JSAPI',
            //'notify_url'       => str_replace("http://","https://",Request::domain()."/ticket/order/notify"),
            'notify_url'       => $notify_url,
            'spbill_create_ip' => request()->ip(),
        ];
        // 生成预支付码
        $result = $pay->create($options);
        //	halt($result);
        // 创建JSAPI参数签名
        $options = $pay->jsapiParams($result['prepay_id']);
        $optionJSON = json_encode($options, JSON_UNESCAPED_UNICODE);
        return return_data(1,"成功",['optionjson'=>$optionJSON]);
    }
    /**
     * 微信支付  Native
     * @author rpengwang
     * Time: 2022-12-07
     */
    public function createWxPayNative(){
        $order_id = input("post.order_id");
        if(!$order_id){
            return return_data(0,"缺少参数");
        }
        $order_info = Db::name("ticket_order a")
            ->field("
                a.order_id,
                a.amount,
                a.status,
                b.openid,
                c.gift_name
            ")
            ->join("ticket_user b","a.uid = b.id")
            ->join("ticket_activity_gift c","a.gift_id = c.id")
            ->where("a.order_id","eq",$order_id)
            ->find();
        if($order_info['status'] != 0){
            return return_data(0,"订单已支付");
        }

        //回调地址
        $notify_url = Request::domain()."/ticket/order/notify";
        //王志鹏修改  如果地址是http  强制改成 https
        $notify_url = str_replace("http://","https://",$notify_url);


        //生成小程序跳转码
        $data=[
            "out_trade_no"=>$order_info['order_id'],
            "notify_url"=>$notify_url,
            "price"=>$order_info['amount'],
        ];
        $data["sign"] = sign($data);
        $res = requestUrl("http://wx.guomei.work/api/vpay",$data);
        $res = json_decode($res,true);
        return return_data(1,"成功",$res['data']);
        /*
        $pay = WechatService::WePayOrder();
        //这里的openid 要重新获取，要不然会出现openid 不匹配
        $options = [
            'body'             => $order_info['gift_name'],
            'out_trade_no'     => $order_info['order_id'],
            'total_fee'        => $order_info['amount'] * 100,
            //'openid'           => $order_info['openid'],
            'trade_type'       => 'NATIVE',
            'notify_url'       => $notify_url,
            'spbill_create_ip' => request()->ip(),
        ];
        // 生成预支付码
        $result = $pay->create($options);
        //halt($result);
        return return_data(1,"成功",['optionjson'=>$result['code_url']]);*/

    }

    /**
     * 支付宝支付
     * @author rpengwang
     * Time: 2022-12-11
     */
    public function createAlipay(){
        $order_id = input("post.order_id");
        if(!$order_id){
            return return_data(0,"缺少参数");
        }
        $order_info = Db::name("ticket_order a")
            ->field("
                a.order_id,
                a.amount,
                a.player_id,
                a.status,
                b.openid,
                c.gift_name
            ")
            ->join("ticket_user b","a.uid = b.id")
            ->join("ticket_activity_gift c","a.gift_id = c.id")
            ->where("a.order_id","eq",$order_id)
            ->find();
        if($order_info['status'] != 0){
            return return_data(0,"订单已支付");
        }

        //回调地址
        $notify_url = Request::domain()."/ticket/order/notifya";
        //王志鹏修改  如果地址是http  强制改成 https
        $notify_url = str_replace("http://","https://",$notify_url);
        $config = [
            // 沙箱模式
            'debug'       => false,
            // 签名类型（RSA|RSA2）
            'sign_type'   => "RSA2",
            // 应用ID
            'appid'       => '2021003168666510',
            // 支付宝公钥内容 (1行填写，特别注意：这里是支付宝公钥，不是应用公钥，最好从开发者中心的网页上去复制)
            'public_key'  => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnc9cPb7SBAibcPRDTJNkZRLf9ysR3UKyxcH4WKR80p+MVj0q/eGDjf1QMWx0zcB1fudSFJlnQpHSBiz/PGPpY3R/+Ii+j5ePgsIQ7U0O9FiVDjLNQMDoxeEuq5MeGGaXytqNGHv4ItuuSkRMIOuSln+pMsA5U+3Ri7VYVkPq6TsK9QbVJNtBfuTsWcguhWOVCBpPmdI/tMcwxZkqb8Mn3BKRQLFyhCD0rWv2CRc61xJtARysYLA/y2BT/BB8RVdAcC9Ge0n1mdm9jd/df/dxgRD0q2jXtS96IRunBGBD4lQGXumq3okgNhf3N/+lRIUJw95AY6lyNsScM5+e+n06rQIDAQAB',
            // 支付宝私钥内容 (1行填写)
            'private_key' => 'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCBQFMoToxJjRv1Exl6P6Y7ZYGe2doso3rxoJm8/1dYoKemsF48ksNxp3kfxElRtJ00QTyEU6jo3rbGW2EvL2y/m/MHBT+hPa9SQ9mrWGcDbELCHTpzvap8gMD7PeDSVykm+5bkBJVVjhwLq7FY2KutC4Dxv+waIec7Ekbe9JGdD+5kX7pxHlxvY5xedY0FyEQf0tHFk2j3Uq6WHf+jbaFLJ10g+x2OlgfebQYcdPWfG6ZV5mudtJ71oX17t5sEIe/CYBAFmq/U1cmb0P9jc3lo6Wh3nBI8RgXBBoBzfO+oKIbUpieroPcljks0xr1g/MR2blAD+QUdH7ZIr527LzqTAgMBAAECggEAMADH4axqhdaWj4qsZ67D+kNUxL58PR/qRVs0PfFHa28fVNUj6rNHSyq73YR1Bpdh20pvQ2Ye0X4Fu92sVm3yoac1t2HHpAbY2mDAstZ+S9MlaBqa3umOK/dVtPniSbx9WDEQdVcOb1v8Jol5sFmoSPiAx0hUU9BLpLDBCYUjn4UHjLfm6Va/BPMUW+cOoSutdkP/j38pgTzfZpb6ET44v+GcYnwuA4XnH/NXNcVxE+Z6jrxwAXU7mVNmbvIlkq/tCENk9vRwSnATVMKmr1dGgCxVBAvb8KBKfAYYMYWf3LqsEFVbJjNeLOU/QyR+Yh33ZEVJIiXwOLt4PcTlO7LLIQKBgQDGp7DxJ6+3/eyRjT5AXdVuVPwmc/T9CRw/mEVuc9fbeKJBL0kVGXdmVDQpc0CLQti+sK4BiR3GXPCbRX+hz6pWFOzFxajrAObf4Ie1ZcLa4VWVWpeN3NGvg5dEoU3JdEEZbuNz1VVhK0jn6nXcYMV5ZMsRR0glN6hcOZMyD/ye7QKBgQCmj8yTgeppFa/S6oGyIMsXvRuwHsTSU4kuCkYcu2IBPv7RGXI/qEdJFoB5nWsJCW3KhQwAQLVWEPtEYUr744HpGt8ldI9fnh77Lp+KK3v4MB/zFUR28n0uWoqTy3nPYt7LZNHwALfUaGBE9AgUotuFcH/5nvTrhZcloD4r966PfwKBgCjFxDhKx/MAh+x7y9oUKDkj8jjGNfM1Snn2+9Emr5gZE1xDo0FUX8A96hLT19x9VNUWmDAyf0z2SF/mDMMeRzyxwML6xaeuILS0dcYSY8Le2tzzogV43ASlAogQf1Gora/VpZNhpstxwd3vrk8UAZPfzxn1wmX5HXIIUyDETTolAoGAPkGhhcEZjQ2+Gmfs8levkb/tiXb2umbe74aHjlW73Btfw2hve7u6aWcvvkVIrKgJkZkJU07ceL8ALB7xmBUBic+xeJ6IuISxr9FNcKewPqQ+TO22GX+pqpnNElELxqh9/ihBECQ0kgAxe5R7MLtusgHK09LGWQIl7LpogqahaKECgYB9zR9DtO9Pi1Ojfv5IpvuUdMwtAogbjtv1ba030Qv/sZMfsE4B4zWpYFA+6ipxWSNvhxtH1U3Nm0g1oxqKHGCk7Qc/5GuLUfcZspLEvImn5ppoxaFgY82qRecXv2mmjpQtLGVceJ9F/W/sJlT/Lncx6HxPwClCOKsyTtxaNSuYcg==',
            // 应用公钥证书内容（新版资金类接口转 app_cert_sn）
            'app_cert'    => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgUBTKE6MSY0b9RMZej+mO2WBntnaLKN68aCZvP9XWKCnprBePJLDcad5H8RJUbSdNEE8hFOo6N62xlthLy9sv5vzBwU/oT2vUkPZq1hnA2xCwh06c72qfIDA+z3g0lcpJvuW5ASVVY4cC6uxWNirrQuA8b/sGiHnOxJG3vSRnQ/uZF+6cR5cb2OcXnWNBchEH9LRxZNo91Kulh3/o22hSyddIPsdjpYH3m0GHHT1nxumVeZrnbSe9aF9e7ebBCHvwmAQBZqv1NXJm9D/Y3N5aOlod5wSPEYFwQaAc3zvqCiG1KYnq6D3JY5LNMa9YPzEdm5QA/kFHR+2SK+duy86kwIDAQAB',
            // 支付宝根证书内容（新版资金类接口转 alipay_root_cert_sn）
            'root_cert'   => '',

        ];
        $config['notify_url'] = $notify_url;
        $config['return_url'] = 'https://h6.guomei.work/pages/vote/user?id='.$order_info['player_id'];
        $pay = \AliPay\Wap::instance($config);


        $result = $pay->apply([
            'out_trade_no' => $order_info['order_id'], // 商户订单号
            'total_amount' => $order_info['amount'],
            'subject'      => $order_info['gift_name'],
        ]);

        return return_data(1,"成功",['pay'=>$result]);

    }
    /**
     * 支付宝的支付回调
     * @author ph
     * Time: 2019-09-07
     */
    public function notifya(){
        $config = [
            // 沙箱模式
            'debug'       => false,
            // 签名类型（RSA|RSA2）
            'sign_type'   => "RSA2",
            // 应用ID
            'appid'       => '2021003168666510',
            // 支付宝公钥内容 (1行填写，特别注意：这里是支付宝公钥，不是应用公钥，最好从开发者中心的网页上去复制)
            'public_key'  => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnc9cPb7SBAibcPRDTJNkZRLf9ysR3UKyxcH4WKR80p+MVj0q/eGDjf1QMWx0zcB1fudSFJlnQpHSBiz/PGPpY3R/+Ii+j5ePgsIQ7U0O9FiVDjLNQMDoxeEuq5MeGGaXytqNGHv4ItuuSkRMIOuSln+pMsA5U+3Ri7VYVkPq6TsK9QbVJNtBfuTsWcguhWOVCBpPmdI/tMcwxZkqb8Mn3BKRQLFyhCD0rWv2CRc61xJtARysYLA/y2BT/BB8RVdAcC9Ge0n1mdm9jd/df/dxgRD0q2jXtS96IRunBGBD4lQGXumq3okgNhf3N/+lRIUJw95AY6lyNsScM5+e+n06rQIDAQAB',
            // 支付宝私钥内容 (1行填写)
            'private_key' => 'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCBQFMoToxJjRv1Exl6P6Y7ZYGe2doso3rxoJm8/1dYoKemsF48ksNxp3kfxElRtJ00QTyEU6jo3rbGW2EvL2y/m/MHBT+hPa9SQ9mrWGcDbELCHTpzvap8gMD7PeDSVykm+5bkBJVVjhwLq7FY2KutC4Dxv+waIec7Ekbe9JGdD+5kX7pxHlxvY5xedY0FyEQf0tHFk2j3Uq6WHf+jbaFLJ10g+x2OlgfebQYcdPWfG6ZV5mudtJ71oX17t5sEIe/CYBAFmq/U1cmb0P9jc3lo6Wh3nBI8RgXBBoBzfO+oKIbUpieroPcljks0xr1g/MR2blAD+QUdH7ZIr527LzqTAgMBAAECggEAMADH4axqhdaWj4qsZ67D+kNUxL58PR/qRVs0PfFHa28fVNUj6rNHSyq73YR1Bpdh20pvQ2Ye0X4Fu92sVm3yoac1t2HHpAbY2mDAstZ+S9MlaBqa3umOK/dVtPniSbx9WDEQdVcOb1v8Jol5sFmoSPiAx0hUU9BLpLDBCYUjn4UHjLfm6Va/BPMUW+cOoSutdkP/j38pgTzfZpb6ET44v+GcYnwuA4XnH/NXNcVxE+Z6jrxwAXU7mVNmbvIlkq/tCENk9vRwSnATVMKmr1dGgCxVBAvb8KBKfAYYMYWf3LqsEFVbJjNeLOU/QyR+Yh33ZEVJIiXwOLt4PcTlO7LLIQKBgQDGp7DxJ6+3/eyRjT5AXdVuVPwmc/T9CRw/mEVuc9fbeKJBL0kVGXdmVDQpc0CLQti+sK4BiR3GXPCbRX+hz6pWFOzFxajrAObf4Ie1ZcLa4VWVWpeN3NGvg5dEoU3JdEEZbuNz1VVhK0jn6nXcYMV5ZMsRR0glN6hcOZMyD/ye7QKBgQCmj8yTgeppFa/S6oGyIMsXvRuwHsTSU4kuCkYcu2IBPv7RGXI/qEdJFoB5nWsJCW3KhQwAQLVWEPtEYUr744HpGt8ldI9fnh77Lp+KK3v4MB/zFUR28n0uWoqTy3nPYt7LZNHwALfUaGBE9AgUotuFcH/5nvTrhZcloD4r966PfwKBgCjFxDhKx/MAh+x7y9oUKDkj8jjGNfM1Snn2+9Emr5gZE1xDo0FUX8A96hLT19x9VNUWmDAyf0z2SF/mDMMeRzyxwML6xaeuILS0dcYSY8Le2tzzogV43ASlAogQf1Gora/VpZNhpstxwd3vrk8UAZPfzxn1wmX5HXIIUyDETTolAoGAPkGhhcEZjQ2+Gmfs8levkb/tiXb2umbe74aHjlW73Btfw2hve7u6aWcvvkVIrKgJkZkJU07ceL8ALB7xmBUBic+xeJ6IuISxr9FNcKewPqQ+TO22GX+pqpnNElELxqh9/ihBECQ0kgAxe5R7MLtusgHK09LGWQIl7LpogqahaKECgYB9zR9DtO9Pi1Ojfv5IpvuUdMwtAogbjtv1ba030Qv/sZMfsE4B4zWpYFA+6ipxWSNvhxtH1U3Nm0g1oxqKHGCk7Qc/5GuLUfcZspLEvImn5ppoxaFgY82qRecXv2mmjpQtLGVceJ9F/W/sJlT/Lncx6HxPwClCOKsyTtxaNSuYcg==',
            // 应用公钥证书内容（新版资金类接口转 app_cert_sn）
            'app_cert'    => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgUBTKE6MSY0b9RMZej+mO2WBntnaLKN68aCZvP9XWKCnprBePJLDcad5H8RJUbSdNEE8hFOo6N62xlthLy9sv5vzBwU/oT2vUkPZq1hnA2xCwh06c72qfIDA+z3g0lcpJvuW5ASVVY4cC6uxWNirrQuA8b/sGiHnOxJG3vSRnQ/uZF+6cR5cb2OcXnWNBchEH9LRxZNo91Kulh3/o22hSyddIPsdjpYH3m0GHHT1nxumVeZrnbSe9aF9e7ebBCHvwmAQBZqv1NXJm9D/Y3N5aOlod5wSPEYFwQaAc3zvqCiG1KYnq6D3JY5LNMa9YPzEdm5QA/kFHR+2SK+duy86kwIDAQAB',
            // 支付宝根证书内容（新版资金类接口转 alipay_root_cert_sn）
            'root_cert'   => '',

        ];
        $pay = \AliPay\App::instance($config);

        $result = $pay->notify();
        if (in_array($result['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            // @todo 更新订单状态，支付完成
            file_put_contents('notify.txt', date("Y-m-d H:i:s")."收到来自支付宝的异步通知\r\n", FILE_APPEND);
            file_put_contents('notify.txt', '订单号：' . $result['out_trade_no'] . "\r\n", FILE_APPEND);
            file_put_contents('notify.txt', '订单金额：' . $result['total_amount'] . "\r\n\r\n", FILE_APPEND);
            file_put_contents('notify.txt', 'con：' . json_encode($result). "\r\n\r\n", FILE_APPEND);

            $order_id = $result['out_trade_no'];
            $where = [];
            $where[] = ['order_id',"eq",$order_id];
            $order_info = Db::name("ticket_order")
                ->where($where)
                ->find();
            if(empty($order_info)){
                return return_data(0,"未获取到订单信息");
            }
            if($order_info['status'] == 1){
                exit('success');
            }
            //获取礼物信息
            $gift_info = Db::name("ticket_activity_gift")
                ->where("id","eq",$order_info['gift_id'])
                ->find();
            //根据礼物数量增加得票数量
            $gift_info['gift_ticket_num'] = $gift_info['gift_ticket_num'] * (isset($order_info['num']) ? $order_info['num'] : 1);
            //如果是盲盒礼物 那就要随机一下子了  2021-12-03
            if($gift_info['is_acciden']==1){
                $ticketnum = 0;
                $activity_event = new ActivityEvent();
                $gift_list = $activity_event->getActivityGiftList($gift_info['activity_id']);

                //获取概率的配置
                $probability = config('probability');
                //奖品列表
                $gift_pro=[];
                for($i=0;$i<$order_info['num'];$i++){
                    //买了几个就随机几次
                    $anum = random_int(1,100);

                    foreach ($probability as $k=>$v){
                        if($anum <=$v){
                            //抽中了

                            //查询礼物
                            $gift_arr = Db::name("ticket_activity_gift")
                                ->where("id","eq",$k)
                                ->find();
                            $gift_arr['activity_id'] = $gift_info['activity_id'];
                            //抽奖用户的id
                            $gift_arr['uid'] = $order_info['uid'];
                            $gift_arr['order_id'] = $order_info['id'];
                            $gift_arr['create_time'] = time();
                            unset($gift_arr['id']);
                            unset($gift_arr['status']);
                            unset($gift_arr['is_acciden']);
                            unset($gift_arr['sort']);

                            unset($gift_arr['introduce']);
                            array_push($gift_pro,$gift_arr);
                            //加票累计
                            $ticketnum += $gift_arr["gift_ticket_num"];
                            break;
                        }
                    }
                }

                //写入获奖数据
                Db::name("ticket_order_gift_pro")->insertAll($gift_pro);

                $gift_info['gift_ticket_num']  = $ticketnum;
            }
            $player_info = Db::name("ticket_activity_player")
                ->field("ticket_num,gift_money")
                ->where("player_id","eq",$order_info['player_id'])
                ->find();
            Db::startTrans();
            //根据礼物增加对应选手的票数  增加对应的礼物金额
            $save_player = [];
            /*
            $save_player['ticket_num'] = ($gift_info['gift_ticket_num'] + $player_info['ticket_num']);
            $save_player['gift_money'] = ($order_info['amount'] + $player_info['gift_money']);
            $res = Db::name("ticket_activity_player")->where("player_id","eq",$order_info['player_id'])->update($save_player);
            */
            //换个写法
            $res = Db::name("ticket_activity_player")->where(['player_id'=>$order_info['player_id']])->setInc('ticket_num',$gift_info['gift_ticket_num']);
            $res = Db::name("ticket_activity_player")->where(['player_id'=>$order_info['player_id']])->setInc('gift_money',$result['buyer_pay_amount']*100/100);

            if($res === false){
                Db::rollback();
                return return_data(0,"增加失败");
            }
            //根据回调值处理订单状态 修改加票状态
            $save = [];
            $save['status'] = 1;
            $save['send_status'] = 1;
            $save['trade_no'] = $result['out_trade_no'];
            $save['trade_success_time'] = $result['gmt_payment'];
            $save['pay_result'] = json_encode($result);
            $re = Db::name("ticket_order")->where("order_id","eq",$order_id)->update($save);
            if($re === false){
                Db::rollback();
                return return_data(0,"订单处理失败");
            }
            Db::commit();
            exit('success');
        } else {
            file_put_contents('notify.txt', "收到异步通知\r\n", FILE_APPEND);
        }


    }
    /**
     * 支付回调
     * @author ph
     * Time: 2019-09-07
     */
    public function notify(){
        //获取返回的xml
        $testxml = file_get_contents("php://input");
        file_put_contents("pay.log",date("Y-m-d H:i:s").PHP_EOL."date:".$testxml .PHP_EOL.PHP_EOL.PHP_EOL,FILE_APPEND);
        //将xml转化为json格式
        $jsonxml = json_encode(simplexml_load_string($testxml, 'SimpleXMLElement', LIBXML_NOCDATA));
        //转成数组
        $result = json_decode($jsonxml, true);
        file_put_contents("pay.log",date("Y-m-d H:i:s").PHP_EOL."date:".json_encode($result) .PHP_EOL.PHP_EOL.PHP_EOL,FILE_APPEND);

        if($result['result_code'] == "SUCCESS" && $result['return_code'] == "SUCCESS"){
            //判断是否支付成功
            $order_id = $result['out_trade_no'];
            $where = [];
            $where[] = ['order_id',"eq",$order_id];
            $order_info = Db::name("ticket_order")
                ->where($where)
                ->find();
            if(empty($order_info)){
                return return_data(0,"未获取到订单信息");
            }
            if($order_info['status'] == 1){
                echo "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";die;
            }
            //获取礼物信息
            $gift_info = Db::name("ticket_activity_gift")
                ->where("id","eq",$order_info['gift_id'])
                ->find();
            //根据礼物数量增加得票数量
            $gift_info['gift_ticket_num'] = $gift_info['gift_ticket_num'] * (isset($order_info['num']) ? $order_info['num'] : 1);
            //如果是盲盒礼物 那就要随机一下子了  2021-12-03
            if($gift_info['is_acciden']==1){
                $ticketnum = 0;
                $activity_event = new ActivityEvent();
                $gift_list = $activity_event->getActivityGiftList($gift_info['activity_id']);
                //获取概率的配置
                $probability = config('probability');
                //奖品列表
                $gift_pro=[];
                for($i=0;$i<$order_info['num'];$i++){
                    //买了几个就随机几次
                    $anum = random_int(1,100);

                    foreach ($probability as $k=>$v){
                        if($anum <=$v){
                            //抽中了

                            //查询礼物
                            $gift_arr = Db::name("ticket_activity_gift")
                                ->where("id","eq",$k)
                                ->find();
                            $gift_arr['activity_id'] = $gift_info['activity_id'];
                            //抽奖用户的id
                            $gift_arr['uid'] = $order_info['uid'];
                            $gift_arr['order_id'] = $order_info['id'];
                            $gift_arr['create_time'] = time();
                            unset($gift_arr['id']);
                            unset($gift_arr['status']);
                            unset($gift_arr['is_acciden']);
                            unset($gift_arr['sort']);
                            unset($gift_arr['introduce']);
                            array_push($gift_pro,$gift_arr);
                            //加票累计
                            $ticketnum += $gift_arr["gift_ticket_num"];
                            break;
                        }
                    }
                }
                //写入获奖数据
                Db::name("ticket_order_gift_pro")->insertAll($gift_pro);
                $gift_info['gift_ticket_num']  = $ticketnum;
            }
            $player_info = Db::name("ticket_activity_player")
                ->field("ticket_num,gift_money")
                ->where("player_id","eq",$order_info['player_id'])
                ->find();
            Db::startTrans();
            //根据礼物增加对应选手的票数  增加对应的礼物金额
            $save_player = [];
            /*
            $save_player['ticket_num'] = ($gift_info['gift_ticket_num'] + $player_info['ticket_num']);
            $save_player['gift_money'] = ($order_info['amount'] + $player_info['gift_money']);
            $res = Db::name("ticket_activity_player")->where("player_id","eq",$order_info['player_id'])->update($save_player);
            */
            //换个写法
            $res = Db::name("ticket_activity_player")->where(['player_id'=>$order_info['player_id']])->setInc('ticket_num',$gift_info['gift_ticket_num']);
            $res = Db::name("ticket_activity_player")->where(['player_id'=>$order_info['player_id']])->setInc('gift_money',$result['cash_fee']/100);

            if($res === false){
                Db::rollback();
                return return_data(0,"增加失败");
            }
            //根据回调值处理订单状态 修改加票状态
            $save = [];
            $save['status'] = 1;
            $save['send_status'] = 1;
            $save['trade_no'] = $result['transaction_id'];
            $save['trade_success_time'] = $result['time_end'];
            $save['pay_result'] = json_encode($result);
            $re = Db::name("ticket_order")->where("order_id","eq",$order_id)->update($save);
            if($re === false){
                Db::rollback();
                return return_data(0,"订单处理失败");
            }
            Db::commit();
            echo "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";
        }else{
            $order_id = isset($result['out_trade_no']) ? $result['out_trade_no'] : "未获取到订单id";
            file_put_contents("pay_error.log","时间:".date("Y-m-d H:i:s")."  订单id:".$order_id.PHP_EOL."返回值:".json_encode($result).PHP_EOL,FILE_APPEND);
        }
    }

    /**
     * 获取微信配置
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author ph
     * Time: 2019-09-29
     */
    public function getconfigjson(){
        if(Request::instance()->isPost() == false){
            return return_data(0,"请求方式有误");
        }
        $url = input("post.url");
        if(!$url){
            return return_data(0,"缺少参数");
        }
        $configJSON = json_encode(WechatService::getWebJssdkSign($url), JSON_UNESCAPED_UNICODE);
        return return_data(1,"获取成功",['config'=>$configJSON]);
    }

    public function createMiniWeChatPay(){
        $order_id = input("post.order_id");
        if(!$order_id){
            return return_data(0,"缺少参数");
        }
        $order_info = Db::name("ticket_order a")
            ->field("
                a.order_id,
                a.amount,
                a.status,
                b.openid,
                c.gift_name,
                a.uid
            ")
            ->join("ticket_user b","a.uid = b.id")
            ->join("ticket_activity_gift c","a.gift_id = c.id")
            ->where("a.order_id","eq",$order_id)
            ->find();
        if($order_info['status'] != 0){
            return return_data(0,"订单已支付");
        }
        //查询用户信息
        $user_info = Db::name("ticket_user")->where('id','eq',$order_info['uid'])->find();
        if(empty($user_info)){
            return return_data(0,'未获取到用户信息');
        }
        //回调地址
        $notify_url = Request::domain()."/ticket/order/notify";
        //商品简述
        $body = '订单支付';

        include Env::get("root_path") . "extend/wxpay/WxPay.Data.php";
        include Env::get("root_path") . "extend/wxpay/WxPayConfig.php";
        include Env::get("root_path") . "extend/wxpay/WxPay.php";

        $input = new WxPayUnifiedOrder();
        $input->SetBody($body);
        $input->SetOut_trade_no( $order_id);
        $input->SetTotal_fee(100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag($order_id);
        $input->SetOpenid($user_info['openid']);
        $input->SetNotify_url($notify_url);
        $input->SetTrade_type('JSAPI');
        $wxConfig = new WxPayConfig(config('wechat.mini_pay'));
        $WxPayApi = new WxPayApi();
        $unifiedOrder = $WxPayApi::unifiedOrder($wxConfig, $input);
        if (
            array_key_exists("appid", $unifiedOrder) &&
            array_key_exists("prepay_id", $unifiedOrder) &&
            $unifiedOrder['prepay_id'] != "")
        {
//            $app_api = new WxPayAppApiPay();
//            $app_api->SetAppid($unifiedOrder["appid"]);
//            $app_api->SetMch_id($unifiedOrder["mch_id"]);
//            $app_api->SetTimeStamp(time());
//            $app_api->SetNonceStr(\wxpay\WxPayApi::getNonceStr());
//            $app_api->SetPackage("Sign=WXPay");
//            $app_api->SetPrepay($unifiedOrder['prepay_id']);
//            $app_api->SetPaySign($app_api->MakeSign($wxConfig, false));
//            $parameters = $app_api->GetValues();

            $nonce_str = $this->nonce_str();
            $time = time();
            $tmp=[];//临时数组用于签名
            $tmp['appId'] = $unifiedOrder["appid"];
            $tmp['nonceStr'] = $nonce_str;
            $tmp['package'] = 'prepay_id='.$unifiedOrder['prepay_id'];
            $tmp['signType'] = 'MD5';
            $tmp['timeStamp'] = (string)$time;
            $parameters['appId'] = $unifiedOrder["appid"];
            $parameters['timestamp'] = (string)$time;//时间戳
            $parameters['noncestr'] = $nonce_str;//随机字符串
            //$parameters['signType'] = 'MD5';//签名算法，暂支持 MD5
            $parameters['package'] = $unifiedOrder['prepay_id'];//统一下单接口返回的 prepay_id 参数值，提交格式如：prepay_id=*
            $parameters['sign'] = $this->sign($tmp);//签名,具体签名方案参见微信公众号支付帮助文档;
            //$parameters['out_trade_no'] = $order_id;

        } else {
            return return_data(0, $unifiedOrder['err_code_des']);
        }
        return return_data(1, '成功', $parameters);

    }

    //签名 $data要先排好顺序
    private function sign($data){
        $stringA = '';
        foreach ($data as $key=>$value){
            if(!$value) continue;
            if($stringA) $stringA .= '&'.$key."=".$value;
            else $stringA = $key."=".$value;
        }
        $wx_key = "012476B52BCDA46940F1E9C406A794CF";
        $stringSignTemp = $stringA.'&key='.$wx_key;//申请支付后有给予一个商户账号和密码，登陆后自己设置key
        return strtoupper(md5($stringSignTemp));
    }

    //随机32位字符串
    private function nonce_str(){
        $result = '';
        $str = 'QWERTYUIOPASDFGHJKLZXVBNMqwertyuioplkjhgfdsamnbvcxz';
        for ($i=0;$i<32;$i++){
            $result .= $str[rand(0,48)];
        }
        return $result;
    }

    /**
     * 新代码调用 原框架sdk来处理支付问题。
     *
     */
    public function createMiniWeChatPay2(){
        $order_id = input("post.order_id");
        if(!$order_id){
            return return_data(0,"缺少参数");
        }
        $order_info = Db::name("ticket_order a")
            ->field("
                a.order_id,
                a.amount,
                a.status,
                b.openid,
                c.gift_name,
                a.uid
            ")
            ->join("ticket_user b","a.uid = b.id")
            ->join("ticket_activity_gift c","a.gift_id = c.id")
            ->where("a.order_id","eq",$order_id)
            ->find();
        if($order_info['status'] != 0){
            return return_data(0,"订单已支付");
        }

        //查询用户信息
        $user_info = Db::name("ticket_user")->where('id','eq',$order_info['uid'])->find();
        if(empty($user_info)){
            return return_data(0,'未获取到用户信息');
        }
        //回调地址
        $notify_url = Request::domain()."/ticket/order/notify";
        //王志鹏修改  如果地址是http  强制改成 https
        $notify_url = str_replace("http://","https://",$notify_url);
        //生成预支付订单

        $pay = WechatService::WePayOrder(config('wechat.mini_pay'));
        $options = [
            'body'             => $order_info['gift_name'],
            'out_trade_no'     => $order_info['order_id'],
            'total_fee'        => $order_info['amount'] * 100,
            'openid'           => $order_info['openid'],
            'trade_type'       => 'JSAPI',
            'notify_url'       => $notify_url,
            'spbill_create_ip' => request()->ip(),
        ];
        // 生成预支付码
        $unifiedOrder = $pay->create($options);
        //做二次签名
        if (
            array_key_exists("appid", $unifiedOrder) &&
            array_key_exists("prepay_id", $unifiedOrder) &&
            $unifiedOrder['prepay_id'] != "")
        {

            $parameters = $pay->jsapiParams($unifiedOrder['prepay_id']);
        } else {
            return return_data(0, $unifiedOrder['err_code_des'].'to');
        }


        return return_data(1, '成功', $parameters);

    }

    //查询订单的抽奖结果
    public function getOrderDraw(){
        $order_sn = input("order_sn");
        if(!$order_sn){
            return return_data(0,"缺少参数");
        }
        $gift_list = Db::name("ticket_order a")
            ->field("
                b.*
            ")
            ->join("ticket_order_gift_pro b","a.id = b.order_id")
            ->where("a.order_id","eq",$order_sn)
            ->select();
        $gclist_new = array_column($gift_list,'gift_ticket_num');
        return return_data(1, '成功', ['glist'=>$gift_list,'gcount'=>array_sum($gclist_new)]);

    }


    /**
     * 支付回调 测试
     * @author ph
     * Time: 2019-09-07
     */
    public function notest(){
        exit('33');
        $resultstr = '{"appid":"wxc3bb9ae04ee7205d","bank_type":"OTHERS","cash_fee":"10000","fee_type":"CNY","is_subscribe":"N","mch_id":"1567208801","nonce_str":"hrh96nyowuqfdz6u6pgsr1zcq1agy19q","openid":"o_MKK5MFWAkQqL9MXVyFtRZMspTk","out_trade_no":"20211215549910098426645482","result_code":"SUCCESS","return_code":"SUCCESS","sign":"ECD68B17B77EAB69F25CCEBCF8B1DFA4","time_end":"20211215002305","total_fee":"10000","trade_type":"JSAPI","transaction_id":"4200001329202112151852349363"}';
        $result = json_decode($resultstr, true);
        if($result['result_code'] == "SUCCESS" && $result['return_code'] == "SUCCESS"){
            //判断是否支付成功
            $order_id = $result['out_trade_no'];
            $where = [];
            $where[] = ['order_id',"eq",$order_id];
            $order_info = Db::name("ticket_order")
                ->where($where)
                ->find();
            if(empty($order_info)){
                return return_data(0,"未获取到订单信息");
            }
            if($order_info['status'] == 1){
                echo "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";die;
            }
            //获取礼物信息
            $gift_info = Db::name("ticket_activity_gift")
                ->where("id","eq",$order_info['gift_id'])
                ->find();
            //根据礼物数量增加得票数量
            $gift_info['gift_ticket_num'] = $gift_info['gift_ticket_num'] * (isset($order_info['num']) ? $order_info['num'] : 1);
            //如果是盲盒礼物 那就要随机一下子了  2021-12-03
            if($gift_info['is_acciden']==1){
                $ticketnum = 0;
                $activity_event = new ActivityEvent();
                $gift_list = $activity_event->getActivityGiftList($gift_info['activity_id']);

                //获取概率的配置
                $probability = config('probability');
                //奖品列表
                $gift_pro=[];
                for($i=0;$i<$order_info['num'];$i++){
                    //买了几个就随机几次
                    $anum = random_int(1,100);

                    foreach ($probability as $k=>$v){
                        if($anum <=$v){
                            //抽中了

                            //查询礼物
                            $gift_arr = Db::name("ticket_activity_gift")
                                ->where("id","eq",$k)
                                ->find();
                            $gift_arr['activity_id'] = $gift_info['activity_id'];
                            //抽奖用户的id
                            $gift_arr['uid'] = $order_info['uid'];
                            $gift_arr['order_id'] = $order_info['id'];
                            $gift_arr['create_time'] = time();
                            unset($gift_arr['id']);
                            unset($gift_arr['status']);
                            unset($gift_arr['is_acciden']);
                            unset($gift_arr['sort']);

                            unset($gift_arr['introduce']);
                            array_push($gift_pro,$gift_arr);
                            //加票累计
                            $ticketnum += $gift_arr["gift_ticket_num"];
                            break;
                        }
                    }
                }

                //写入获奖数据
                Db::name("ticket_order_gift_pro")->insertAll($gift_pro);

                $gift_info['gift_ticket_num']  = $ticketnum;
            }
            $player_info = Db::name("ticket_activity_player")
                ->field("ticket_num,gift_money")
                ->where("player_id","eq",$order_info['player_id'])
                ->find();
            Db::startTrans();
            //根据礼物增加对应选手的票数  增加对应的礼物金额
            $save_player = [];
            /*
            $save_player['ticket_num'] = ($gift_info['gift_ticket_num'] + $player_info['ticket_num']);
            $save_player['gift_money'] = ($order_info['amount'] + $player_info['gift_money']);
            $res = Db::name("ticket_activity_player")->where("player_id","eq",$order_info['player_id'])->update($save_player);
            */
            //换个写法
            $res = Db::name("ticket_activity_player")->where(['player_id'=>$order_info['player_id']])->setInc('ticket_num',$gift_info['gift_ticket_num']);
            $res = Db::name("ticket_activity_player")->where(['player_id'=>$order_info['player_id']])->setInc('gift_money',$result['cash_fee']/100);

            if($res === false){
                Db::rollback();
                return return_data(0,"增加失败");
            }
            //根据回调值处理订单状态 修改加票状态
            $save = [];
            $save['status'] = 1;
            $save['send_status'] = 1;
            $save['trade_no'] = $result['transaction_id'];
            $save['trade_success_time'] = $result['time_end'];
            $save['pay_result'] = json_encode($result);
            $re = Db::name("ticket_order")->where("order_id","eq",$order_id)->update($save);
            if($re === false){
                Db::rollback();
                return return_data(0,"订单处理失败");
            }
            Db::commit();
            echo "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";
        }else{
            $order_id = isset($result['out_trade_no']) ? $result['out_trade_no'] : "未获取到订单id";
            file_put_contents("pay_error.log","时间:".date("Y-m-d H:i:s")."  订单id:".$order_id.PHP_EOL."返回值:".json_encode($result).PHP_EOL,FILE_APPEND);
        }
    }




}