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

/**
 * 校验Json
 * @param string $data 数据
 * @return mixed
 */
function is_json($data)
{
    // 初始化变量
    if (!is_string($data)) return false;
    $data = json_decode($data, true);
    if (null !== $data && is_array($data)) return true;
    return false;
}

/**
 * 校验空白
 * @param string $data 数据
 * @return mixed
 */
function is_blank($data)
{
    // 初始化变量
    return is_null($data) || $data === '';
}

/**
 * 校验时间
 * @param string $time 时间
 * @param string $format 格式
 * @return bool
 */
function is_time($time, $format = 'Y-m-d H:i:s')
{
    // 校验转时间戳
    if (false === ($times = strtotime($time))) {
        return $times;
    }
    // 校验格式化时间
    return date($format, $times) == $time;
}

/**
 * 校验函数
 * @param object $data 数据
 * @return boolean
 */
function is_function($data)
{
    // 初始化变量
    return is_object($data) && is_callable($data);
}

/**
 * 校验手机
 * @param string $data 数据
 * @return boolean
 */
function is_mobile($data)
{
    // 初始化变量
    if (!is_string($data) || empty($data)) return false;
    return preg_match('/^1\d{10}$/', $data);
}

/**
 * 校验邮箱
 * @param string $data 数据
 * @return boolean
 */
function is_email($data)
{
    // 初始化变量
    if (!is_string($data) || empty($data)) return false;
    return preg_match('/^[a-z0-9]+([\+_\-\.]?[a-z0-9]+)*@([a-z0-9]+[\-]?[a-z0-9]+\.)+[a-z]{2,6}$/i', $data);
}

/**
 * 校验邮政编码
 * @param string $data 数据
 * @return boolean
 */
function is_zipcode($data)
{
    // 初始化变量
    if (!is_string($data) || empty($data)) return false;
    return preg_match('/^[1-9][0-9]{5}$/', $data);
}

/**
 * 校验IPv4
 * @param string $data 数据
 * @return boolean
 */
function is_ipv4($data)
{
    // 初始化变量
    return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * 校验IPv6
 * @param string $data 数据
 * @return boolean
 */
function is_ipv6($data)
{
    // 初始化变量
    return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}

/**
 * 校验URL
 * @param string $data 数据
 * @return boolean
 */
function is_url($data)
{
    // 初始化变量
    return filter_var($data, FILTER_VALIDATE_URL) !== false;
}

/**
 * 校验IP
 * @param string $data 数据
 * @return boolean
 */
function is_ip($data)
{
    // 初始化变量
    return is_ipv4($data) || is_ipv6($data);
}

/**
 * 语言
 * @param array|string $name 语言变量名
 * @param array $vars 动态变量值
 * @param string $lang 语言
 * @return string
 */
function fxy_lang($name, $vars = [], $lang = '')
{
    // 初始化变量
    return \fxapp\Base::lang($name, $vars, $lang);
}

/**
 * 配置参数-[获取|设置]
 * @param array $vars 参数
 * @return mixed
 */
function fxy_config(...$vars)
{
    // 初始化变量
    return \fxapp\Base::config(...$vars);
}

/**
 * 配置Cookie-[获取|设置]
 * @param array $vars 参数
 * @return mixed
 */
function fxy_cookie(...$vars)
{
    // 初始化变量
    return \fxapp\Base::cookie(...$vars);
}

/**
 * 环境参数-[获取|设置]
 * @param array $vars 参数
 * @return mixed
 */
function fxy_env(...$vars)
{
    // 初始化变量
    return \fxapp\Base::env(...$vars);
}

/**
 * 浏览器友好的变量输出
 * @param array $vars 要输出的变量
 * @return void
 */
function fxy_dump(...$vars)
{
    // 初始化变量
    \fxapp\Base::dump(...$vars);
}

/**
 * 解析Json
 * @param mixed $var 变量
 * @param string $type 类型
 * @param mixed $param 参数
 * @return mixed
 */
function fxy_json($var, $type, $param = null)
{
    // 初始化变量
    return \fxapp\Base::json($var, $type, $param);
}

/**
 * 配置响应
 * @param string $message 提示信息
 * @param int|null $code 状态代码
 * @param array $data 响应数据
 * @param array $extend 扩展数据
 * @return array
 */
function fxy_echo($message, $code = null, $data = [], $extend = [])
{
    // 初始化变量
    return \fxapp\Base::echo($message, $code, $data, $extend);
}
