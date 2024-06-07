<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/9/4 0004
 * Time: 14:29
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
use app\basic\event\UploadEvent;
use app\traits\controller\Walker;
use Hashids\Hashids;
use Rsa\Rsa;
use think\Controller;
use think\Db;


class ActivityApi extends Controller {
    use Walker;
    //实例化加密类
    protected $rsa;
    public function initialize(){
    	//header('Access-Control-Allow-Headers:actoken,Content-Type');
    	//header("Access-Control-Allow-Origin: *");
    	//halt('dsf');
        $this->rsa= new Rsa();
        //数据缓存
        $this->storageCache();
        //如果活动关闭，就提示活动关闭了
        $activity_id = input("request.activity_id");
        if($activity_id){
        	$activity_event = new ActivityEvent();
            $info = $activity_event->getActivityInfo($activity_id);
            if(empty($info)){
                //return return_data(0,"未获取到活动信息");
            }
        }
            
    }

    /**
     * 获取活动内容
     * @author ph
     * Time: 2019-09-04
     */
    public function getActivityInfo(){
        try{
        
            $activity_id = input("request.activity_id");
            if(!$activity_id) return return_data(0,"缺少参数ac");
            $activity_event = new ActivityEvent();
            $info = $activity_event->getActivityInfo($activity_id);
            if(empty($info)){
                return return_data(0,"未获取到活动信息");
            }
            //判断如果没有分享标题,内容,图片 用活动标题内容图片替换
            if(is_null($info['share_title']) === true){
                $info['share_title'] = $info['activity_title'];
            }
            if(is_null($info['share_desc']) == true){
                $info['share_desc'] = $info['activity_desc'];
            }
            if(is_null($info['share_img']) == true){
                $info['share_img'] = $info['poster_img'];
            }
            //判断如果没有投票时间就以活动的开始结束时间为准
            if(is_null($info['ticket_start_time']) == true){
                $info['ticket_start_time'] = $info['activity_start_time'];
            }
            if(is_null($info['ticket_end_time']) == true){
                $info['ticket_end_time'] = $info['activity_end_time'];
            }
            //处理活动规则和奖品介绍 如果是null处理成空字符串
            if(is_null($info['activity_rule_content']) == true){
                $info['activity_rule_content'] = "";
            }
            if(is_null($info['activity_prize_content']) == true){
                $info['activity_prize_content'] = "";
            }
            //获取该活动的总票数和总热度
            $ticket_data = Db::name("ticket_activity_player a")
                ->field("
                    sum(a.ticket_num) as sum_ticket_num,
                    sum(a.hot_num) as sum_hot_num, 
                    sum(a.gift_money) as sum_gift_money,
                    count(*) as sum_player
                ")
                ->join("ticket_player b","a.player_id = b.id")
                ->where("a.activity_id","eq",$activity_id)
                ->where("b.status","eq",1)
                ->find();
            //获取活动团队的起始票数
            $start_num = Db::name('ticket_activity_group')
            ->where('activity_id','eq',$activity_id)
            ->sum('start_num');
            $ticket_data['sum_ticket_num'] +=$start_num;
            $info = array_merge($info,$ticket_data);
            //检测时间
            $check_time_result = $activity_event->checkActivityTime($info);
            if($check_time_result['code'] != 1){
                return return_data(1,$check_time_result['msg'],$info);
            }
            return return_data(1,"获取成功",$info);
        }catch (\Exception $e){
            return return_data(0,"获取失败");
        }

    }

    /**
     * 获取活动参赛选手
     * @author ph
     * Time: 2019-09-04
     */
    public function getActivityPlayerList(){
        try{
            $activity_id = input("post.activity_id",0);
            if(!$activity_id){
                return return_data(0,"缺少参数");
            }
            $group_id = input("post.group_id",0);

            $where = [];
            //选手状态正常
            $where[] = ['c.status','eq',1];
            //对应的活动id
            $where[] = ['a.activity_id','eq',$activity_id];
            if($group_id > 0){
                $where[] = ['c.group_id','eq',$group_id];
            }
            $where_str = trim(input("post.where_str"));
            if($where_str){
                $where[] = ['c.id|c.name|b.player_num','like',"%".$where_str."%"];
            }

            //排序方式 1按票  2按礼物  3按热度
            $order = input("post.order",1);
            //排序
            $db_order = "b.ticket_num desc";
            switch($order){
                case 1:
                    //按票量排名
                    $db_order = "b.ticket_num desc";
                    break;
                case 2:
                    //按礼物排名
                    $db_order = "b.gift_money desc";
                    break;
                case 3:
                    //按热度排名
                    $db_order = "b.hot_num desc";
                    break;
                default:
                    //默认按票量排名
                    $db_order = "b.ticket_num desc";
                    break;
            }
            /**判断活动是否开始 add by yan Date:2019/12/18**/
            if(Db('ticket_activity')->where('activity_id',$activity_id)->value('activity_start_time') > time()){
                $db_order = 'b.player_num';
            }
            /**判断活动是否关闭 end**/

            //当前页
            $page = input("post.page",1);
            //每页显示的条数
            $page_size = input("post.page_size",20);
            //计算偏移量
            $limit = ( $page - 1 ) * $page_size;
            //db查询
            $list = Db::name("ticket_activity a")
                ->field("
                c.id as player_id,
                c.name as player_name,
                case when sex = 1 then '男' else '女' end as player_sex,
                c.mobile as player_mobile,
                c.head_img as player_head_img,
                c.person_img as player_person_img,
                c.declaration as player_declaration,
                c.desc as player_desc,
                c.small_video,
                b.ticket_num,
                b.gift_money,
                b.hot_num,
                d.group_name,
                b.player_num,
                c.pro_name,
                c.company_name
            ")
                ->where($where)
                ->join("ticket_activity_player b","a.activity_id = b.activity_id")
                ->join("ticket_player c","b.player_id = c.id")
                ->join('ticket_activity_group d','c.group_id = d.id','LEFT')
                ->order($db_order)
                ->limit($limit.",".$page_size)
                ->select();

            //显示缩略图 add by yan Date:2019/12/18
            foreach ($list as &$v)
            {
                $v['thumb_img'] =  $v['player_person_img'] . '?x-oss-process=image/resize,m_fill,h_372,w_296';
            }
            //显示缩略 end

            return return_data(1,"获取成功",$list);
        }catch (\Exception $e){
            return return_data(0,"获取失败");
        }
    }

    /**
     * 获取选手详情
     * @author ph
     * Time: 2019-09-07
     */
    public function getPlayerInfo(){
        //$activity_id = input("post.activity_id",0);
        $player_id = input("post.player_id");
        if(!$player_id){
            return return_data(0,"缺少参数。，");
        }
        $activity_id = Db('ticket_activity_player')->where('player_id',$player_id)->value('activity_id');
        $where = [];
        if($activity_id > 0){
            $where[] = ['b.activity_id','eq',$activity_id];
        }
        $where[] = ['b.player_id','eq',$player_id];
        $player_info = Db::name("ticket_player a")
            ->field("
                a.id as player_id,
                a.name as player_name,
                case when sex = 1 then '男' else '女' end as player_sex,
                a.mobile as player_mobile,
                a.head_img as player_head_img,
                a.person_img as player_person_img,
                a.declaration as player_declaration,
                a.desc as player_desc,
                a.pro_name,
                a.company_name,
                b.ticket_num,
                b.gift_money,
                b.hot_num,
                c.group_name,
                b.activity_id,
                b.player_num,
            	a.small_video
            ")
            ->join("ticket_activity_player b","a.id = b.player_id","left")
            ->join('ticket_activity_group c','a.group_id = c.id','left')
            ->where($where)
            ->find();
        if(empty($player_info)){
            return return_data(0,"未查询到选手信息");
        }
        //获取选手当前排名
        $activity_event = new ActivityEvent();
        $rankign_info = $activity_event->getPlayerRanking($activity_id,$player_id);
        $player_info['row_num'] = $rankign_info['row_num'];
        return return_data(1,"获取成功",$player_info);

    }

    /**
     * 给选手投票
     * @author ph
     * Time: 2019-09-05
     * 数据做加密处理  2020-12-08
     */
    public function clickPlayerTicket(){
    
        try{
            $request = request();
			
            //用户唯一标识
            $header_uid = $request->header()['actoken'];
    	
            if(!$header_uid){
                return return_data(0,"请求有误");
            }
            
            
            $hs = new Hashids('ticket', 10);
            //$uid = $hs->decode($header_uid)[0];
            $uid = $header_uid;
            /*
            $user_info = Db::name("ticket_user")->where("id","eq",$uid)->find();
            if(empty($user_info)){
                return return_data(0,"用户不存在");
            }*/

            //解密数据
            $pdata = input("post.esign");

            $pdata = $this->rsa->privDecrypt($pdata);

            $parr = explode('|',$pdata);
            
    
            //判断非法请求
            if($header_uid!=$parr[1]){
                //加密数据和header 数据不一致
                return return_data(0,"非法投票会列入黑名单");
            }
            //活动id
            $activity_id = $parr[0];
            if(!$activity_id){
                return return_data(0,"缺少活动参数");
            }

            //选手id
            $player_id = $parr[2];
            if(!$player_id){
                return return_data(0,"缺少活动参数");
            }

            $activity_event = new ActivityEvent();

            $player_info = $activity_event->getPlayerInfo($player_id);
            if(empty($player_info)){
                return return_data(0,'未获取到选手信息');
            }
            if(!isset($player_info['activity_id'])){
                return return_data(0,'参数错误');
            }
            //接口传回的活动id不可信   通过选手活动关联表重新获取活动id
            $activity_id = $player_info['activity_id'];



            $activity_info = $activity_event->getActivityInfo($activity_id);
            if(empty($activity_info)){
                return return_data(0,"未获取到活动信息");
            }
            //检测时间
            $check_time_result = $activity_event->checkActivityTime($activity_info);
            if($check_time_result['code'] != 1){
                return return_data(0,$check_time_result['msg']);
            }

            //当天日期
            $now_date = date("Y-m-d");
            $num = $parr[3];
            if($num > $activity_info['person_day_user_num']){
                return return_data(0,'最多可投'.$activity_info['person_day_user_num']."票");
            }

            if($num > $activity_info['max_num']){
                return return_data(0,'选手免费得票数已达上限');
            }



	        //判断选手当天免费得票数
            $today=strtotime(date("Y-m-d"),time());
                $player_daycount = DB::table('ticket_player_record')->where([['create_time','>=',$today],['player_id','=',$player_id]])->sum('num');
                if($player_daycount > $activity_info['max_num']){
                    return return_data(0,"她的免费票已超限请选择其他礼物");
                }
                
            //系统封号
            if($player_id ==205){
              //return return_data(0,"投票失败。");
            }

            //redis hash 名
            $redis_name = "user_ticket_".$now_date."_".$player_id."_".$uid;
            //$redis_name = "user_ticket_".$now_date."_".$player_id."_".$uid;
            //$redis_name = "user_ticket_".$now_date."_".$uid;

            //每人每日可投票数
            $person_day_num = $activity_info['person_day_num'];

            //每人每天可给每个选手投的票数
            $person_day_user_num = $activity_info['person_day_user_num'];

            //最新投票规则 只判断每人每日投票数 add by yan 2019/12/26
            if(0)
            {
                //用户当天已投票量
                $day_user_sum = array_sum((array)S()->HVALS($redis_name));

                //判断当天已投是否已超过当天可投
                if($day_user_sum >= $person_day_num){
                    return return_data(0,"您今日免费投票已达上限！");
                }

            }else{
                //用户当天已投票量
                $day_user_sum = array_sum((array)S()->HVALS($redis_name));
                //剩余
                $surplus_num = $activity_info['person_day_user_num'] - $day_user_sum;
                if($surplus_num == 0){
                    return return_data(0,"^您今日已给她投过票啦！");
                }
                if($num > $surplus_num){
                    return return_data(0,'您今日还可给她投'.$surplus_num.'票哦');
                }

                //判断当天已投是否已超过当天可投

                if($day_user_sum >= $person_day_user_num){
                    return return_data(0,"~您今日已给她投过票啦！");
                }

                //判断今日给某选手投票是否已达上限
                if(S()->hget($redis_name,$player_id) >= $person_day_user_num){
                    return return_data(0,"今日给此选手投票数量已达上限");
                }
            }
            Db::startTrans();
            //先增加投票记录
            $data = [];
            $data['activity_id'] = $activity_id;
            $data['player_id'] = $player_id;
            $data['uid'] = $uid;
            $data['num'] = $num;
            $data['create_time'] = time();
            $data['ip_address'] = get_ip();
            $amap_event = new MapEvent();
            $ip_result = $amap_event->ipToAddress($data['ip_address']);
            $data['province'] = $ip_result['data']['province'];
            $data['city'] = $ip_result['data']['city'];
            $record = Db::name("ticket_player_record")
                ->insert($data);
            if(!$record){
                Db::rollback();
                return return_data(0,"投票失败");
            }
            //再给用户加票
            $re = Db::name("ticket_activity_player")
                ->where("activity_id","eq",$activity_id)
                ->where("player_id","eq",$player_id)
                ->setInc("ticket_num",$num);
            if($re === false){
                Db::rollback();
                return return_data(0,"投票失败");
            }
            Db::commit();
            //记录用户投票记录
            S()->HINCRBY($redis_name,$player_id,$num);
            //记录选手得票数
            //S()->HINCRBY($costfree_name,$player_id,$num);
            //socket 通知   XXX获得X票支持
            /**
            $content = $player_info['name']. '获得' . $num .'票支持';
            if($num>=8000){
                $this->sendToAll($content,'api',$activity_id,8000);
            }else{
                $this->sendToAll($content,'api',$activity_id);
            }


            //socket 通知大屏展示
            $re = json_decode($this->getPlayerSort($activity_id),true);
            if($re['code'] == 1)
            {
                $this->sendToAll($re['data'],'bigscreen',$activity_id);
            }else{
                return return_data(0,"投票失败",$re['msg']);
            }
*/
            return return_data(1,"投票成功",['addnum'=>$num]);
        }catch (\Exception $e){
            return return_data(0,"投票失败",$e->getMessage());
        }
    }

    private function getPlayerSort($activity_id,$group_id=0)
    {
        try{
            $where = [];
            //选手状态正常
            $where[] = ['c.status','eq',1];
            //对应的活动id
            $where[] = ['a.activity_id','eq',$activity_id];
            if($group_id > 0){
                $where[] = ['c.group_id','eq',$group_id];
            }

            //排序
            $db_order = "b.ticket_num desc";
            /**判断活动是否开始 add by yan Date:2019/12/18**/
            if(Db('ticket_activity')->where('activity_id',$activity_id)->value('activity_start_time') > time()){
                $db_order = 'b.player_num';
            }
            /**判断活动是否关闭 end**/

            //db查询
            $list = Db::name("ticket_activity a")
                ->field("
                c.id as player_id,
                c.name as player_name,
                case when sex = 1 then '男' else '女' end as player_sex,
                c.mobile as player_mobile,
                c.head_img as player_head_img,
                c.person_img as player_person_img,
                c.declaration as player_declaration,
                c.desc as player_desc,
                c.small_video,
                b.ticket_num,
                b.gift_money,
                b.hot_num,
                d.group_name,
                b.player_num,
                c.pro_name,
                c.company_name
            ")
                ->where($where)
                ->join("ticket_activity_player b","a.activity_id = b.activity_id")
                ->join("ticket_player c","b.player_id = c.id")
                ->join('ticket_activity_group d','c.group_id = d.id','LEFT')
                ->order($db_order)
                ->limit(20)
                ->select();

            //显示缩略图 add by yan Date:2019/12/18
            foreach ($list as &$v)
            {
                $v['thumb_img'] =  $v['player_person_img'] . '?x-oss-process=image/resize,m_fill,h_372,w_296';
            }
            //显示缩略 end

            return json_encode(array('code'=>1,'data'=>$list));
        }catch (\Exception $e){
            return json_encode(array('code'=>0,'msg'=>$e->getMessage()));
        }
    }

    /**
     * 获取礼物列表
     * @author ph
     * Time: 2019-09-06
     */
    public function getGiftList(){
        $activity_id = input("post.activity_id");
        if(!$activity_id){
            return return_data(0,"获取失败");
        }
        $activity_event = new ActivityEvent();
        $list = $activity_event->getActivityGiftList($activity_id);

        if(empty($list)){
            return return_data(0,"获取失败");
        }
        return return_data(1,"获取成功",$list);
    }

    /**
     * 数据缓存
     * @author ph
     * Time: 2019-09-06
     */
    public function storageCache(){
        //缓存活动信息
        $activity_event = new ActivityEvent();
        $list = Db::name("ticket_activity")->column("activity_id");
        foreach($list as $k => $activity_id){
            $activity_event->getActivityInfo($activity_id);
        }
    }

    /**
     * 创建用户
     * @author ph
     * Time: 2019-09-25
     */
    public function createUser(){
        $code = input("post.code");
        if(!$code){
            return return_data(0,"缺少参数");
        }
        //获取openid
        $url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxa54929ee62568723&secret=0aac8b9e80afd13335eff5e2ba9aeceb&code=$code&grant_type=authorization_code";
        $result= file_get_contents($url);
        $jsoninfo= json_decode($result, true);
        
        if(isset($jsoninfo['errcode'])){
        	
            //return return_data(0,$jsoninfo['errmsg']);
            return return_data(0,'请重新打开网页');
        }
        $openid= $jsoninfo["openid"];//从返回json结果中读出openid

        //查询用户是否存在
        $uid = Db::name("ticket_user")->where("openid","eq",$openid)->value("id");
        if(!$uid){
            //存储用户信息
            $user_data = [];
            $user_data['openid'] = $jsoninfo['openid'];
            $user_data['nickname'] = isset($jsoninfo['fansinfo']['nickname']) ? $jsoninfo['fansinfo']['nickname'] : '';
            $user_data['sex'] = isset($jsoninfo['fansinfo']['sex']) ? $jsoninfo['fansinfo']['sex']:'';
            $user_data['country'] = isset($jsoninfo['fansinfo']['country']) ? $jsoninfo['fansinfo']['country'] : '';
            $user_data['province'] = isset($jsoninfo['fansinfo']['province']) ? $jsoninfo['fansinfo']['province'] : '';
            $user_data['city'] = isset($jsoninfo['fansinfo']['city']) ? $jsoninfo['fansinfo']['city'] : '';
            $user_data['headimgurl'] = isset($jsoninfo['fansinfo']['headimgurl']) ? $jsoninfo['fansinfo']['headimgurl'] : '';
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
     * 获取活动信息
     * @author ph
     * Time: 2019-10-09
     */
    protected function getActivityContent($activity_id,$filed){
    	
        return Db::name("ticket_activity")
            ->where("activity_id","eq",$activity_id)
            ->value($filed);
    }

    /**
     * 获取大赛详情
     * @author ph
     * Time: 2019-10-09
     */
    public function getActivityDetail(){
        if(!input("post.activity_id")){
            return return_data(0,"缺少参数");
        }
        $info = $this->getActivityContent(input("post.activity_id"),"activity_rule_content");
        $info = isset($info) ? $info : "";
        return return_data(1,"获取成功",$info);
    }

    /**
     * 获取鸣谢单位
     * @author ph
     * Time: 2019-10-09
     */
    public function getActivityCompany(){
        if(!input("post.activity_id")){
            return return_data(0,"缺少参数");
        }
        $info = $this->getActivityContent(input("post.activity_id"),"cooperative_company");
       //halt(Db::getLastSql());
        $info = isset($info) ? $info : "";
        return return_data(1,"获取成功",$info);
    }

    /**
     * 获取活动下分组
     * User: phao345
     * Date: 2019/11/29
     */
   public function getActivityGroup(){
        $activity_id = input('post.activity_id');
        if(!$activity_id){
            return return_data(0,'缺少参数');
        }
        $group_list = Db::name('ticket_activity_group')
            ->field("id as group_id,group_name,img")
            ->where('activity_id','eq',$activity_id)
            ->where('status','eq',1)
            ->select();
        $activity_event = new ActivityEvent();
        foreach($group_list as $k=>$v){
            $group_list[$k]['ticket_num'] = $activity_event->getActicityGroupTicketNum($v['group_id']);
            $group_list[$k]['player_num'] = $activity_event->getActicityGroupPlayerNum($v['group_id']);
            if( $group_list[$k]['img'] == ''){$group_list[$k]['img'] = 'https://ticket-online.oss-cn-hangzhou.aliyuncs.com/b8697ab2b97ecd2a/268c37cf2b4f467b.jpg';}
        }
        if(empty($group_list)){
            return return_data(0,'暂无分组信息');
        }

        /**按得票数量倒叙分组 add by yan  Date:2019/12/18**/
        $key_array = array_column($group_list,'ticket_num');
        array_multisort($key_array,SORT_DESC,$group_list);
        /**按得票数量倒叙分组  end**/

        return return_data(1,'获取成功',$group_list);
    }

    /**
     * 获取活动分组下选手列表
     * User: phao345
     * Date: 2019/11/29
     */
    public function getActivityPlayerGroupList(){
        try{
            $activity_id = input("post.activity_id",0);
            if(!$activity_id){
                return return_data(0,"缺少参数");
            }
            $group_id = input("post.group_id");
            if(!$group_id){
                return return_data(0,'缺少分组');
            }

            $where = [];
            //选手状态正常
            $where[] = ['c.status','eq',1];
            //对应的活动id
            $where[] = ['a.activity_id','eq',$activity_id];
            $where[] = ['c.group_id','eq',$group_id];
            $where_str = trim(input("post.where_str"));
            if($where_str){
                $where[] = ['c.id|c.name','like',"%".$where_str."%"];
            }
            //排序方式 1按票  2按礼物  3按热度
            $order = input("post.order",1);
            //排序
            $db_order = "b.ticket_num desc";
            switch($order){
                case 1:
                    //按票量排名
                    $db_order = "b.ticket_num desc";
                    break;
                case 2:
                    //按礼物排名
                    $db_order = "b.gift_money desc";
                    break;
                case 3:
                    //按热度排名
                    $db_order = "b.hot_num desc";
                    break;
                default:
                    //默认按票量排名
                    $db_order = "b.ticket_num desc";
                    break;
            }
            //当前页
            //$page = input("post.page",1);
            //每页显示的条数
            //$page_size = input("post.page_size",10);
            //计算偏移量
            //$limit = ( $page - 1 ) * $page_size;
            //db查询
            $list = Db::name("ticket_activity a")
                ->field("
                c.id as player_id,
                c.name as player_name,
                case when sex = 1 then '男' else '女' end as player_sex,
                c.mobile as player_mobile,
                c.head_img as player_head_img,
                c.person_img as player_person_img,
                c.declaration as player_declaration,
                c.desc as player_desc,
                c.small_video,
                b.ticket_num,
                b.gift_money,
                b.hot_num,
                d.group_name
            ")
                ->where($where)
                ->join("ticket_activity_player b","a.activity_id = b.activity_id")
                ->join("ticket_player c","b.player_id = c.id")
                ->join('ticket_activity_group d','c.group_id = d.id')
                ->order($db_order)
                //->limit($limit.",".$page_size)
                ->select();
            return return_data(1,"获取成功",$list);
        }catch (\Exception $e){
            return return_data(0,"获取失败");
        }
    }

    /**
     * 获取活动列表
     * User: phao345
     * Date: 2019/12/6
     */
    public function getActivityList(){
    	//*//
        $activity_list = Db::name('ticket_activity a')
            ->field("
                a.activity_id,
                a.activity_title,
                a.activity_start_time,
                a.activity_end_time,
                a.ticket_start_time,
                a.ticket_end_time,
                a.poster_img,
                a.is_group,
                a.status,
                count(*) as sum_player
            ")
            ->join('ticket_activity_player b','a.activity_id = b.activity_id')
            ->join('ticket_player c','b.player_id = c.id')
            ->where('a.status','eq',1)
            ->where('a.activity_id','eq',4)
            ->where('c.status','eq',1)
            ->group('a.activity_id')
            ->select();
		//halt($activity_list);
        $activity_event = new ActivityEvent();
        foreach($activity_list as $k=>&$v){
            $v['activity_start_time'] = date('Y-m-d H:i:s',$v['activity_start_time']);
            $v['activity_end_time'] = date('Y-m-d H:i:s',$v['activity_end_time']);
            $v['ticket_start_time'] = date('Y-m-d H:i:s',$v['ticket_start_time']);
            $v['ticket_end_time'] = date('Y-m-d H:i:s',$v['ticket_end_time']);
            $v['banner_img'] = $activity_event->getActivityBanner($v['activity_id']);
        }
        /*/
        //隐藏活动列表
        $activity_list=[];
        //*/
        return return_data(1,'获取成功',$activity_list);
    }

    /**
     * 获取活动下的banner图
     * User: phao345
     * Date: 2019/12/6
     */
   public function getActivityBannerList(){
        $activity_id = input("post.activity_id");
        if(!$activity_id){
            return return_data(0,'缺少参数');
        }
//        $img_list = [
//            "http://ticket-online.oss-cn-hangzhou.aliyuncs.com/97bd9f776fb007f1/674e0f05cadb0ead.jpg",
//            "http://ticket-online.oss-cn-hangzhou.aliyuncs.com/b45b34c18f04213a/aac05bdd8cb66185.jpg"
//        ];
//
//        $video_list = [
//            [
//                'index_img' =>  "https://ticket-onlines.oss-cn-beijing.aliyuncs.com/WechatIMG641.jpeg",
//                'video_url' => "https://ticket-onlines.oss-cn-beijing.aliyuncs.com/1575944061041078.mp4"
//            ]
//        ];
//
//        $return = [];
//        $return['type'] = 2;
//        $return['list'] = $video_list;

        $activity_event = new ActivityEvent();
        $list = $activity_event->getActivityBanner($activity_id);

        //返回banner缩略图 add by yan Date:2019/12/18
        foreach ($list as &$v)
        {
        	!isset($v['banner_img']) && $v['banner_img'] = '';
            $v['thumb_img'] = $v['banner_img'] != '' ?$v['banner_img'] . '?x-oss-process=image/resize,m_fill,h_360,w_608' :'';
        }
        //返回banner缩略图 end

        return return_data(1,'获取成功',$list);
    }

    public function imgUpload(){
        try{
            $upload_event = new UploadEvent();
            $result = $upload_event->plupload();
            $result_data = $result->getData();

            if(!isset($result_data['url'])){
                return return_data(0,"上传失败,请稍后再试",['error'=>json_encode($result_data)]);
            }
            $return['img_url'] = $result_data['url'];

            return return_data(1,"上传成功",$return);
        }catch (\Exception $e){
            return return_data(0,"上传失败",['error'=>getError($e)]);
        }
    }



	//生成语音播报感谢语
public function audioCreate()
{
    return return_data(1,"获取成功", ['mp3' => 'https://ticket-online.oss-cn-hangzhou.aliyuncs.com/c48afbd9a4abc5dc/1e1b295c300e5036.mp3']);
}

    //删除语音播报文件
    public function audioDelete()
    {
        $name = $this->request->post('audio');
        $file = ROOT_PATH . 'public' . $name;
        if(file_exists($file)){
            @unlink($file);
            return return_data(1,"删除成功");
        }
        return return_data(0,"删除失败");
    }



    //测试方法
    public function testa(){

        $gift_info['activity_id'] =1;
        $order_info=['uid'=>1,'id'=>3787,'num'=>2];
        /************正式开始***********/
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
            echo $anum.'<br>';
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

                    array_push($gift_pro,$gift_arr);
                    //加票累计
                    $ticketnum += $gift_arr["gift_ticket_num"];
                    break;
                }
            }
        }
        //写入获奖数据
        Db::name("ticket_order_gift_pro")->insertAll($gift_pro);
        halt($gift_pro);
    }



 public function ttt(){
       /*
       $today=strtotime(date("Y-m-d"),time());
       $player_daycount = DB::table('ticket_player_record')->where([['create_time','>=',$today],['player_id','=',205]])->sum('num');
       halt($player_daycount);
       */
        //当天日期
        $now_date = date("Y-m-d");

        $player_id = 218;
        $uid = 11801;
        //redis hash 名
        $redis_name = "user_ticket_".$now_date."_".$player_id."_".$uid;
        $redis_name = "activity_info_1";
        echo $redis_name;
        
        halt(S()->get($redis_name));
        $day_user_sum = S()->HVALS($redis_name);
        var_dump(S()->TTL($redis_name));
        halt($day_user_sum);


    }









}
