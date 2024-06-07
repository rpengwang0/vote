<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/9/7 0007
 * Time: 11:42
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

namespace app\basic\event;


use think\Controller;

class MapEvent extends Controller{

    protected $amap_key = "aa48f897e49177d22ff73b84e9ac2b32";

    /**
     * 调用高德Api  将ip转为省市县的地址
     * @author ph
     * Time: 2019-09-07
     */
    public function ipToAddress($ip){
        $province = "";//省
        $city = "";//市

        $url  = "http://restapi.amap.com/v3/ip?ip=".$ip."&output=json&key=".$this->amap_key;
//        $url  = "https://restapi.amap.com/v3/geocode/regeo?output=json&";
//        $url .= "location=".$ip;
//        $url .= "&key=".$this->amap_key."&";
//        $url .= "radius=100&extensions=base";
        $result = json_decode(file_get_contents($url),true);
        if($result['status'] == 1){
            $province = $result['province'];
            $city = $result['city'];
        }
        $return['province'] = $province;
        $return['city'] = $city;
        return resultInfo(1,"成功",$return);
    }
}