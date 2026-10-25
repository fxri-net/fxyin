<?php
// +----------------------------------------------------------------------
// | Name 风音框架
// +----------------------------------------------------------------------
// | Author 唐启云 <tqy@fxri.net>
// +----------------------------------------------------------------------
// | Copyright Copyright © 2016-2099 方弦研究所. All rights reserved.
// +----------------------------------------------------------------------
// | Link https://www.fxri.net
// +----------------------------------------------------------------------

namespace fxapp;

/**
 * 文件类
 * @see \fxapp\facade\File
 * @package fxapp\facade
 * @method static mixed init() 初始化配置
 * @method static string formatSize(int $size) 处理文件-格式化大小
 * @method static mixed getList(mixed $path, ?string $ext = null, string $limit = -1) 查询文件-获取列表
 * @method static mixed moveDirectory(mixed $oldpath, mixed $newpath) 处理文件-移动目录
 * @method static mixed deleteDirectory(mixed $path) 处理文件-删除目录
 * @method static mixed getMediaInfo(mixed $var, string $type) 查询文件-获取媒体信息
 */
class File extends \fxyin\Facade
{
}
