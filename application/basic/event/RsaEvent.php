<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/9/4 0004
 * Time: 17:04
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
use Rsa\Rsa;
class RsaEvent extends Controller{
    //实例化加密类
    protected $rsa;
    //加密解密的数据
    protected $data;

    /**
     * 初始化
     * @param array $data
     * @return bool|void
     * @author ph
     * Time: 2019-09-04
     */
    public function initialize($data = []){
        if(empty($data)) return false;
        $this->data = $data;
        $this->rsa = new Rsa();
    }

    /**
     * Rsa私钥加密
     * @return mixed
     * @author ph
     * Time: 2019-09-04
     */
    public function rsaPrivateEncryption(){
        //要加密的数据转json
        $this->data = json_encode($this->data);
        return $this->rsa->privEncrypt($this->data);
    }

    /**
     * Rsa公钥解密
     * @return mixed
     * @author ph
     * Time: 2019-09-04
     */
    public function rsaPublicDecryption(){
        return json_decode($this->rsa->publicDecrypt($this->data));
    }

    /**
     * Rsa公钥加密
     * @return mixed
     * @author ph
     * Time: 2019-09-04
     */
    public function rsaPublicEncryption(){
        //要加密的数据转json
        $this->data = json_encode($this->data);
        return $this->rsa->publicEncrypt($this->data);
    }

    /**
     * Rsa私钥解密
     * @return mixed
     * @author ph
     * Time: 2019-09-04
     */
    public function rsaPrivDecryption(){
        return json_decode($this->rsa->privDecrypt($this->data));
    }

    /**
     * 原demo方法
     * @author ph
     * Time: 2019-09-04
     */
    public function demo(){
        $rsa = new Rsa();
        $data['name'] = 'Tom';
        $data['age']  = '20';

        $privEncrypt = $rsa->privEncrypt(json_encode($data));
        echo '私钥加密后:'.$privEncrypt.'<br>';

        $publicDecrypt = $rsa->publicDecrypt($privEncrypt);
        echo '公钥解密后:'.$publicDecrypt.'<br>';

        $publicEncrypt = $rsa->publicEncrypt(json_encode($data));
        echo '公钥加密后:'.$publicEncrypt.'<br>';

        $privDecrypt = $rsa->privDecrypt($publicEncrypt);
        echo '私钥解密后:'.$privDecrypt.'<br>';
    }
}