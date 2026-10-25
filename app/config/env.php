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

\fxyin\Lang::setLangCookieName(fxy_config('app.lang.name'));
// 视图配置
$tray['lang'] = $_SERVER['HTTP_LANG'] ?? \fxyin\Lang::detect();
// 识别语言
$config = fxy_config('app.lang');
if (!in_array($tray['lang'], $config['list'])) {
    $tray['lang'] = fxy_config('app.lang.default');
}

/**
 * 环境配置
 * @return array
 */
return [
    // 视图配置
    'view' => [
        // 语言
        'lang' => $tray['lang'],
    ],
];
