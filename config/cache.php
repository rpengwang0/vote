<?php
return [
    // 缓存配置为复合类型
    'type'  =>  'complex',
    'default'	=>	[
        'type'	=>	'file',
        // 全局缓存有效期（0为永久有效）
        'expire'=>  0,
        // 缓存前缀
        'prefix'=>  'sdb_',
        // 缓存目录
        'path'  =>  '../runtime/cache/',
    ],
    'redis'	=>	[
        'type'	=>	'redis',
        'host'	=>	'127.0.0.1',
        'password'	=>	'',
        // 全局缓存有效期（0为永久有效）
        'expire'=>  0,
        // 缓存前缀
        'prefix'=>  '',
        //端口
        'port'=> 6379
    ],
    // 添加更多的缓存类型设置
];
?>