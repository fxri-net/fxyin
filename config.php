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

$tray['scheme'] = $_SERVER['REQUEST_SCHEME'] ?? (isset($_SERVER['SCRIPT_URI']) ? explode('://', $_SERVER['SCRIPT_URI'])[0] : null);
$tray['host'] = $_SERVER['HTTP_HOST'] ?? null;
$tray['uri'] = $_SERVER['REQUEST_URI'] ?? null;
$tray['method'] = $_SERVER['REQUEST_METHOD'] ?? null;

/**
 * 基础配置
 * @return array
 */
return [
    // 环境配置
    'env' => [
        // 应用配置
        'app' => [
            // 基础应用
            0 => __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR,
        ],
        // 基础配置
        'base' => [
            // 根目录
            'root' => '',
            // 应用目录
            'app' => '',
            // 应用名称
            'name' => '',
            // 请求方案
            'scheme' => !is_null($tray['scheme']) ? strtolower($tray['scheme']) : null,
            // 请求主机
            'host' => !is_null($tray['host']) ? strtolower($tray['host']) : null,
            // 请求路径
            'uri' => !is_null($tray['uri']) ? $tray['uri'] : null,
            // 请求方法
            'method' => !is_null($tray['method']) ? strtolower($tray['method']) : null,
            // 请求地址
            'web' => !is_null($tray['host']) ? $tray['scheme'] . '://' . $tray['host'] : '',
        ],
        // 门面配置
        'facade' => [
            // 基础门面
            0 => '\\fxapp\\facade\\',
        ],
    ],
    // 加载器配置
    'loader' => [
        // 框架配置
        'fxyin' => [
            // 基础框架
            0 => __DIR__ . DIRECTORY_SEPARATOR . 'frame' . DIRECTORY_SEPARATOR,
        ],
        // 应用配置
        'fxapp' => [
            // 基础应用
            0 => __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR,
        ],
    ],
];
