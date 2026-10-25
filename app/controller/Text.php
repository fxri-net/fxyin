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
 * 文本类
 * @see \fxapp\facade\Text
 * @package fxapp\facade
 * @method static array uuid(?string $mode = null, array $param = []) 生成UUID
 * @method static array|string implode(string $delimiter, mixed $data, $explode = false, boolean $unique = true) 组合字符串
 * @method static array explode(string $delimiter, mixed $data, boolean $unique = true, int $limit = PHP_INT_MAX) 打散字符串
 * @method static string splice(string $string, string $value, string $delimiter = '') 拼接字符串
 * @method static mixed strlen(?string $string = null, int $start = 0, int $end = 0) 检查字符串长度
 * @method static string letters(string $var, string $in_charset = 'utf-8', string $out_charset = 'gb2312') 首字母组
 * @method static string letter(string $var, string $in_charset = 'utf-8', string $out_charset = 'gb2312') 首字母
 * @method static string timeRange(array|string $time, array $start = [], array $end = []) 处理时间-范围
 * @method static string timeChange(int|string $time, ?string $type = null, ?string $format = null) 处理时间-转换
 * @method static string timeMilli(?string $mtime = null) 处理时间-毫秒
 * @method static string timeFormat(?int $time = null, array $param = []) 处理时间-格式化
 * @method static string strEncode(array $data) 处理字符串-编码
 * @method static string strEncodeMerge(array $data) 处理字符串-编码-合并数组
 * @method static array strDecode(string $data) 处理字符串-解码
 * @method static string strDecodeMerge(array $data) 处理字符串-解码-合并数组
 * @method static mixed ipv4(mixed $data, string $type) 解析IPv4
 * @method static mixed convert(mixed $data, string $type) 进制转换
 * @method static mixed throwable(\Throwable $th, string $type, array $param) 提取抛出
 */
class Text extends \fxyin\Facade
{
}
