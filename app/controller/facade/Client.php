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

namespace fxapp\facade;

/**
 * 客户端类
 */
class Client
{
    /**
     * 获取IP
     * @param int $type 类型
     * @return mixed
     */
    public function ip($type = 0)
    {
        // 初始化变量
        $type = $type ? 1 : 0;
        static $ip = null;
        if ($ip !== null) return $ip[$type];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) unset($arr[$pos]);
            $ip = trim($arr[0]);
        } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf('%u', \fxapp\Text::ipv4($ip, 'encode'));
        $ip = $long ? [$ip, $long] : ['0.0.0.0', 0];
        return $ip[$type];
    }

    /**
     * 获取浏览器
     * @param int $type 类型
     * @return mixed
     */
    public function browser($type = -1)
    {
        // 初始化变量
        $echo = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        // 开始检测
        if (strpos($echo, strtolower('MicroMessenger'))) {
            // 微信内置浏览器
            $echo = 'MicroMessenger';
        } else if (strpos($echo, strtolower('Alipay'))) {
            // 支付宝内置浏览器
            $echo = 'Alipay';
        } else if (strpos($echo, strtolower('MSIE 8.0'))) {
            // IE8.0
            $echo = 'Internet Explorer 8.0';
        } else if (strpos($echo, strtolower('MSIE 7.0'))) {
            // IE7.0
            $echo = 'Internet Explorer 7.0';
        } else if (strpos($echo, strtolower('MSIE 6.0'))) {
            // IE6.0
            $echo = 'Internet Explorer 6.0';
        } else if (strpos($echo, strtolower('Firefox/3'))) {
            // 火狐浏览器
            $echo = 'Firefox 3';
        } else if (strpos($echo, strtolower('Firefox/2'))) {
            // 火狐浏览器
            $echo = 'Firefox 2';
        } else if (strpos($echo, strtolower('Chrome'))) {
            // 谷歌浏览器
            $echo = 'Google Chrome';
        } else if (strpos($echo, strtolower('Safari'))) {
            // 游猎浏览器
            $echo = 'Safari';
        } else if (strpos($echo, strtolower('Opera'))) {
            // 欧朋浏览器
            $echo = 'Opera';
        } else {
            // 其他
            $echo = 'Others';
        }
        switch ($type) {
            case 1:
                // 格式化-小写
                $echo = strtolower($echo);
                break;
            case 2:
                // 格式化-大写
                $echo = strtoupper($echo);
                break;
        }
        return $echo;
    }

    /**
     * 获取系统
     * @param int $type 类型
     * @return mixed
     */
    public function system($type = -1)
    {
        // 初始化变量
        $echo = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        // 开始检测
        if (preg_match('/win/i', $echo) && preg_match('/95/i', $echo)) {
            $echo = 'Windows 95';
        } else if (preg_match('/win 9x/i', $echo) && preg_match('/4.90/i', $echo)) {
            $echo = 'Windows ME';
        } else if (preg_match('/win/i', $echo) && preg_match('/98/i', $echo)) {
            $echo = 'Windows 98';
        } else if (preg_match('/win/i', $echo) && preg_match('/nt 5.1/i', $echo)) {
            $echo = 'Windows XP';
        } else if (preg_match('/win/i', $echo) && preg_match('/nt 5.2/i', $echo)) {
            $echo = 'Windows 2003';
        } else if (preg_match('/win/i', $echo) && preg_match('/nt 5/i', $echo)) {
            $echo = 'Windows 2000';
        } else if (preg_match('/win/i', $echo) && preg_match('/nt/i', $echo)) {
            $echo = 'Windows NT';
        } else if (preg_match('/win/i', $echo) && preg_match('/32/i', $echo)) {
            $echo = 'Windows 32';
        } else if (preg_match('/linux/i', $echo)) {
            $echo = 'Linux';
        } else if (preg_match('/unix/i', $echo)) {
            $echo = 'Unix';
        } else if (preg_match('/sun/i', $echo) && preg_match('/os/i', $echo)) {
            $echo = 'SunOS';
        } else if (preg_match('/ibm/i', $echo) && preg_match('/os/i', $echo)) {
            $echo = 'IBM OS/2';
        } else if (preg_match('/Mac/i', $echo) && preg_match('/PC/i', $echo)) {
            $echo = 'Macintosh';
        } else if (preg_match('/PowerPC/i', $echo)) {
            $echo = 'PowerPC';
        } else if (preg_match('/AIX/i', $echo)) {
            $echo = 'AIX';
        } else if (preg_match('/HPUX/i', $echo)) {
            $echo = 'HPUX';
        } else if (preg_match('/NetBSD/i', $echo)) {
            $echo = 'NetBSD';
        } else if (preg_match('/BSD/i', $echo)) {
            $echo = 'BSD';
        } else if (preg_match('/OSF1/i', $echo)) {
            $echo = 'OSF1';
        } else if (preg_match('/IRIX/i', $echo)) {
            $echo = 'IRIX';
        } else if (preg_match('/FreeBSD/i', $echo)) {
            $echo = 'FreeBSD';
        } else if (preg_match('/teleport/i', $echo)) {
            $echo = 'teleport';
        } else if (preg_match('/flashget/i', $echo)) {
            $echo = 'flashget';
        } else if (preg_match('/webzip/i', $echo)) {
            $echo = 'webzip';
        } else if (preg_match('/offline/i', $echo)) {
            $echo = 'offline';
        } else {
            $echo = 'Others';
        }
        switch ($type) {
            case 1:
                // 格式化-小写
                $echo = strtolower($echo);
                break;
            case 2:
                // 格式化-大写
                $echo = strtoupper($echo);
                break;
        }
        return $echo;
    }

    /**
     * 获取请求参数
     * @param mixed $param 参数
     * @param string $method 方法
     * @return mixed
     */
    public function param($param = null, $method = 'param')
    {
        // 初始化变量
        $echo = [];
        $input = file_get_contents('php://input');
        $predefined = [
            // GET
            'get' => $_GET,
            // POST
            'post' => $_POST,
            // INPUT
            'input' => $input,
            // CLI
            'cli' => fxy_config('app.param.cli'),
        ];
        $base = \fxapp\Param::define([null, $predefined], '1.1.3');
        // 识别方法
        $method = \fxapp\Text::explode(',', strtolower($method));
        foreach ($method as $value) {
            switch ($value) {
                case 'param':
                    // 全部入参
                    $echo = array_merge($echo, ...array_values($base));
                    break;
                case 'get':
                case 'post':
                case 'input':
                case 'cli':
                    // 提取入参
                    $echo = array_merge($echo, $base[$value]);
                    break;
                case 'put':
                case 'patch':
                case 'delete':
                    // 提取入参
                    $echo = array_merge($echo, $base['input']);
                    break;
            }
        }
        if (is_array($param)) {
            // 获取指定参数数组
            $echo = \fxapp\Param::pick($echo, $param);
        } else if (is_string($param)) {
            // 获取指定参数
            $echo = $echo[$param] ?? null;
        }
        return $echo;
    }
}
