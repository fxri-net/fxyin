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
 * 基础类
 */
class Base
{
    /**
     * 加载文件
     * @param string $file 路径
     * @param string $type 类型
     * @return boolean
     */
    public function load($file, $type = null)
    {
        static $_file = [];
        $file = realpath($file);
        if (!isset($_file[$file])) {
            if (!is_file($file)) {
                $_file[$file] = false;
            } else if ($type == 'data') {
                $_file[$file] = require $file;
            } else {
                require $file;
                $_file[$file] = true;
            }
        }
        return $_file[$file];
    }

    /**
     * 语言
     * @param array|string $name 语言变量名
     * @param array $vars 动态变量值
     * @param string $lang 语言
     * @return string
     */
    public function lang($name, $vars = [], $lang = '')
    {
        $langs = $this->langList($name, $vars, $lang);
        $echo = $this->langParse($langs);
        return ucfirst($echo);
    }

    /**
     * 语言-列表
     * @param array|string $name 语言变量名
     * @param array $vars 动态变量值
     * @param string $lang 语言
     * @return string
     */
    public function langList($name, $vars = [], $lang = '')
    {
        $langs = [];
        $lang = \fxyin\Lang::range();
        if (is_array($name)) {
            foreach ($name as $value) {
                $langs[] = $this->langList($value, $vars, $lang);
            }
        } else {
            if (is_null($name)) {
                $langs = 'null';
            } else if (strpos($name, $this->config('app.lang.prefix')) === 0) {
                $langs = ltrim($name, $this->config('app.lang.prefix'));
            } else if (strpos($name, $this->config('app.lang.ignore')) === 0) {
                $langs = '';
            } else {
                if (is_numeric($name)) {
                    $name = strval($name);
                }
                $langs = \fxyin\Lang::get($name, $vars, $lang);
            }
        }
        return $langs;
    }

    /**
     * 语言-解析
     * @param array|string $name 语言变量名
     * @return string
     */
    public function langParse($name)
    {
        $type = \fxyin\Lang::range();
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                if (is_array($value)) {
                    $name[$key] = $this->langParse($value);
                }
            }
            switch ($type) {
                default:
                case 'en-us':
                    // 英语（美国）
                    $name = implode(' ', $name);
                    break;
                case 'zh-cn':
                    // 中文（简体）
                    $name = implode('', $name);
                    break;
            }
        }
        return $name;
    }

    /**
     * 配置参数-[获取|设置]
     * @param array $vars 参数
     * @return mixed
     */
    public function config(...$vars)
    {
        $vars[0] = $vars[0] ?? null;
        if (!is_array($vars[0]) && !array_key_exists(1, $vars)) {
            return 0 === strpos($vars[0] ?? '', '?') ? \fxyin\Config::has(substr($vars[0], 1)) : \fxyin\Config::get($vars[0]);
        } else {
            $vars[1] = $vars[1] ?? null;
            return \fxyin\Config::set($vars[0], $vars[1]);
        }
    }

    /**
     * 配置Cookie-[获取|设置]
     * @param array $vars 参数
     * @return mixed
     */
    public function cookie(...$vars)
    {
        $vars[0] = $vars[0] ?? null;
        if (!is_array($vars[0]) && !array_key_exists(1, $vars)) {
            return \fxyin\Cookie::get($vars[0]);
        } else {
            $vars[1] = $vars[1] ?? null;
            return \fxyin\Cookie::set(...$vars);
        }
    }

    /**
     * 环境参数-[获取|设置]
     * @param array $vars 参数
     * @return mixed
     */
    public function env(...$vars)
    {
        $vars[0] = $vars[0] ?? null;
        if (!is_array($vars[0]) && !array_key_exists(1, $vars)) {
            return 0 === strpos($vars[0] ?? '', '?') ? \fxyin\Env::has(substr($vars[0], 1)) : \fxyin\Env::get($vars[0]);
        } else {
            $vars[1] = $vars[1] ?? null;
            return \fxyin\Env::set($vars[0], $vars[1]);
        }
    }

    /**
     * 浏览器友好的变量输出
     * @param array $vars 要输出的变量
     * @return void
     */
    public function dump(...$vars)
    {
        // 提取数据
        ob_start();
        var_dump(...$vars);
        $output = ob_get_clean();
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
        // 处理数据
        if (PHP_SAPI == 'cli') {
            $output = PHP_EOL . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, ENT_SUBSTITUTE);
            }
            $output = '<pre>' . $output . '</pre>';
        }
        // 输出数据
        echo $output;
    }

    /**
     * 解析Json
     * @param mixed $data 数据
     * @param string $type 类型
     * @param mixed $param 参数
     * @return mixed
     */
    public function json($data, $type, $param = null)
    {
        // 初始化变量
        $type = strtolower($type);
        switch ($type) {
            case 'encode':
                // 编码
                if (!is_json($data)) {
                    $data = \fxapp\Param::json($data, $type, $param);
                }
                break;
            case 'decode':
                // 解码
                if (is_json($data)) {
                    $data = \fxapp\Param::json($data, $type, $param);
                } else if (is_object($data)) {
                    $data = (array) $data;
                } else if (is_string($data)) {
                    $data = \fxapp\Text::strDecode($data);
                } else if (!is_array($data)) {
                    $data = !is_null($data) ? [$data] : [];
                }
                break;
        }
        return $data;
    }

    /**
     * 解析IPv4
     * @param mixed $data 数据
     * @param string $type 类型
     * @return mixed
     */
    public function ipv4($data, $type)
    {
        // 初始化变量
        $type = strtolower($type);
        switch ($type) {
            case 'encode':
                // 编码
                if (is_ipv4($data)) {
                    $data = \fxapp\Text::ipv4($data, $type);
                }
                break;
            case 'decode':
                // 解码
                if (!is_ipv4($data)) {
                    $data = \fxapp\Text::ipv4($data, $type);
                }
                break;
        }
        return $data;
    }

    /**
     * 加密解密
     * @param mixed $data 数据
     * @param string $type 类型
     * @param string $param 参数
     * @return mixed
     */
    public function crypt($data, $type, $param = null)
    {
        // 初始化变量
        $config = $this->config('safe.base');
        if (!$config['crypt_switch']) return $data;
        if (!is_array($param)) {
            $param = null;
        }
        $predefined = [
            'method' => $config['crypt_method'], 'password' => $config['crypt_key'], 'options' => $config['crypt_options'],
            'iv' => $config['crypt_iv'],
        ];
        $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
        $type = strtolower($type);
        switch ($type) {
            default:
                // 默认
                $data = null;
                break;
            case 'encode':
                // 编码
                $data = \fxapp\Safe::crypt($data, 'encode', $param);
                break;
            case 'decode':
                // 解码
                $data = \fxapp\Safe::crypt($data, 'decode', $param);
                break;
        }
        return $data;
    }

    /**
     * 抛出异常
     * @param string $message 消息
     * @return void
     */
    public function throwable($message)
    {
        try {
            throw new \Exception($message);
        } catch (\Throwable $th) {
            // 执行异常处理
            if (true !== fxy_config('app.debug.switch')) {
                $echo = '系统异常';
            } else if (fxy_env('base.method') == 'get') {
                $echo = \fxapp\Text::throwable($th, '1.1');
            } else if (PHP_SAPI !== 'cli') {
                $echo = \fxapp\Server::echo();
                $echo[0] = false;
                $echo[1] = 0;
                $echo[2] = \fxapp\Text::throwable($th, '1.1');
                header('Content-Type:application/json; charset=utf-8');
                $echo = fxy_json(\fxapp\Server::format($echo, 2), 'encode');
            } else {
                throw $th;
            }
        }
        exit($echo);
    }

    /**
     * 配置响应
     * @param string $message 提示信息
     * @param int|null $code 状态代码
     * @param array $data 响应数据
     * @param array $extend 扩展数据
     * @return array
     */
    public function echo($message, $code = null, $data = [], $extend = [])
    {
        // 初始化变量
        $echo = \fxapp\Server::echo();
        // 疏理输出
        if (!is_null($code) && $echo[1] != $code) {
            $echo[0] = false;
            $echo[1] = $code;
        }
        $echo[2] = $message;
        $echo[3] = $data;
        $echo[4] = (object) $extend;
        $echo[5] = strval(time());
        return $echo;
    }
}
