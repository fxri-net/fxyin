<?php
// +----------------------------------------------------------------------
// | Name 风音框架
// +----------------------------------------------------------------------
// | Author 唐启云 <tqy@fxri.net>
// +----------------------------------------------------------------------
// | Copyright Copyright © 2016-2026 唐启云. All rights reserved.
// +----------------------------------------------------------------------
// | Link https://www.fxri.net
// +----------------------------------------------------------------------

// 应用配置
return [
    // 基础设置
    'base' => [
        // 默认时区
        'timezone' => 'PRC',
    ],
    // 调试配置
    'debug' => [
        // 开关
        'switch' => false,
        // 等级
        'level' => ['error', 'warning', 'notice', 'info', 'debug', 'trace', 'depot', 'ignore'],
        // 跟踪
        'trace' => null,
    ],
    // 日志配置
    'log' => [
        // 开关
        'switch' => false,
        // 等级
        'level' => ['error', 'warning', 'notice', 'info', 'debug', 'trace', 'depot', 'ignore'],
    ],
    // 语言配置
    'lang' => [
        // 多语言开关
        'switch' => true,
        // 名称
        'name' => 'lang',
        // 默认语言
        'default' => 'zh-cn',
        // 忽略转换
        'prefix' => '!@',
        // 忽略文本
        'ignore' => '!#',
        // 缓存有效期
        'expire' => 315360000,
        // 列表
        'list' => [
            'zh-cn',
            'en-us',
        ],
    ],
    // Session设置
    'session' => [
        // 是否自动开启
        'auto_start' => true,
        // 启用安全传输
        'secure' => false,
        // httponly设置
        'httponly' => true,
    ],
    // Cookie设置
    'cookie' => [
        // 保存时间
        'expire' => 0,
        // 保存路径
        'path' => '/',
        // 有效域名
        'domain' => '',
        // 启用安全传输
        'secure' => false,
        // httponly设置
        'httponly' => false,
    ],
    // 媒体配置
    'media' => [
        // 视频
        'video' => [
            // 启动
            'bin' => null,
            // 目录
            'path' => [
                // Linux系统
                'linux' => null,
                // Windows系统
                'windows nt' => null,
            ],
        ],
    ],
];
