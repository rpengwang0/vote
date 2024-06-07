<?php


namespace app\ticket\controller;

use app\basic\event\ActivityEvent;
use app\basic\event\UploadEvent;
use Hashids\Hashids;
use think\Controller;
use think\Db;
use think\facade\Log;
use think\facade\Request;

class Wechat extends Controller{

    /**
     * getMinToken 获取小程序access_token
     * User: phao345
     * Date: 2019/12/6
     */
    public function getMinToken(){
        $file = S()->get('mini_access_token');
        if($file){
            $data = [];
            $data['access_token'] = $file;
            return resultInfo(1,'获取成功',$data);
        }
        $wxMin = config('wechat.wx_min_app');
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$wxMin['AppId'].'&secret='.$wxMin['AppSecret'];
        $datas = json_decode(requestUrl($url),true);
        if (!isset($datas['access_token']) || !$datas['access_token']) {
            return resultInfo(0,'access_token获取失败');
        }
        S()->set('mini_access_token',$datas['access_token'],7000);
        return resultInfo(1,'获取成功',['token'=>$datas['access_token']]);
    }
    
    /**
     * 微信公众号登录
     * User: rpengwang
     * Date: 2022
     */
    public function mph5login(){

        $code = input("post.code");
        $wxMp = config('wechat.wx_mph5');
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$wxMp['AppId'].'&secret='.$wxMp['AppSecret'].'&code='.$code.'&grant_type=authorization_code';
        
        $result_data = json_decode(requestUrl($url),true);
    	
        if(!isset($result_data['openid'])){
        
        	 return return_data(0,'code登录失败'.json_encode($result_data));
        }
        //查询用户是否存在   不存在添加
        $user_info = Db::name("ticket_user")
            ->where('openid','eq',$result_data['openid'])
            ->where('type','eq',1)
            ->find();
        $uid = 0;
       
        if(empty($user_info)){
            $insert_data = [];
            $insert_data['openid'] = $result_data['openid'];
       
            $insert_data['type'] = 1;
            $insert_data['create_time'] = time();
            $uid = Db::name("ticket_user")->insertGetId($insert_data);
            if(!$uid){
                return return_data(0,'登录失败');
            }
        }else{
            $uid = $user_info['id'];
        }
        $hs = new Hashids('ticket', 10);
        $uid = $hs->encode($uid);

        return return_data(1,'登录成功',['uid'=>$uid]);
        
    }
    
    /**
     * APP登录
     * User: rpengwang
     * Date: 2022
     */
    public function wechatAppLogin(){

        $phone = input("post.phone");
 
        //查询用户是否存在   不存在添加
        $user_info = Db::name("ticket_user")
            ->where('openid','eq',$phone)
            ->where('type','eq',1)
            ->find();
        $uid = 0;
       
        if(empty($user_info)){
            $insert_data = [];
            $insert_data['openid'] = $phone;
       
            $insert_data['type'] = 1;
            $insert_data['create_time'] = time();
            $uid = Db::name("ticket_user")->insertGetId($insert_data);
            if(!$uid){
                return return_data(0,'登录失败');
            }
        }else{
            $uid = $user_info['id'];
        }
        $hs = new Hashids('ticket', 10);
        $uid = $hs->encode($uid);

        return return_data(1,'登录成功',['uid'=>$uid]);
        
    }
    

    /**
     * [getMinOpenid 获取小程序openid]
     * User: phao345
     * Date: 2019/12/6
     * @param $code
     * @return mixed|void
     */
    public function getMinOpenid($code)
    {
        if(empty($code)){return resultInfo(0,'code不可为空');}
        $wxMin = config('wechat.wx_min_app');
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxMin['AppId'].'&secret='.$wxMin['AppSecret'].'&js_code='.$code.'&grant_type=authorization_code';
        $data = json_decode(requestUrl($url),true);

        if (!isset($data['openid']) || !$data['openid']) {
            return resultInfo(0,'openid获取失败');
        }
        if (strlen($data['session_key']) != 24) {
            return resultInfo(0,'sessionKey获取失败');
        }
        return resultInfo(1,'获取成功',$data);
    }

    /**
     * getMinOpenid 小程序登录
     * User: phao345
     * Date: 2019/12/6
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function wechatMinLogin()
    {
        $posts = input('post.');
        if(!isset($posts['code']) || !isset($posts['avatarUrl']) || !isset($posts['nickName']) || !isset($posts['gender']) || !isset($posts['province']) || !isset($posts['city'])){
            return return_data(0,'缺少参数');
        }

        //获取openid和session_key
        $result_data = $this->getMinOpenid($posts['code']);
        if($result_data['code'] != 1){
            return return_data(0,$result_data['msg']);
        }
        //查询用户是否存在   不存在添加
        $user_info = Db::name("ticket_user")
            ->where('openid','eq',$result_data['data']['openid'])
            ->where('type','eq',2)
            ->find();
        $uid = 0;
        if(empty($user_info)){
            $insert_data = [];
            $insert_data['openid'] = $result_data['data']['openid'];
            $insert_data['nickname'] = $posts['nickName'];
            $insert_data['headimgurl'] = $posts['avatarUrl'];
            $insert_data['sex'] = $posts['gender'];
            $insert_data['country'] = $posts['country'];
            $insert_data['province'] = $posts['province'];
            $insert_data['city'] = $posts['city'];
            $insert_data['type'] = 2;
            $insert_data['create_time'] = time();
            $uid = Db::name("ticket_user")->insertGetId($insert_data);
            if(!$uid){
                return return_data(0,'登录失败');
            }
        }else{
            $uid = $user_info['id'];
        }
        $hs = new Hashids('ticket', 10);
        $uid = $hs->encode($uid);

        return return_data(1,'登录成功',['uid'=>$uid]);
    }

    /**
     * 生成小程序二维码
     * User: phao345
     * Date: 2019/12/7
     * @param $player_id
     * @return bool|string
     */
    public function getMiniQrCode($player_id,$home_url){
        $token_result = $this->getMinToken();
        if ($token_result['code'] != 1) {
            return false;
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token='.$token_result['data']['access_token'];
         if($home_url)
        {
            $dataObj = [
                'width' =>  20,
                'path' => 'pages/introduce/introduce?player_id=' . $player_id .'&back=' . $home_url,
            ];

        }else{
            $dataObj = [
                'width' =>  20,
                'path' => 'pages/introduce/introduce?player_id=' . $player_id,
            ];
        }
        $data = json_encode($dataObj);
        $result = requestUrl($url, $data, ["Content-Type: application/json"]);
        file_put_contents('createwxaqrcode.log',date('Y-m-d H:i:s').PHP_EOL.$result.PHP_EOL.PHP_EOL,FILE_APPEND);
        if (empty($result)) {
            return false;
        } else {
            $json = json_decode($result, true);
            if (!empty($json)) {
                return false;
            }
        }
        $img_name = "player_".$player_id.'.png';
        $data = file_put_contents(env('root_path') . '/public/upload/minicode/'.$img_name,$result);
        if($data>0 && is_int($data)){
            $urls = '';
            //$urls .= Request::domain();
            $urls .= './upload/minicode/'.$img_name;
            //上传oss
            $upload_event = new UploadEvent();
            $oss_data = [];
            $oss_data['file_name'] = "player" . "_" . $player_id . ".png";
            $oss_data['url'] = $urls;
            $resimg = $upload_event->ossUploadFile($oss_data, 'player_qr');
            return $resimg;
        }else{
            return false;
        }
    }

    /**
     * 获取选手小程序二维码
     * User: phao345
     * Date: 2019/12/7
     */
    public function getPlayerQrCode(){
        try{
            $player_id = input("post.player_id");
            if(!$player_id){
                return return_data(0,"缺少参数");
            }
              //拼接参数 add by yan 2019/12/26
            $home_url = input("post.home_url");
            //获取选手信息
            $player_info = Db::name("ticket_player")->field("id,person_img,mini_qr_url")->where('id','eq',$player_id)->find();
            if(empty($player_info)){
                return return_data(0,'获取选手信息失败');
            }
            if($player_info['mini_qr_url'] != ''){
                return return_data(1,'生成小程序二维码成功',['img_url'=>$player_info['mini_qr_url']]);
            }else{
                $result = $this->getMiniQrCode($player_id,$home_url);
                if($result === false){
                    return return_data(0,'生成小程序二维码失败');
                }
                //存入数据库
                $re = Db::name('ticket_player')->where('id','eq',$player_id)->update([
                    'mini_qr_url'=>$result
                ]);
                if($re === false){
                    return return_data(0,'存储选手二维码失败');
                }
                return return_data(1,'生成小程序二维码成功',['img_url'=>$result]);
            }
        }catch (\Exception $e){
            return return_data(0,'生成失败');
        }
    }

    public function testimg(){

        $img1 = "http://ticket-online.oss-cn-hangzhou.aliyuncs.com/3e70c07fe96fe1bf/533448302a168740.jpg";
        $img2 = "http://www.meilidongli.com/static/img/beijing/player_178.png";
        $bai_img = "http://www.meilidongli.com/static/img/beijing/baibai.png";
        $event = new UploadEvent();
        //图片二维码合并
        $merge_url = $event->mergeImg($img1,$img2,1);
        if(!isset($merge_url)){
            return return_data(0,'生成失败');
        }
        //加文字
        $text = "闫秋";
        $add_font_url = $event->imgAddFont($merge_url,
            [
                [
                    'text'=>$text,
                    'size'=>20,
                    'angle'=>0,
                    'x'=>30,
                    'y'=>120,
                    'color'=>[255,255,255,0]
                ]
            ]
        );
        if(!isset($add_font_url)){
            return return_data(0,'生成失败');
        }
        //加白色背景
        $bai_img = $event->mergeImg($bai_img,$add_font_url,2);

        //加顶部的一段话
        $name = "闫秋";
        $content = "我是闫秋。在参加xxx。扫码来为我加油呀！";
        $aaa = $event->imgAddFont($bai_img,
            [
                [
                    'text'=>$name,
                    'size'=>20,
                    'angle'=>0,
                    'x'=>150,
                    'y'=>50,
                    'color'=>[0,0,0,0]
                ],
                [
                    'text'=>$content,
                    'size'=>10,
                    'angle'=>0,
                    'x'=>30,
                    'y'=>70,
                    'color'=>[0,0,0,0]
                ]
            ]
        );
        dd($aaa);die;


    }

    /**
     * 获取邀请图
     * User: phao345
     * Date: 2019/12/9
     */
    public function getInviteImg(){

        $player_id = input("post.player_id");
        if(!$player_id){
            return return_data(0,'缺少参数');
        }
        $activity_id = input("post.activity_id");
        if(!$activity_id){
            return return_data(0,'缺少参数');
        }
        $cache_name = "mini_share_url_".$player_id;
        if(S()->get($cache_name)){
            return return_data(1,'生成成功',['img_url'=>S()->get($cache_name)]);
        }

        //选手姓名
        $player_info = Db::name("ticket_player")
            ->field("name,person_img")
            ->where('id','eq',$player_id)
            ->find();
        if(!isset($player_info['person_img'])){
            return return_data(0,'选手没有封面图');
        }
        //活动名称
        $activity_title = Db::name("ticket_activity")
            ->where("activity_id",'eq',$activity_id)
            ->value("activity_title");
        //获取选手小程序二维码
        $mini_result = $this->getMiniQrCode($player_id);
        if($mini_result == false){
            return return_data(0,"生成小程序二维码失败");
        }
        //小程序码
        $mini_code_url = $mini_result;
        //白色背景
        $bai_img = env("root_path") . "/public/static/img/beijing/baibai.png";
        //选手封面图
        $person_img = $player_info['person_img'];

        //合成后图片存放位置
        $img_name = "share_".$player_id.".jpg";
        $img_path = env('root_path') . '/public/upload/shareimg/share_' . $player_id . '.jpg';

        //开始合成
        $event = new UploadEvent();

        //封面图与二维码合并
        $merge_url = $event->mergeImg($person_img,$mini_result,1,$img_path);
        if(!isset($merge_url)){
            return return_data(0,'生成失败');
        }
        //加图上的名字
        $add_font_url = $event->imgAddFont($merge_url,
            [
                [
                    'text'=>$player_info['name'],
                    'size'=>20,
                    'angle'=>0,
                    'x'=>30,
                    'y'=>120,
                    'color'=>[255,255,255,0]
                ]
            ]
        );
        if(!isset($add_font_url)){
            return return_data(0,'生成失败');
        }
        //加白色背景
        $bai_img = $event->mergeImg($bai_img,$add_font_url,2,$img_path);
        if(!isset($bai_img)){
            return return_data(0,'生成失败');
        }
        //加顶部的一段话
        $name = $player_info['name'];
        $content = "我是".$player_info['name']."。在参加".$activity_title."。扫码来为我加油呀！";
        $url = $event->imgAddFont($bai_img,
            [
                [
                    'text'=>$player_info['name'],
                    'size'=>20,
                    'angle'=>0,
                    'x'=>150,
                    'y'=>50,
                    'color'=>[0,0,0,0]
                ],
                [
                    'text'=>$content,
                    'size'=>8,
                    'angle'=>0,
                    'x'=>30,
                    'y'=>70,
                    'color'=>[0,0,0,0]
                ]
            ]
        );
        if(!isset($url)){
            return return_data(0,'生成失败');
        }

        $up_data['url'] = $url;
        $up_data['file_name'] = $img_name;
        $oss_url = $event->ossUploadFile($up_data,'player_share_img');
        //存入选手表
        $save_re = Db::name("ticket_player")
            ->where('id','eq',$player_id)
            ->update(['mini_share_url'=>$oss_url]);
        if($save_re === false){
            return return_data(0,'存储失败');
        }
        //存入缓存
        S()->set($cache_name,$oss_url,60*60*24);

        return return_data(1,'生成成功',['img_url'=>$oss_url]);


    }
}