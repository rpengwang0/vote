<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
 
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

return [
    // 微信开放平台接口
    'service_url' => 'https://framework.thinkadmin.top',
    // 小程序支付参数
//    'miniapp'     => [
//        'appid'      => 'wx8c108930fe12b7ef',
//        'appsecret'  => '13d829992a2b6a0a44195a4a580da56d',
//        'mch_id'     => '1332187001',
//        'mch_key'    => 'A82DC5BD1F3359081049C568D8502BC5',
//        'ssl_p12'    => __DIR__ . '/cert/1332187001_20181030_cert.p12',
//        'cache_path' => env('runtime_path') . 'wechat' . DIRECTORY_SEPARATOR,
//    ],

    // 小程序登录配置  梅雅科技
    /*'wx_min_app' => array(
        'AppId' => 'wxdf5321739516475b',
        'AppSecret' => 'e6d37a2502fb22e6a646133d7c50a80e'
    ),
    //小程序支付支付配置
    'mini_pay' => array(
        'appid' => 'wxdf5321739516475b',
        'mch_id' => '1567208801',
        'mch_key'=>'2cbe19648e4a7f4846e69413b471c01d',
        'MerchantId' => '1567208801',
        'SignType' => 'MD5',
        'Key' => '2cbe19648e4a7f4846e69413b471c01d',
        'AppSecret' => '11ed59644e68819f1bcbe82e018d4ed2',
    ),
    
    */
    // 公众号  东方梅雅
    'wx_mph5' => array(
        'AppId' => 'wxa54929ee62568723',
        'AppSecret' => '234b8630cbcc1edd472991eb7fd2f5a1'
    ),
    // 小程序登录配置  东方梅雅
    'wx_min_app' => array(
        'AppId' => 'wxf050f9ca80c58106',
        'AppSecret' => '6ed84745a79ed03da01406415f769d73'
    ),
    //小程序支付支付配置
    'mini_pay' => array(
        'appid' => 'wxf050f9ca80c58106',
        'mch_id' => '1567208801',
        'mch_key'=>'2cbe19648e4a7f4846e69413b471c01d',
        'MerchantId' => '1567208801',
        'SignType' => 'MD5',
        'Key' => '2cbe19648e4a7f4846e69413b471c01d',
        'AppSecret' => '6ed84745a79ed03da01406415f769d73',
    ),

];