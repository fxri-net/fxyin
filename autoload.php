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

// 加载文件
require_once __DIR__ . DIRECTORY_SEPARATOR . 'frame' . DIRECTORY_SEPARATOR . 'Loader.php';
// 注册文件
\fxyin\Loader::register();
// 加载基础配置
\fxyin\Config::load(__DIR__ . DIRECTORY_SEPARATOR . 'config.php');
