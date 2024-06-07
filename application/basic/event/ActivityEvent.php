<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/9/6 0006
 * Time: 15:28
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
use think\Db;
use think\Cache;

class ActivityEvent extends Controller{

    /**
     * 获取活动信息
     * @param $activity_id
     * @return array|null|object|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author ph
     * Time: 2019-09-06
     */
    public function getActivityInfo($activity_id){
        $info = [];
        if(S()->get("activity_info_".$activity_id)){
            $info = json_decode(S()->get("activity_info_".$activity_id),1);
        }else{
            $info = Db::name("ticket_activity")
                ->where("activity_id","eq",$activity_id)
                ->where("status","eq",1)
                ->find();
            S()->set("activity_info_".$activity_id,json_encode($info),60*60*1);
        }
        return $info ? $info : [];
    }

    /**
     * 检测时间 未开始 进行中 已结束
     * @author ph
     * Time: 2019-09-04
     */
    public function checkActivityTime($order_info){
        $status = 0;
        $msg = "";
       
        if($order_info['status'] == 0){
            $msg = "活动已关闭";
            return resultInfo($status,$msg);
        }
        $now_time = time();
        if($now_time < $order_info['activity_start_time']){
            $msg = "活动未开始";
        }elseif($now_time > $order_info['activity_end_time']){
            $msg = "活动已结束";
        }elseif($now_time > $order_info['activity_start_time'] && $now_time < $order_info['activity_end_time']){
            $msg = "活动进行中";
            $status = 1;
        }
        return resultInfo($status,$msg);
    }

    /**
     * 获取礼物列表
     * @author ph
     * Time: 2019-09-06
     */
    public function getActivityGiftList($activity_id){
       $list = [];
        if(S()->get("activity_gift_list_".$activity_id)){
            $list = json_decode(S()->get("activity_gift_list_".$activity_id),1);
        }else{
            $list = Db::name("ticket_activity_gift")
                ->where("activity_id","eq",$activity_id)
                ->where("status","eq",1)
                ->order("sort asc")
                ->select();
            S()->set("activity_gift_list_".$activity_id,json_encode($list),60*60*1);
        }
        return $list ? $list : [];
    }

    /**
     * 获取礼物详情
     * @author ph
     * Time: 2019-09-06
     */
   public function getGiftInfo($gift_id){
        $info = [];
        if(S()->get("gift_info_".$gift_id)){
            $info = json_decode(S()->get("gift_info_".$gift_id),1);
        }else{
            $info = Db::name("ticket_activity_gift")
                ->where("id","eq",$gift_id)
                ->find();
            S()->set("gift_info_".$gift_id,json_encode($info),60*60*1);
        }
        return $info ? $info : [];
    }


    /**
     * 获取活动banner
     * User: phao345
     * Date: 2019/12/6
     * @param $activity_id
     * @return array|mixed
     */
   public function getActivityBanner($activity_id){
        $cache_name = "activity_banner_img_".$activity_id;
        $list = json_decode(S()->get($cache_name),true);
        if(!$list){
            $list = Db::name('ticket_activity_banner')->field('banner_id,banner_img,type,video_url')->where('activity_id','eq',$activity_id)->select();
            if(!empty($list)){
                S()->set($cache_name,json_encode($list),60*60*1);
            }
        }
        return !empty($list) ? $list : [];
    }

    /**
     * 选手排名
     * User: phao345
     * Date: 2019/12/9
     */
    public function getPlayerRanking($activity_id,$player_id){
        $return = [];
        $return['row_num'] = 0;
        $return['ticket_num'] = 0;
        $sql = 'SELECT b.* FROM ( SELECT t.player_id, t.ticket_num, @rownum := @rownum + 1 AS rownum  FROM ( SELECT @rownum := 0 ) r, ( SELECT * FROM ticket_activity_player where activity_id='.$activity_id.' ORDER BY ticket_num DESC ) AS t  ) AS b  WHERE b.player_id = '.$player_id;
        $info = Db::query($sql);
        if(!empty($info)){
            $return['row_num'] = $info[0]['rownum'];
            $return['ticket_num'] = $info[0]['ticket_num'];
        }
        return $return;
    }

    /**
     * 图片合成
     * User: phao345
     * Date: 2019/12/9
     * @param $person_img
     * @param $qr_img
     */
    public function palyerImgCompose($person_img,$qr_img){

        $dst_path = $person_img; // 背景图
        $src_path = $qr_img;// 二维码图
        //创建图片的实例
        $dst = imagecreatefromstring(file_get_contents($dst_path));
        $src = imagecreatefromstring(file_get_contents($src_path));

        // 把二维码图片的白色背景设为透明
        imagecolortransparent($src, imagecolorallocate($src, 255, 255, 255));
        //获取水印图片的宽高
        list($src_w, $src_h) = getimagesize($src_path);

        //将水印图片复制到目标图片上
        imagecopymerge($dst, $src, 114, 243, 0, 0, $src_w, $src_h, 100);
        //生成图片
        imagepng($dst,'test2.png');
        //销毁
        imagedestroy($dst);
        imagedestroy($src);
    }

    /**
     * 改变图片的宽高
     *
     * @author flynetcn (2009-12-16)
     *
     * @param string $img_src 原图片的存放地址或url
     * @param string $new_img_path  新图片的存放地址
     * @param int $new_width  新图片的宽度
     * @param int $new_height 新图片的高度
     * @return bool  成功true, 失败false
     */

    public function resize_image($img_src, $new_img_path, $new_width, $new_height){
        $img_info = @getimagesize($img_src);
        if (!$img_info || $new_width < 1 || $new_height < 1 || empty($new_img_path)) {
            return false;
        }
        if (strpos($img_info['mime'], 'jpeg') !== false) {
            $pic_obj = imagecreatefromjpeg($img_src);
        } else if (strpos($img_info['mime'], 'gif') !== false) {
            $pic_obj = imagecreatefromgif($img_src);
        } else if (strpos($img_info['mime'], 'png') !== false) {
            $pic_obj = imagecreatefrompng($img_src);
        } else {
            return false;
        }
        $pic_width = imagesx($pic_obj);
        $pic_height = imagesy($pic_obj);
        if (function_exists("imagecopyresampled")) {
            $new_img = imagecreatetruecolor($new_width,$new_height);
            imagecopyresampled($new_img, $pic_obj, 0, 0, 0, 0, $new_width, $new_height, $pic_width, $pic_height);
        } else {
            $new_img = imagecreate($new_width, $new_height);
            imagecopyresized($new_img, $pic_obj, 0, 0, 0, 0, $new_width, $new_height, $pic_width, $pic_height);
        }
        if (preg_match('~.([^.]+)$~', $new_img_path, $match)) {
            $new_type = strtolower($match[1]);
            switch ($new_type) {
                case 'jpg':
                    imagejpeg($new_img, $new_img_path);
                    break;
                case 'gif':
                    imagegif($new_img, $new_img_path);
                    break;
                case 'png':
                    imagepng($new_img, $new_img_path);
                    break;
                default:
                    imagejpeg($new_img, $new_img_path);
            }
        } else {
            imagejpeg($new_img, $new_img_path);
        }
        imagedestroy($pic_obj);
        imagedestroy($new_img);
        return true;
    }

    /**
     * 获取每个地区的总票数
     * User: phao345
     * Date: 2019/11/29
     * @param int $group_id
     * @return float
     */
    public function getActicityGroupTicketNum($group_id = 1){
        $sum = Db::name('ticket_player a')
            ->join('ticket_activity_player b','a.id = b.player_id')
            ->where('group_id','eq',$group_id)
            ->sum('b.ticket_num');
        //每个团队的起始票数
        $start_num = Db::name('ticket_activity_group')
            ->where('id','eq',$group_id)
            ->value('start_num');
        $sum += $start_num;    
        return isset($sum) ? $sum : 0;
    }

    /**
     * 获取选手信息
     * User: phao345
     * Date: 2019/12/15
     * @param $player_id
     * @return array|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPlayerInfo($player_id){
        $info = Db::name('ticket_player a')
            ->field("a.id as player_id,a.name,b.activity_id")
            ->join('ticket_activity_player b','a.id = b.player_id')
            ->where('a.id','eq',$player_id)
            ->where('a.status','eq',1)
            ->find();
        return !empty($info) ? $info : [];
    }
    /**
     * 获取每个地区的参赛选手
     * User: yan
     * Date: 2019/12/20
     * @param int $group_id
     * @return float
     */
    public function getActicityGroupPlayerNum($group_id = 0){
        $sum = Db::name('ticket_player')
            ->where('group_id','eq',$group_id)
            ->count();
        return isset($sum) ? $sum : 0;
    }
}