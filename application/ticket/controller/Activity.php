<?php
/**
 * 活动管理
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/8/21 0021
 * Time: 13:44
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
use library\Controller;
use think\Db;

class Activity extends Controller{

    /**
     * 活动列表
     * @author ph
     * @auth true
     * @menu true
     * Time: 2019-08-21
     */
    public function index(){
        $this->title = "活动列表";
        $where = [];
        //活动标题
        if(input("request.activity_title")){
            $where[] = ["a.activity_title","like","%".trim(input("request.activity_title"))."%"];
        }
//        $where[] = ['c.status','eq',1];
        //显示全部活动
        //$where[] = ["c.status","gt",0];
        $list = Db::name("ticket_activity a")
            ->field("
                a.*,
                sum(b.ticket_num) as ticket_num,
                sum(b.gift_money) as gift_money,
                sum(b.hot_num) as hot_num
            ")
            ->where($where)
            ->join("ticket_activity_player b","a.activity_id = b.activity_id","left")
            ->join("ticket_player c","b.player_id = c.id","left")
            ->group("a.activity_id")
            ->order("a.status desc,a.activity_id desc,a.activity_start_time asc")
            ->select();
        //计算活动人数
        $activity_id_list = array_column($list,'activity_id');
        $player_num_list = Db::name('ticket_activity_player a')
            ->where('a.activity_id','in',$activity_id_list)
            ->where('b.status','eq',1)
            ->join('ticket_player b ','a.player_id = b.id')
            ->group('a.activity_id')
            ->column('a.activity_id,count( * ) as num');
        foreach($list as $k=>&$v){
            $v['sum_player'] = isset($player_num_list[$v['activity_id']]) ? $player_num_list[$v['activity_id']] : 0;
        }
        $this->assign("list",$list);
        return $this->fetch();
    }

    /**
     * 修改活动状态
     *  @auth true
     * @author ph
     * Time: 2019-08-21
     */
    public function saveActivityStatus(){
        $activity_id = input("post.activity_id");
        $status = input("post.status");
        if(!$activity_id || !$status) {
            return return_data(0, "缺少参数");
        }
        $status = $status == 2 ? 0 : $status;
        $re = Db::name("ticket_activity")->where("activity_id","eq",$activity_id)->update(['status'=>$status]);
        if($re === false){
            return return_data(0, "操作失败");
        }
        S()->set("activity_info_".$activity_id,null);
        return return_data(1, "操作成功");
    }

    /**
     * 添加活动
     *  @auth true
     * @author ph
     * Time: 2019-08-22
     */
    public function addActivity(){
        $request = request()->isGet();
        if(!$request){
            $data = input("post.");
            $activity_data = [];
            /**
             * 活动信息
             */
            //活动标题
            $activity_data['activity_title'] = trim($data['activity_title']);
            //活动海报
            $activity_data['poster_img'] = $data['poster_img'];
            //活动开始时间结束时间
            $activity_time = explode(" - ",$data['activity_time']);
            $activity_data['activity_start_time'] = strtotime($activity_time[0]);
            $activity_data['activity_end_time'] = strtotime($activity_time[1]);
            //活动投票开始时间结束时间
            $activity_ticket_time = explode(" - ",$data['activity_ticket_time']);
            $activity_data['ticket_start_time'] = strtotime($activity_ticket_time[0]);
            $activity_data['ticket_end_time'] = strtotime($activity_ticket_time[1]);
            //未填写投票时间默认以活动的开始结束时间为准
            if(is_null($activity_data['ticket_start_time']) == true){
                $activity_data['ticket_start_time'] = $activity_data['activity_start_time'];
            }
            if(is_null($activity_data['ticket_end_time']) == true){
                $activity_data['ticket_end_time'] = $activity_data['activity_end_time'];
            }
            //创建活动时间
            $activity_data['create_time'] = time();

            /**
             * 活动规则
             */
            //选手最大得票数
            $activity_data['max_num'] = (int)$data['max_num'];
            //每人每日每用户
            $activity_data['person_day_user_num'] = (int)$data['person_day_user_num'];
            //每人每日
            $activity_data['person_day_num'] = (int)$data['person_day_num'];
            //是否显示分组
            $activity_data['is_group'] = (int)$data['is_group'];
            //是否语音播报
            $activity_data['is_voice'] = (int)$data['is_voice'];

            /**
             * 活动内容
             */
            //大赛详情
            $activity_data['activity_rule_content'] = $data['activity_rule_content'];
            //活动奖品
            $activity_data['activity_prize_content'] = $data['activity_prize_content'];
            //鸣谢单位
            $activity_data['cooperative_company'] = $data['cooperative_company'];

            /**
             * 分享设置
             */
            //分享标题
            $activity_data['share_title'] = trim($data['share_title']);
            //分享描述
            $activity_data['share_desc'] = trim($data['share_desc']);
            //分享图片
            $activity_data['share_img'] = $data['share_img'];

            if($data['activity_id'] > 0){
                //编辑
                $re = Db::name("ticket_activity")->where("activity_id","eq",$data['activity_id'])->update($activity_data);
                S()->set("activity_info_".$data['activity_id'],null);
                $activity_event = new ActivityEvent();
                $activity_event->getActivityInfo($data['activity_id']);
            }else{
                //新增
                $re = Db::name("ticket_activity")->insert($activity_data);
            }
            if($re === false){
                return return_data(0,"操作失败");
            }
            return return_data(1,"操作成功");
        }else{
            $activity_id = input("get.activity_id",0);
            $activity_info = [];
            if($activity_id > 0){
                //查询活动
                $activity_info = Db::name("ticket_activity")
                    ->field("
                        activity_id,
                        activity_title,
                        activity_desc,
                        poster_img,
                        concat(from_unixtime(activity_start_time,'%Y-%m-%d %H:%i:%s'),' - ',from_unixtime(activity_end_time,'%Y-%m-%d %H:%i:%s')) as activity_time,
                        concat(from_unixtime(ticket_start_time,'%Y-%m-%d %H:%i:%s'),' - ',from_unixtime(ticket_end_time,'%Y-%m-%d %H:%i:%s')) as activity_ticket_time,
                        person_day_user_num,
                        person_day_num,
                        is_verify,
                        activity_rule_content,
                        activity_prize_content,
                        share_title,
                        share_desc,
                        share_img,
                        cooperative_company,
                        is_group,
                        is_voice,
                        max_num
                    ")
                    ->where("activity_id","eq",$activity_id)
                    ->find();
            }else{
                $activity_info['is_group'] = 0;
                $activity_info['is_voice'] = 0;
            }
            $activity_info['is_verify'] = "";

            $this->assign("activity_id",$activity_id);
            $this->assign("info",$activity_info);
            return $this->fetch();
        }
    }

    /**
     * 活动礼物列表
     *  @auth true
     * @author ph
     * Time: 2019-08-23
     */
    public function activityGiftList(){
        $activity_id = input("get.activity_id");
        if(!$activity_id){
            $this->error("缺少参数");
        }
        $where = [];
        $where[] = ['activity_id','eq',$activity_id];
        $where[] = ['status',"eq",1];
        $list = Db::name("ticket_activity_gift")
            ->where($where)
            ->select();
        foreach($list as $k=>$v){
            $list[$k]['num'] = $k;
        }
        $this->assign("list",$list);
        $this->assign("gift_count",count($list));
        $this->assign("activity_id",$activity_id);
        return $this->fetch();
    }

    /**
     * 添加活动礼物
     *  @auth true
     * @author ph
     * Time: 2019-08-27
     */
    public function addGift(){
        $data = input("post.");
        if(!isset($data['activity_id'])){
            return return_data(0,"缺少重要参数");
        }
        $add = [];
        $add['activity_id'] = $data['activity_id'];
        $add['gift_name'] = $data['gift_name'];
        $add['gift_img'] = $data['gift_img_url'];
        $add['create_time'] = time();
        $add['gift_price'] = $data['gift_price'];
        $add['gift_ticket_num'] = $data['gift_ticket_num'];
        if(isset($data['gift_id'])){
            $re = Db::name("ticket_activity_gift")->where("id","eq",$data['gift_id'])->update($add);
            $gift_id = $data['gift_id'];
        }else{
            $re = Db::name("ticket_activity_gift")->insert($add);
            $gift_id = Db::name("ticket_activity_gift")->getLastInsID();
        }
        if($re === false){
            return return_data(0,"提交失败");
        }
        S()->set("activity_gift_list_".$data['activity_id'],null);
        S()->set("gift_info_".$gift_id,null);
        return return_data(1,"提交成功",['gift_id'=>$gift_id]);
    }

    /**
     * 删除礼物
     *  @auth true
     * @author ph
     * Time: 2019-08-27
     */
    public function delGift(){
        $gift_id = input("post.gift_id",0);
        if($gift_id == 0){
            return return_data(0,"缺少参数");
        }
        $re = Db::name("ticket_activity_gift")->where("id","eq",$gift_id)->update(['status'=>0]);
        if($re === false){
            return return_data(0,"操作失败");
        }
        $activity_id = Db::name("ticket_activity_gift")->where("id","eq",$gift_id)->value("activity_id");
        S()->set("activity_gift_list_".$activity_id,null);
        return return_data(1,"操作成功");
    }

    /**
     * 参赛选手列表
     *  @auth true
     * @author ph
     * Time: 2019-08-27
     */
    public function playerList(){
        $this->title = "选手列表";
        $activity_id = input("request.activity_id");
        $order = input("request.order",3);
        switch($order){
            case 1:
                $db_order = "a.ticket_num desc";
                break;
            case 2:
                $db_order = "a.gift_money desc";
                break;
            case 3:
                $db_order = "b.id desc";
                break;
            default:
                $db_order = "b.id desc";
                break;
        }
	$request = input("request.");
        $where = [];
        $where[] = ['a.activity_id','eq',$activity_id];
        if(isset($request['username'])){
            $where[] = ['name','like',"%".$request['username']."%"];
        }
        if(isset($request['mobile'])){
            $where[] = ['mobile','like',"%".$request['mobile']."%"];
        }
        if(isset($request['group_id']) && $request['group_id'] != -1){
            $where[] = ['group_id','eq',$request['group_id']];
        }
       //参赛选手加分页 add by yan 2019/12/25
        $list = Db::name("ticket_activity_player a")
            ->field("
                b.*,
				a.player_num,
                a.ticket_num as real_ticket_num,
                a.gift_money as real_gift_money,
                a.ticket_num as real_hot_num,
                case when b.status = 1 then '正常' when b.status = 10 then '审核中' when b.status = 0 then '已删除' end as status_msg,
                case when (sum( c.amount ) ) > 0 then sum( c.amount )   else 0 end player_amount
            ")
            ->join("ticket_player b","a.player_id = b.id and b.status = 1")
            ->join("ticket_order c","b.id = c.player_id and c.status = 1","left")
            ->where($where)
            ->group("b.id,a.ticket_num,a.gift_money,a.ticket_num,a.player_num")
            ->order($db_order)
            ->paginate(10,false,['path'=>'/admin.html#/ticket/activity/playerlist.html','query'=>request()->get()]);
        $group_list = Db::name('ticket_activity_group')
            ->field('id as group_id,group_name')
            ->where('activity_id','eq',$activity_id)
            ->where('status','eq',1)
            ->select();

        array_unshift($group_list,['group_id'=>0,'group_name'=>'暂无分组']);
		//分页后的排名处理 add by yan 2019/12/25
		$this->assign("page",(request()->get('page',1)-1)*10);
        $this->assign("list",$list);
        $this->assign("group_list",$group_list);
        $this->assign("activity_id",$activity_id);
        return $this->fetch();

    }

    /**
     * 添加参赛选手
     *  @auth true
     * @author ph
     * Time: 2019-08-27
     */
    public function addPlayer(){
        $this->title = "参赛选手";
        $request = request()->isGet();
        if(!$request){
            $data = input()['arr'];
            $param = [];
            $param['name'] = $data['name'];
            $param['sex'] = isset($data['sex']) ? $data['sex'] : 1;
            $param['mobile'] = $data['mobile'];
            $param['person_img'] = $data['img_arr'];
            $param['declaration'] = $data['declaration'];
            $param['desc'] = $data['desc'];
            $param['ticket_num'] = $data['ticket_num'];
            $param['gift_money'] = $data['gift_money'];
            $param['hot_num'] = $data['hot_num'];
            $param['head_img'] = $data['head_img'];
            $param['small_video'] = $data['small_video'];
            //新增作品名称
            $param['pro_name'] = $data['pro_name'];
            //公司名称
            $param['company_name'] = $data['company_name'];
			//添加或编辑选手判断分组 add by yan 2019/12/25
            isset($data['group_id']) && $param['group_id'] = $data['group_id'];

            Db::startTrans();
            if(isset($data['player_id'])){
                $re = Db::name("ticket_player")->where("id","eq",$data['player_id'])->update($param);
                if($re === false){
                    Db::rollback();
                    return return_data(0,"编辑选手信息失败");
                }
                $rea = Db::name('ticket_activity_player')->where('player_id','eq',$data['player_id'])
                    ->update([
                        'player_num'=> isset($data['player_num']) ? $data['player_num'] : ''
                    ]);
                if($rea === false){
                    Db::rollback();
                    return return_data(0,"保存选手编码失败");
                }
            }else{
                //状态为正常
                $param['status'] = 1;
                $param['create_time'] = time();
                $res = Db::name("ticket_player")->insert($param);
                if($res === false){
                    Db::rollback();
                    return return_data(0,"添加选手失败");
                }
                $player_id = Db::name("ticket_player")->getLastInsID();
                $map = [];
                $map['activity_id'] = $data['activity_id'];
                $map['player_id'] = $player_id;
                $map['ticket_num'] = 0;
                $map['gift_money'] = 0;
                $map['hot_num'] = 0;
                $map['create_time'] = time();
                //选手编号录入 add by yan 2019/12/25
				$map['player_num'] = $data['player_num'];
                $re = Db::name("ticket_activity_player")->insert($map);
                if($re === false){
                    Db::rollback();
                    return return_data(0,"添加选手失败");
                }
            }
            Db::commit();
            return return_data(1,"添加成功");
        }else{
            $activity_id = input("request.activity_id",1);
            $where = [];
            $where[] = ['a.activity_id','eq',$activity_id];
            $where[] = ['a.status','eq',1];
            $group_list = Db::name('ticket_activity_group a')
                ->field("a.id as group_id,a.group_name")
                ->where($where)
                ->select();
            array_unshift($group_list,['group_id'=>0,'group_name'=>'暂无分组']);

            $this->assign("activity_id",$activity_id);
            $this->assign("group_list",$group_list);
            return $this->fetch();
        }
    }

    /**
     * 修改
     *  @auth true
     * @author ph
     * Time: 2019-09-03
     */
    public function savePlayerStatus(){
        $status = input("post.status");
        if(isset($status) == false){
            return return_data(0,"缺少参数");
        }
        $player_id = input("post.player_id");
        if(!$player_id){
            return return_data(0,"缺少参数.");
        }
        $re = Db::name("ticket_player")->where("id","eq",$player_id)->update(['status'=>$status]);
        if($re === false){
            return return_data(0,"操作失败");
        }
        return return_data(1,"操作成功");
    }

    /**
     * 选手详情
     *  @auth true
     * @author ph
     * Time: 2019-09-03
     */
    public function playerDetail(){
        $player_id = input("request.player_id");
        $info = Db::name("ticket_player a")
            ->field("
                a.*,
                a.id as player_id,
                b.ticket_num as real_ticket_num,
                b.gift_money as real_gift_money,
                b.ticket_num as real_hot_num,
                b.player_num
            ")
            ->where("a.id","eq",$player_id)
            ->join("ticket_activity_player b","a.id = b.player_id")
            ->find();
        if($info['person_img'] == ''){
            $person_img = [];
        }else{
            $person_img = explode(",",$info['person_img']);
        }
        $this->assign("info",$info);
        $this->assign("person_img",$person_img);
        return $this->fetch();
    }

    /**
     * 给某个活动的选手加票
     *  @auth true
     * @author ph
     * Time: 2019-09-03
     */
    public function addPlayerActivityTicketNum(){
        $player_id = input("post.player_id");
        $activity_id = input("post.activity_id");
        $num = input("post.num");
        $info = Db::name("ticket_activity_player")
            ->where("activity_id","eq",$activity_id)
            ->where("player_id","eq",$player_id)
            ->find();
        if(empty($info)){
            $data = [];
            $data['activity_id'] = $activity_id;
            $data['player_id'] = $player_id;
            $data['ticket_num'] = $num;
            $data['create_time'] = time();
            $re = Db::name("ticket_activity_player")->insert($data);
        }else{
            $data = [];
            $data['ticket_num'] = $num + $info['ticket_num'];
            $re = Db::name("ticket_activity_player")->where("id","eq",$info['id'])->update($data);
        }
        if($re === false){
            return return_data(0,"操作失败");
        }
        return return_data(1,"操作成功");
    }

    /**
     * 订单列表
     *  @auth true
     * @author ph
     * Time: 2019-09-10
     */
    public function orderList(){
        $this->title = "订单列表";
        $activity_id = input("request.activity_id",0);
        $where = [];
        $page = input("get.page",1);

        if($activity_id > 0 ){
            $where[] = ['b.activity_id','eq',$activity_id];
        }
        if(input("request.order_id")){
            $where[] = ['a.order_id','like',"%".input('request.order_id')."%"];
        }
        if(input("request.trade_no")){
            $where[] = ['a.trade_no','eq',"%".input("request.trade_no")."%"];
        }
        if(input("request.status")){
            if(input("request.status") == 2){
                $where[] = ['a.status','eq',0];
            }else{
                $where[] = ['a.status','eq',1];
            }
        }
        if(input('get.export_order') > 0){
            $list = Db::name("ticket_order a")
                ->field("
                a.order_id,
                a.amount,
                a.trade_no,
                a.trade_success_time,
                case when a.pay_type = 1 then '微信' else '其他' end pay_type,
                case when a.status = 1 then '已支付' else '未支付' end status,
                from_unixtime(a.create_time) as create_time,
                b.gift_name,
                b.gift_price,
                c.name as player_name
            ")
                ->where($where)
                ->join("ticket_activity_gift b","a.gift_id = b.id")
                ->join("ticket_player c","a.player_id = c.id")
                ->order("status desc,id desc")
                ->page($page, 10)
                ->select();
            $xlsCell = array(
                array('order_id', '订单号'),
                array('player_name', '选手姓名'),
                array('gift_name', '礼物名称'),
                array('gift_price', '礼物价格'),
                array('amount', '订单金额'),
                array('pay_type', '支付方式'),
                array('status', '支付状态'),
                array('create_time', '下单时间'),
                array('trade_no', '三方订单号'),
                array('trade_success_time', '三方支付时间')
            );
            $xlsName = date('Y-m-d') . '订单导出数据';
            exportExcel($xlsName, $xlsCell, $list,[]);
        }
        $order_list = Db::name("ticket_order a")
            ->field("
                a.id,
                a.order_id,
                a.amount,
                a.trade_no,
                a.trade_success_time,
                a.pay_type,
                a.status,
                from_unixtime(a.create_time) as create_time,
                b.gift_name,
                b.gift_img,
                b.gift_price,
                c.name as player_name,
                c.head_img as player_head_img
            ")
            ->where($where)
            ->join("ticket_activity_gift b","a.gift_id = b.id")
            ->join("ticket_player c","a.player_id = c.id")
            ->order("status desc,id desc")
            ->page($page, 10)
            ->select();
        $count = Db::name("ticket_order a")
            ->where($where)
            ->join("ticket_activity_gift b","a.gift_id = b.id")
            ->join("ticket_player c","a.player_id = c.id")
            ->count();
        $this->assign("count", $count);
        $this->assign("curr", $page);
        $this->assign("order_list",$order_list);
        return $this->fetch();
    }
    
    /**
     * 选手的礼物订单列表
     *  @auth true
     * @author ph
     * Time: 2019-10-21
     */
    public function playergiftlist(){
        $player_id = input("request.player_id");
        if(!$player_id){
            return return_data(0,"缺少参数");
        }
        $list = Db::name("ticket_order a")
            ->field("
                a.order_id,
                FROM_UNIXTIME(a.create_time,'%Y-%m-%d %H:%i:%s') as create_time,
                a.amount,
                case when a.num > 0 then a.num else 1 end num,
                b.gift_name
            ")
            ->where("a.player_id","eq",$player_id)
            ->where("a.status","eq",1)
            ->join("ticket_activity_gift b","a.gift_id = b.id")
            ->order("a.id desc")
            ->select();
        $this->assign("list",$list);
        return $this->fetch();
    }

    /**
     * 活动分组管理
     *  @auth true
     * User: phao345
     * Date: 2019/11/25
     */
    public function groupList(){
        $activity_id = input('get.activity_id');
        if(!$activity_id){
            $this->error('缺少参数');
        }

        $activity_info = Db::name("ticket_activity")->field('activity_id,activity_title')->where('activity_id','eq',$activity_id)->find();
        $where = [];
        $where[] = ['a.activity_id','eq',$activity_id];
        $where[] = ['a.status','eq',1];
        $group_list = Db::name('ticket_activity_group a')
            ->field("
                a.id as group_id,
                a.group_name,
                a.img,
                from_unixtime(a.create_time,'%Y-%m-%d %H:%i:%s') as create_time
            ")
            ->where($where)
            ->select();

        array_unshift($group_list,['group_id'=>0,'create_time'=>date('Y-m-s H:i:s'),'group_name'=>'暂无分组']);

        $activity_event = new ActivityEvent();
        foreach($group_list as $k=>$v){
            $group_list[$k]['sum_ticket_num'] = $activity_event->getActicityGroupTicketNum($v['group_id']);
        }

        $this->assign('group_list',$group_list);
        $this->assign('activity_info',$activity_info);
        return $this->fetch();
    }

    /**
     * 修改分组名称
     *  @auth true
     * User: phao345
     * Date: 2019/11/29
     */
    public function saveGroupName(){
        $group_name = input('post.group_name');
        $group_id   = input('post.group_id');
        $re = Db::name('ticket_activity_group')->where('id','eq',$group_id)->update([
            'group_name'    =>  $group_name,
            'update_time'   =>  time()
        ]);
        if($re === false){
            return return_data(0,'分组名称修改失败,请稍后再试');
        }
        return return_data(1,'分组名称修改成功');
    }

    /**
     * 新增活动分组
     *  @auth true
     * User: phao345
     * Date: 2019/11/29
     */
    public function addActivityGroup(){
        $request = request()->isGet();
        if(!$request){
            $activity_id = input('post.activity_id');
            if(!$activity_id){
                return return_data(0,'缺少活动id');
            }
            $group_name = input('post.group_name');
            if(!$group_name){
                return return_data(0,'缺少分组名称');
            }
            $img = input('post.img');
            $group_id = Db::name('ticket_activity_group')
                ->insertGetId([
                    'group_name' => $group_name,
                    'img' => $img,
                    'activity_id'=> $activity_id,
                    'status'     => 1,
                    'create_time'=> time()
                ]);
            if(!$group_id){
                return return_data(0,'分组创建失败');
            }
            return return_data(1,'分组创建成功',['group_id'=>$group_id]);
        }else{
            return $this->fetch();
        }

    }

    /**
     * 删除分组
     *  @auth true
     * User: phao345
     * Date: 2019/11/29
     */
    public function delActivityGroup(){
        $group_id = input('post.group_id');
        if(!$group_id){
            return return_data(0,'缺少参数');
        }

        Db::startTrans();
        //修改该分组下的选手   置为0
        $re = Db::name('ticket_player')->where('group_id','eq',$group_id)->update(['group_id'=>0]);
        if($re === false){
            return return_data(0,'删除失败!');
        }

        $res = Db::name('ticket_activity_group')->where('id','eq',$group_id)->delete();
        if($res === false){
            return return_data(0,'删除失败');
        }

        Db::commit();
        return return_data(1,'删除成功');
    }

    /**
     * 获取活动下的分组列表
     *  @auth true
     * User: phao345
     * Date: 2019/11/29
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGroupList(){
        $activity_id = input('post.activity_id');
        if(!$activity_id){
            return return_data(0,'缺少参数');
        }
        $group_list = Db::name('ticket_activity_group')
            ->field('id as group_id,group_name')
            ->where('activity_id','eq',$activity_id)
            ->where('status','eq',1)
            ->select();
        if(empty($group_list)){
            return return_data(0,'暂无分组');
        }
        return return_data(1,'获取成功',$group_list);
    }

    /**
     * 修改选手分组
     *  @auth true
     * User: phao345
     * Date: 2019/11/29
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function savePlayerGroup(){
        $player_id = input('post.player_id');
        if(!$player_id){
            return return_data(0,'缺少选手id');
        }
        $group_id = input('post.group_id');
        if(!$group_id){
            return return_data(0,'缺少分组id');
        }

        $re = Db::name('ticket_player')
            ->where('id','eq',$player_id)
            ->update([
                'group_id'  =>  $group_id
            ]);
        if($re === false){
            return return_data(0,'修改分组失败');
        }
        return return_data(1,'修改成功');
    }

    /**
     * 活动banner列表
     * @auth true
     * @author yan
     * Time: 2019-12-17
     */
    public function bannerlist(){
        $this->activity_id = input("get.activity_id");
        $activity_event = new ActivityEvent();
        $this->list = $activity_event->getActivityBanner($this->activity_id);
        $this->banner_count = count($this->list);
        return $this->fetch();
    }

    /**
     * 添加活动banner
     * @auth true
     * @author yan
     * Time: 2019-12-17
     */
    public function addBanner(){
        $data = input("post.");
        if(!isset($data['activity_id'])){
            return return_data(0,"缺少重要参数");
        }
        if($data['banner_img_url'] == '')
        {
            return return_data(0,"请选取图片");
        }
        if($data['type'] == 2 && $data['video_url'] == '')
        {
            return return_data(0,"请选取视频");
        }
        $add = [];
        $add['activity_id'] = $data['activity_id'];
        $add['banner_img'] = $data['banner_img_url'];
        $add['type'] = $data['type'];
        isset($data['video_url']) && $add['video_url'] = $data['video_url'];
        $add['create_time'] = time();
        if(isset($data['banner_id'])){
            $re = Db::name("ticket_activity_banner")->where("banner_id","eq",$data['banner_id'])->update($add);
            $banner_id = $data['banner_id'];
        }else{
            $re = Db::name("ticket_activity_banner")->insert($add);
            $banner_id = Db::name("ticket_activity_gift")->getLastInsID();
        }
        if($re === false){
            return return_data(0,"提交失败");
        }
        //重置缓存
        S()->set("activity_banner_img_".$data['activity_id'],null);
        return return_data(1,"提交成功",['banner_id'=>$banner_id]);
    }

    /**
     * 删除banner
     * @auth true
     * @author yan
     * Time: 2019-12-17
     */
    public function delBanner(){
        $banner_id = input("post.banner_id",0);
        if($banner_id == 0){
            return return_data(0,"缺少参数");
        }
        //删除前获取一下活动id
        $activity_id = Db::name("ticket_activity_banner")->where("banner_id","eq",$banner_id)->value("activity_id");
        //执行删除
        $re = Db::name("ticket_activity_banner")->delete($banner_id);
        if($re === false){
            return return_data(0,"操作失败");
        }
        //重置缓存
        S()->set("activity_banner_img_".$activity_id,null);

        return return_data(1,"操作成功");
    }
    
    /**
     * 添加分组
     * @auth true
     * @author yan
     * Time: 2019-12-20
     */
    public function addGroup(){
        $this->activity_id = request()->get('activity_id');
        return $this->fetch();
    }
     /**
     * 修改分组图片
     * @auth true
     * User: yan
     * Date: 2019/12/22
     */
    public function saveGroupImg(){
        $img = input('post.img');
        $group_id   = input('post.group_id');
        $re = Db::name('ticket_activity_group')->where('id','eq',$group_id)->update([
            'img'    =>  $img,
            'update_time'   =>  time()
        ]);
        if($re === false){
            return return_data(0,'分组图片修改失败,请稍后再试');
        }
        return return_data(1,'分组图片修改成功');
    }

    public function bigScreen()
    {
        $this->assign('activity_id',input('get.activity_id'));
        return $this->fetch();
    }
    
}
