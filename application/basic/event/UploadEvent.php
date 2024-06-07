<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/30 0030
 * Time: 13:51
 */

namespace app\basic\event;

use think\Exception;
use think\facade\Log;
use function Couchbase\defaultDecoder;
use library\driver\Oss;
use OSS\OssClient;
use think\Controller;
use library\File;

class UploadEvent extends Controller
{
    protected $uptype = "", $ext = "", $safe = false;

    public function initialize()
    {
        $this->uptype = "oss";
    }

    /**
     * Plupload 插件上传文件
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function plupload()
    {
        if (!($file = $this->getUploadFile()) || empty($file)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传异常，文件可能过大或未上传']]);
        }
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts')))) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if ($file->checkExt('php')) {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        $this->safe = $this->getUploadSafe();

        $this->uptype = $this->getUploadType();
        $this->ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $name = File::name($file->getPathname(), $this->ext, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
        if (is_array($info) && isset($info['url'])) {
            return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url']]);
        }
        return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
    }

    /**
     * doPluploads 多图插件上传文件
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function doPluploads($file)
    {
        if (!$file || empty($file)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传异常，文件可能过大或未上传']]);
        }
        $ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        if (!strstr(sysconf('storage_local_exts'), $ext)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if ($ext == 'php') {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        $this->safe = $this->getUploadSafe();
        $this->uptype = $this->getUploadType();
        $name = File::name($file->getPathname(), $ext, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
        if (is_array($info) && isset($info['url'])) {
            return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url']]);
        }
        return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
    }


    /**
     * 获取本地文件对象
     * @return \think\File
     */
    private function getUploadFile()
    {
        try {
            return $this->request->file('img');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }

    /**
     * 获取上传安全模式
     * @return boolean
     */
    private function getUploadSafe()
    {
        return $this->safe = boolval(input('safe'));
    }

    /**
     * 获取文件上传方式
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getUploadType()
    {
        $this->uptype = input('uptype');
        if (!in_array($this->uptype, ['local', 'oss', 'qiniu'])) {
            $this->uptype = sysconf('storage_type');
        }
        return $this->uptype;
    }

    /**
     * oss文件上传
     * @author ph
     * Time: 2019-06-04
     */
    public function ossUploadFile($data, $type)
    {
        $storage_oss_bucket = sysconf("storage_oss_bucket");
        $storage_oss_endpoint = sysconf("storage_oss_endpoint");
        $storage_oss_domain = sysconf("storage_oss_domain");
        $storage_oss_keyid = sysconf("storage_oss_keyid");
        $storage_oss_secret = sysconf("storage_oss_secret");
        $storage_oss_is_https = sysconf("storage_oss_is_https");
        $oss_client = new OssClient($storage_oss_keyid, $storage_oss_secret, $storage_oss_endpoint);
        $object = $type . '/' . $data['file_name'];//想要保存文件的名称
        $file = $data['url'];//文件路径，必须是本地的。
        try {
            $oss_client->uploadFile($storage_oss_bucket, $object, $file);
            //上传成功，自己编码
            //这里可以删除上传到本地的文件。unlink（$file）；
//            unlink($file);
            return $storage_oss_is_https . "://" . $storage_oss_domain . "/" . $object;
        } catch (OssException $e) {
            //上传失败，自己编码
            printf($e->getMessage() . "\n");
            return;
        }
    }

    /**
     * 不保存本地文件，直接上传文件
     * @param $data
     * @param $type
     * @param $fileName
     * @return bool|int
     */
    public function ossPutObject($data, $type, $fileName)
    {
        try {
            $config = $this->getOssConfig();
            $ossClient = $this->getOssClient();
            $object = $type . '/' . $fileName;
            $ret = $ossClient->putObject($config['bucket'], $object, $data);
            if (!isset($ret['info']['http_code']) || intval($ret['info']['http_code']) == 200) {
                return intval($ret['info']['size_upload']);
            }
            //检查返回值
            return false;
        } catch (Exception $e) {
            Log::error('oss put object failed: type:' . $type . ' filename:' . $fileName);
        }
        return false;
    }

    /**
     * 检查oss文件是否存在
     * @param $type
     * @param $fileName
     * @return false|bool
     */
    public function ossFileExists($type, $fileName)
    {
        $object = $type . '/' . $fileName;
        $config = $this->getOssConfig();
        try {
            $client = $this->getOssClient();
            $exists = $client->doesObjectExist($config['bucket'], $object);
            return $exists;
        } catch (Exception $e) {
            Log::error('check oss object exists failed: object' . $object);
        }
        return false;
    }

    /**
     * 根据object获取oss的公开文件路径
     * @param $object
     * @return string
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function getOssPublicFileUrl($type, $fileName)
    {
        $object = $type . '/' . $fileName;
        $config = $this->getOssConfig();
        return $config['is_https'] . '://' . $config['domain'] . '/' . $object;
    }

    /**
     * 获取上传端
     * @throws \OSS\Core\OssException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getOssClient()
    {
        $config = $this->getOssConfig();
        try {
            $ossClient = new OssClient($config['keyid'], $config['secret'], $config['endpoint']);
            return $ossClient;
        } catch (Exception $e) {
            Log::error('oss client create failed:' . $e->getMessage());
        }
        return null;
    }

    /**
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getOssConfig()
    {
        $storage_oss_bucket = sysconf("storage_oss_bucket");
        $storage_oss_endpoint = sysconf("storage_oss_endpoint");
        $storage_oss_domain = sysconf("storage_oss_domain");
        $storage_oss_keyid = sysconf("storage_oss_keyid");
        $storage_oss_secret = sysconf("storage_oss_secret");
        $storage_oss_is_https = sysconf("storage_oss_is_https");
        return array(
            'bucket' => $storage_oss_bucket,
            'endpoint' => $storage_oss_endpoint,
            'domain' => $storage_oss_domain,
            'keyid' => $storage_oss_keyid,
            'secret' => $storage_oss_secret,
            'is_https' => $storage_oss_is_https,
        );
    }

    /**
     * 生成宣传海报
     * User: phao345
     * Date: 2019/12/9
     * @param array $config
     * @param string $filename
     * @param string $type
     * @param int $driver_id
     * @return bool|string|void
     */
    public function createPoster($config = array(), $filename = "", $type = "", $driver_id = 0)
    {
        //如果要看报什么错，可以先注释调这个header
        if (empty($filename))
            header("content-type: image/png");
            $imageDefault = array(
                'left' => 0,
                'top' => 0,
                'right' => 0,
                'bottom' => 0,
                'width' => 100,
                'height' => 200,
                'opacity' => 100
            );
        $textDefault = array(
            'text' => '',
            'left' => 0,
            'top' => 0,
            'fontSize' => 32,       //字号
            'fontColor' => '255,255,255', //字体颜色
            'angle' => 0,
        );
        $background = $config['background'];//海报最底层得背景
        //背景方法
        $backgroundInfo = getimagesize($background);
        $backgroundFun = 'imagecreatefrom' . image_type_to_extension($backgroundInfo[2], false);
        $background = $backgroundFun($background);
        $backgroundWidth = imagesx($background);  //背景宽度
        $backgroundHeight = imagesy($background);  //背景高度
        $imageRes = imageCreatetruecolor($backgroundWidth, $backgroundHeight);
        $color = imagecolorallocate($imageRes, 0, 0, 0);
        imagefill($imageRes, 0, 0, $color);
        // imageColorTransparent($imageRes, $color);  //颜色透明
        imagecopyresampled($imageRes, $background, 0, 0, 0, 0, imagesx($background), imagesy($background), imagesx($background), imagesy($background));
        //处理了图片
        if (!empty($config['image'])) {
            foreach ($config['image'] as $key => $val) {
                $imageDefault = array(
                    'left' => 0,
                    'top' => 0,
                    'right' => 0,
                    'bottom' => 0,
                    'width' => 100,
                    'height' => 100,
                    'opacity' => 100
                );
                $val = array_merge($imageDefault, $val);
                $info = getimagesize($val['url']);
                $function = 'imagecreatefrom' . image_type_to_extension($info[2], false);
                if ($val['stream']) {   //如果传的是字符串图像流
                    $info = getimagesizefromstring($val['url']);
                    $function = 'imagecreatefromstring';
                }
                $res = $function($val['url']);
                $resWidth = $info[0];
                $resHeight = $info[1];
                //建立画板 ，缩放图片至指定尺寸
                $canvas = imagecreatetruecolor($val['width'], $val['height']);
                imagefill($canvas, 0, 0, $color);
                //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
                imagecopyresampled($canvas, $res, 0, 0, 0, 0, $val['width'], $val['height'], $resWidth, $resHeight);
                $val['left'] = $val['left'] < 0 ? $backgroundWidth - abs($val['left']) - $val['width'] : $val['left'];
                $val['top'] = $val['top'] < 0 ? $backgroundHeight - abs($val['top']) - $val['height'] : $val['top'];
                //放置图像
                imagecopymerge($imageRes, $canvas, $val['left'], $val['top'], $val['right'], $val['bottom'], $val['width'], $val['height'], $val['opacity']);//左，上，右，下，宽度，高度，透明度
            }
        }
        //    处理文字
        if (!empty($config['text'])) {
            foreach ($config['text'] as $key => $val) {
                $val = array_merge($textDefault, $val);
                list($R, $G, $B) = explode(',', $val['fontColor']);
                $fontColor = imagecolorallocate($imageRes, $R, $G, $B);
                $val['left'] = $val['left'] < 0 ? $backgroundWidth - abs($val['left']) : $val['left'];
                $val['top'] = $val['top'] < 0 ? $backgroundHeight - abs($val['top']) : $val['top'];
                $val['fontPath'] = str_replace('.', '.', $val['fontPath']);
                imagettftext($imageRes, $val['fontSize'], $val['angle'], $val['left'], $val['top'], $fontColor, $val['fontPath'], $val['text']);
            }
        }
        //生成图片
        if (!empty($filename)) {
            $res = imagejpeg($imageRes, $filename, 90); //保存到本地
            imagedestroy($imageRes);
            if (!$res) return false;
            //上传oss
            $upload_event = new UploadEvent();
            $data = [];
            $data['file_name'] = $type . "_" . $driver_id . ".png";
            $data['url'] = $filename;
            $resimg = $upload_event->ossUploadFile($data, $type);
            return $resimg;
        } else {
            imagejpeg($imageRes);     //在浏览器上显示
            imagedestroy($imageRes);
        }
    }

    /**行程海报
     * @param $bgimg
     * @param $filename
     * @param $text
     * @param $qrimg
     * @param $trip_id
     * @return bool|string|void
     * @author ph
     * Time: 2019-06-13
     */
    public function createTripPoster($bgimg, $filename, $text, $qrimg, $trip_id = 0)
    {
        $ttf = env("root_path") . "/public/static/ttf/simkai.ttf";
        $fontSize = 22;
        $fontColor = '0,0,0';
        if (strlen($text['price1']) == 5) {
            $priceleft1 = 520;
        } elseif (strlen($text['price1']) == 6) {
            $priceleft1 = 510;
        } elseif (strlen($text['price1']) == 7) {
            $priceleft1 = 500;
        } else {
            $priceleft1 = 510;
        }

        if (strlen($text['price2']) == 5) {
            $priceleft2 = 520;
        } elseif (strlen($text['price2']) == 6) {
            $priceleft2 = 510;
        } elseif (strlen($text['price2']) == 7) {
            $priceleft2 = 500;
        } else {
            $priceleft2 = 510;
        }
        $config = array(
            //出发地
            'text' => array(
                array(
                    'text' => $text['from_city'],
                    'left' => 160,
                    'top' => 715,
                    'fontPath' => $ttf,     //字体文件
                    'fontSize' => $fontSize,             //字号
                    'fontColor' => $fontColor,       //字体颜色
                    'angle' => 0,
                ),
                //目的地
                array(
                    'text' => $text['to_city'],
                    'left' => 160,
                    'top' => 762,
                    'fontPath' => $ttf,     //字体文件
                    'fontSize' => $fontSize,             //字号
                    'fontColor' => $fontColor,       //字体颜色
                    'angle' => 0,
                ),
                //拼车价格
                array(
                    'text' => $text['price1'],
                    'left' => 160,
                    'top' => 810,
                    'fontPath' => $ttf,     //字体文件
                    'fontSize' => $fontSize,             //字号
                    'fontColor' => $fontColor,       //字体颜色
                    'angle' => 0,
                ),
                //包车价格
                array(
                    'text' => $text['price2'],
                    'left' => 160,
                    'top' => 855,
                    'fontPath' => $ttf,     //字体文件
                    'fontSize' => $fontSize,             //字号
                    'fontColor' => $fontColor,       //字体颜色
                    'angle' => 0,
                ),
                //出发时间
                array(
                    'text' => $text['datetime'],
                    'left' => 160,
                    'top' => 905,
                    'fontPath' => $ttf,     //字体文件
                    'fontSize' => $fontSize,             //字号
                    'fontColor' => $fontColor,       //字体颜色
                    'angle' => 0,
                ),
                //下单人
                array(
                    'text' => $text['usertitle'],
                    'left' => 460,
                    'top' => 890,
                    'fontPath' => $ttf,     //字体文件
                    'fontSize' => 13,             //字号
                    'fontColor' => "255,255,255",       //字体颜色
                    'angle' => 0,
                ),
            ),
            'image' => array(
                array(
                    'url' => $qrimg,       //图片资源路径
                    'left' => 460,
                    'top' => 675,
                    'stream' => 0,             //图片资源是否是字符串图像流
                    'right' => 0,
                    'bottom' => 0,
                    'width' => 150,
                    'height' => 150,
                    'opacity' => 100
                )
            ),
            'background' => $bgimg,
        );
        return $this->createPoster($config, $filename, "trip", $trip_id);
    }

    /**
     * 图片二维码合并
     * User: phao345
     * Date: 2019/12/9
     * @param string $img1
     * @param string $img2
     * @param string $filename
     * @return string
     */
    public function mergeImg($path_1 = "", $path_2 = "", $type = 1,$img_name){
        $src_w = 0;
        $src_h = 0;
        $dst_y = 0;
        $dst_y = 0;

        //缩小二维码
        if($type == 1){
            $path_2 = $this->scaleImg($path_2,env('root_path') . '/public/upload/shareimg/'.time()."a.jpg",120,120);
            $src_w = 315;
            $src_h = 520;
            $dst_x = 210;
            $dst_y = 400;
        }else{
            $src_w = 315;
            $src_h = 520;
            $dst_x = 30;
            $dst_y = 100;
        }

        $dst_path = $path_1; // 背景图
        $src_path = $path_2;
        //创建图片的实例
        $dst = imagecreatefromstring(file_get_contents($dst_path));
        $src = imagecreatefromstring(file_get_contents($src_path));
        //将水印图片复制到目标图片上
        imagecopymerge($dst, $src, $dst_x, $dst_y, 0, 0, $src_w,$src_h, 100);
        //生成图片
        imagepng($dst,$img_name);
        imagedestroy($dst);
        imagedestroy($src);
        return $img_name;

    }

    /**
     * 添加文字
     * User: phao345
     * Date: 2019/12/9
     * @param $dst_path
     * @param $text
     * @return mixed
     */
    public function imgAddFont($dst_path,$text=[]){
        $src =$dst_path;
        //2.获取图片信息
        $info = getimagesize($src);
        //3.通过编号获取图像类型
        $type = image_type_to_extension($info[2], false);
        //4.在内存中创建和图像类型一样的图像
        $fun = "imagecreatefrom" . $type;
        //5.图片复制到内存
        $image = $fun($src);

        //1.设置字体的路径
        $font = env("root_path") . "/public/static/ttf/MSYH.TTF";

        foreach($text as $k=>$v){
            /*操作图片*/
            //2.填写水印内容
            $content = $text;
            //3.设置字体颜色和透明度
            $color = imagecolorallocatealpha($image, $v['color'][0], $v['color'][1], $v['color'][2],$v['color'][3]);
            //4.写入文字
            // 画布资源 字体大小 旋转角度 x轴 y轴 字体颜色 字体文件 需要渲染的字符串
            imagettftext($image, $v['size'], $v['angle'], $v['x'], $v['y'], $color, $font, $v['text']);
        }

        /*输出图片*/
        //浏览器输出
        //header("Content-type:".$info['mime']);
        $fun = "image" . $type;
        // $fun($image);//在浏览器中输出图片
        $imgPathName = $dst_path;
        //添加水印之后的图片  图片路径名称
        $fun($image, $imgPathName); //保存图片
        return $imgPathName;
    }

    public function getImgObj($picname){
        $ename=getimagesize($picname);
        $ename=explode('/',$ename['mime']);
        $ext=$ename[1];
        switch($ext){
            case "png":
                $image=imagecreatefrompng($picname);
                break;
            case "jpeg":

                $image=imagecreatefromjpeg($picname);
                break;
            case "jpg":
                $image=imagecreatefromjpeg($picname);
                break;
            case "gif":
                $image=imagecreatefromgif($picname);
                break;
        }
        return $image;
    }

    /**
     *等比例缩放函数（以保存新图片的方式实现）
     * @param string $picName 被缩放的处理图片源
     * @param string $savePath 保存路径
     * @param int $maxx 缩放后图片的最大宽度
     * @param int $maxy 缩放后图片的最大高度
     * @param string $pre 缩放后图片的前缀名
     * @return $string 返回后的图片名称（） 如a.jpg->s.jpg
     *
     **/
    protected function scaleImg($picName,$savePath, $maxx = 800, $maxy = 450)
    {
        $info = getimageSize($picName);//获取图片的基本信息
        $w = $info[0];//获取宽度
        $h = $info[1];//获取高度

        if($w<=$maxx&&$h<=$maxy){
            return $picName;
        }
        //获取图片的类型并为此创建对应图片资源
        switch ($info[2]) {
            case 1://gif
                $im = imagecreatefromgif($picName);
                break;
            case 2://jpg
                $im = imagecreatefromjpeg($picName);
                break;
            case 3://png
                $im = imagecreatefrompng($picName);
                break;
            default:
                die("图像类型错误");
        }
        //计算缩放比例
        if (($maxx / $w) > ($maxy / $h)) {
            $b = $maxy / $h;
        } else {
            $b = $maxx / $w;
        }
        //计算出缩放后的尺寸
        $nw = floor($w * $b);
        $nh = floor($h * $b);
        //创建一个新的图像源（目标图像）
        $nim = imagecreatetruecolor($nw, $nh);

        //透明背景变黑处理
        //2.上色
        $color=imagecolorallocate($nim,255,255,255);
        //3.设置透明
        imagecolortransparent($nim,$color);
        imagefill($nim,0,0,$color);


        //执行等比缩放
        imagecopyresampled($nim, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        //输出图像（根据源图像的类型，输出为对应的类型）
        $picInfo = pathinfo($picName);//解析源图像的名字和路径信息
        $savePath = $savePath;
        switch ($info[2]) {
            case 1:
                imagegif($nim, $savePath);
                break;
            case 2:
                imagejpeg($nim, $savePath);
                break;
            case 3:
                imagepng($nim, $savePath);
                break;

        }
        //释放图片资源
        imagedestroy($im);
        imagedestroy($nim);
        //返回结果
        return $savePath;
    }
}
