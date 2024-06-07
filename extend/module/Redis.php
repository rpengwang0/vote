<?php
/**
 * Created by PhpStorm.
 * User: ph
 * Date: 2019/5/28 0028
 * Time: 17:34
 */

namespace module;

class Redis extends \Redis {
    private static $handler;
    public static function redis() {
        $con = new \Redis();
        $con->connect(config('cache.redis.host'), config('cache.redis.port'), 5);
        return $con;
    }

    private static function handler(){
        if(!self::$handler){
            self::$handler = new Redis();
            self::$handler -> connect('127.0.0.1','6379');
        }
        return self::$handler;
    }

    public static function gets($key){
        $value = self::handler() -> get($key);
        $value_serl = @unserialize($value);
        if(is_object($value_serl)||is_array($value_serl)){
            return $value_serl;
        }
        return $value;
    }

    public static function sets($key,$value){
        if(is_object($value)||is_array($value)){
            $value = serialize($value);
        }

        return self::handler() -> set($key,$value);
    }

}