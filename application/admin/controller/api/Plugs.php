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

namespace app\admin\controller\api;

use app\admin\service\NodeService;
use library\Controller;
use library\File;

/**
 * 后台插件管理
 * Class Plugs
 * @package app\admin\controller\api
 */
class Plugs extends Controller
{

    /**
     * Plugs constructor.
     */
    public function initialize()
    {
        // parent::__construct();
        if (!NodeService::islogin()) {
            $this->error('访问授权失败，请重新登录授权再试！');
        }
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
        $this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $name = File::name($file->getPathname(), $this->extend, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
        if(input("request.type") != "layui"){
            if (is_array($info) && isset($info['url'])) {
                return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url']]);
            } else {
                return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
            }
        }else{
            if (is_array($info) && isset($info['url'])) {
                $return = [];
                $return['code'] = 0;
                $return['msg'] = "上传成功";
                $return['data'] = [
                    "src" => $this->safe ? $name : $info['url'],
                    "title" => $name
                ];
                return json_encode($return);
            } else {
                $return = [];
                $return['code'] = 1;
                $return['msg'] = "上传失败";
                $return['data'] = [];
                return json_encode($return);
            }
        }
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
     * 获取上传安全模式
     * @return boolean
     */
    private function getUploadSafe()
    {
        return $this->safe = boolval(input('safe'));
    }

    /**
     * 获取本地文件对象
     * @return \think\File
     */
    private function getUploadFile()
    {
        try {
            return $this->request->file('file');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }

    /**
     * 系统选择器图标
     */
    public function icon()
    {
        $this->title = '图标选择器';
        $this->field = input('field', 'icon');
        $this->fetch();
    }

}