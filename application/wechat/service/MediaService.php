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

namespace app\wechat\service;

use library\File;
use think\Db;
use WeChat\Contracts\MyCurlFile;

/**
 * 微信素材管理
 * Class MediaService
 * @package app\wechat\service
 */
class MediaService
{
    /**
     * 通过图文ID读取图文信息
     * @param integer $id 本地图文ID
     * @param array $where 额外的查询条件
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function news($id, $where = [])
    {
        $data = Db::name('WechatNews')->where(['id' => $id])->where($where)->find();
        list($data['articles'], $articleIds) = [[], explode(',', $data['article_id'])];
        $articles = Db::name('WechatNewsArticle')->whereIn('id', $articleIds)->select();
        foreach ($articleIds as $article_id) foreach ($articles as $article) {
            if (intval($article['id']) === intval($article_id)) array_push($data['articles'], $article);
            unset($article['create_by'], $article['create_at']);
        }
        return $data;
    }

    /**
     * 上传图片永久素材，返回素材media_id
     * @param string $url 文件URL地址
     * @param string $type 文件类型
     * @param array $videoInfo 视频信息
     * @return string|null
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function upload($url, $type = 'image', $videoInfo = [])
    {
        $where = ['md5' => md5($url), 'appid' => WechatService::getAppid()];
        if (($mediaId = Db::name('WechatMedia')->where($where)->value('media_id'))) return $mediaId;
        $result = WechatService::WeChatMedia()->addMaterial(self::getServerPath($url), $type, $videoInfo);
        data_save('WechatMedia', [
            'local_url' => $url, 'md5' => $where['md5'], 'appid' => WechatService::getAppid(), 'type' => $type,
            'media_url' => isset($result['url']) ? $result['url'] : '', 'media_id' => $result['media_id'],
        ], 'type', $where);
        return $result['media_id'];
    }

    /**
     * 文件位置处理
     * @param string $local
     * @return string
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    private static function getServerPath($local)
    {
        if (file_exists($local)) return new MyCurlFile($local);
        return new MyCurlFile(File::down($local)['file']);
    }
}