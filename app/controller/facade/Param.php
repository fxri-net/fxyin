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

namespace fxapp\facade;

/**
 * 参数类
 */
class Param
{
    /**
     * 初始化配置
     * @return mixed
     */
    public function init()
    {
        // 初始化CLI入参
        $param = $_SERVER['argv'] ?? [];
        $data = [];
        foreach ($param as $key => $name) {
            if (strpos($name, '@') !== 0) continue;
            $name = preg_replace('/^@/', '', $name);
            $name = explode('=', $name, 2);
            $name[1] = $name[1] ?? null;
            // 解析名称
            $name[0] = array_reverse(explode('.', $name[0]));
            foreach ($name[0] as $elem) {
                $elem = str_replace('/_', '.', $elem);
                $name[1] = [$elem => $name[1]];
            }
            // 融合数据
            $data = $this->merge($data, $name[1]);
            // 移除入参
            unset($param[$key]);
        }
        // 疏理CLI入参
        $_SERVER['argv'] = array_values($param);
        fxy_config('app.param.cli', $data);
    }

    /**
     * 定义参数
     * @param array $param 参数
     * @param int $mode 模式
     * @return array
     */
    public function define($param, $mode = null)
    {
        // 初始化变量
        $echo = [];
        switch ($mode) {
            default:
                // 默认
                $echo = $param;
                break;
            case '1.1.1':
                // 数组覆盖-融合-值不存在
                // $predefined = [
                //     'data' => null,
                // ];
                // data => mixed
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!isset($echo[$key])) {
                        $echo[$key] = $value;
                    }
                }
                break;
            case '1.1.2':
                // 数组覆盖-融合-值不存在或为空字符串
                // $predefined = [
                //     'data' => null,
                // ];
                // data => mixed
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!isset($echo[$key]) || '' === $echo[$key]) {
                        $echo[$key] = $value;
                    }
                }
                break;
            case '1.1.3':
                // 数组覆盖-融合-值不存在或为非数组
                // $predefined = [
                //     'data' => null,
                // ];
                // data => array
                // data => json
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!isset($echo[$key])) {
                        $echo[$key] = $value;
                    }
                }
                foreach ($param[1] as $key => $value) {
                    $echo[$key] = fxy_json($echo[$key], 'decode');
                }
                break;
            case '1.2.1':
                // 数组覆盖-赋空-值不存在
                // $predefined = [
                //     'data',
                // ];
                // data => mixed
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!isset($echo[$value])) {
                        $echo[$value] = null;
                    }
                }
                break;
            case '1.2.2':
                // 数组覆盖-赋空-值不存在或为空字符串
                // $predefined = [
                //     'data',
                // ];
                // data => mixed
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!isset($echo[$value]) || '' === $echo[$value]) {
                        $echo[$value] = null;
                    }
                }
                break;
            case '1.2.3':
                // 数组覆盖-赋空-值不存在或为非数组
                // $predefined = [
                //     'data',
                // ];
                // data => array
                // data => json
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    $echo[$value] = fxy_json($echo[$value] ?? null, 'decode');
                }
                break;
            case '1.2.4':
                // 数组覆盖-赋空-值拆分数组
                // $predefined = [
                //     'data',
                // ];
                // data => 1,2,3,4
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!isset($echo[$value]) || '' === $echo[$value]) {
                        $echo[$value] = [];
                    } else if (is_string($echo[$value]) || is_numeric($echo[$value])) {
                        $echo[$value] = explode(',', $echo[$value]);
                    } else if (!is_array($echo[$value])) {
                        $echo[$value] = [];
                    }
                }
                break;
            case '1.3.1':
                // 数组覆盖-倒融合-值不存在
                // $predefined = [
                //     'data' => null,
                // ];
                // data => mixed
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!is_null($value)) {
                        $echo[$key] = $value;
                    }
                }
                break;
            case '1.3.2':
                // 数组覆盖-倒融合-赋空-值不存在
                // $predefined = [
                //     'data' => null,
                // ];
                // data => mixed
                $echo = $param[0];
                foreach ($param[1] as $key => $value) {
                    if (!is_null($value)) {
                        $echo[$key] = $value;
                    } else if (!isset($echo[$key]) || '' === $echo[$key]) {
                        $echo[$key] = null;
                    }
                }
                break;
            case '2.1.1':
                // 数组新建-融合-值不存在
                // $predefined = [
                //     'data' => null,
                // ];
                // data => mixed
                foreach ($param[1] as $key => $value) {
                    if (!isset($param[0][$key])) {
                        $echo[$key] = $value;
                    } else {
                        $echo[$key] = $param[0][$key];
                    }
                }
                break;
            case '2.1.2':
                // 数组新建-融合-值不存在或为空字符串
                // $predefined = [
                //     'data' => null,
                // ];
                // data => mixed
                foreach ($param[1] as $key => $value) {
                    if (!isset($param[0][$key]) || '' === $param[0][$key]) {
                        $echo[$key] = $value;
                    } else {
                        $echo[$key] = $param[0][$key];
                    }
                }
                break;
            case '2.2.1':
                // 数组新建-赋空-值不存在
                // $predefined = [
                //     'data',
                // ];
                // data => mixed
                foreach ($param[1] as $key => $value) {
                    if (!isset($param[0][$value])) {
                        $echo[$value] = null;
                    } else {
                        $echo[$value] = $param[0][$value];
                    }
                }
                break;
            case '2.2.2':
                // 数组新建-赋空-值不存在或为空字符串
                // $predefined = [
                //     'data',
                // ];
                // data => mixed
                foreach ($param[1] as $key => $value) {
                    if (!isset($param[0][$value]) || '' === $param[0][$value]) {
                        $echo[$value] = null;
                    } else {
                        $echo[$value] = $param[0][$value];
                    }
                }
                break;
        }
        return $echo;
    }

    /**
     * 数组填充空值
     * @param array $param 参数
     * @param int $limit 次数限制
     * @param bool $filter 过滤器
     * @return array
     */
    public function array($param, int $limit = -1, bool $filter = true)
    {
        // 初始化变量
        if (!is_array($param) || $limit === 0) {
            return $param;
        }
        if ($limit > 0) {
            $limit--;
        }
        // 处理数组
        foreach ($param as $key => $value) {
            $param[$key] = $this->array($value, $limit, $filter);
        }
        if ($filter) {
            $param[''] = null;
        }
        return $param;
    }

    /**
     * 空数组转对象
     * @param array $param 参数
     * @param int $limit 次数限制
     * @param bool $filter 过滤器
     * @return mixed
     */
    public function object($param, int $limit = -1, bool $filter = true)
    {
        // 初始化变量
        if (!is_array($param) || !count($param) || $limit === 0) {
            return $param;
        }
        if ($limit > 0) {
            $limit--;
        }
        // 过滤空元素
        if (!isset($param[''])) {
            unset($param['']);
        }
        // 处理数组
        if (count($param)) {
            foreach ($param as $key => $value) {
                $param[$key] = $this->object($value, $limit, $filter);
            }
        } else if ($filter) {
            $param = new \StdClass();
        }
        return $param;
    }

    /**
     * 提取数组
     * @param array $base 基础
     * @param array $data 数据
     * @return array
     */
    public function pick($base, $data)
    {
        // 初始化变量
        $echo = [];
        if (!is_array($base)) {
            $base = [];
        }
        if (!is_array($data)) {
            return $echo;
        }
        // 处理数据
        foreach ($data as $key => $value) {
            // 提取匹配数据
            if (is_array($value)) {
                if (array_key_exists($key, $base)) {
                    $echo[$key] = $this->pick($base[$key], $value);
                } else {
                    $echo[$key] = $this->pick([], $value);
                }
            } else {
                if (array_key_exists($value, $base)) {
                    $echo[$value] = $base[$value];
                } else {
                    $echo[$value] = null;
                }
            }
        }
        return $echo;
    }

    /**
     * 追加数组
     * @param array $args 数组集合
     * @return mixed
     */
    public function append(...$args)
    {
        // 初始化变量
        $echo = array_shift($args);
        if (!is_array($echo)) {
            $echo = [];
        }
        // 追加数组
        foreach ($args as $data) {
            if (!is_array($data)) continue;
            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    $echo[$key] = $value;
                } else {
                    $echo[] = $value;
                }
            }
        }
        return $echo;
    }

    /**
     * 合并数组
     * @param array|int $limit 次数限制或数组第1条
     * @param boolean|string $cover 覆盖或数组第2条
     * @param boolean $type 类型或数组第3条
     * @param array $args 数组集合
     * @return mixed
     */
    public function merge($limit = -1, $cover = true, $type = false, ...$args)
    {
        // 初始化变量
        $echo = [];
        // 疏理类型
        if (!is_bool($type)) {
            array_unshift($args, $type);
            $type = false;
        }
        // 疏理覆盖
        if (!is_bool($cover) && !is_string($cover)) {
            array_unshift($args, $cover);
            $cover = true;
        }
        // 疏理限制
        if (!is_int($limit)) {
            array_unshift($args, $limit);
            $limit = -1;
        }
        if (count($args) < 2) {
            return array_shift($args);
        } else if (count($args) > 2) {
            $echo[0] = array_shift($args);
            $echo[1] = $this->merge($limit, $cover, $type, ...$args);
        } else {
            $echo = $args;
        }
        return $this->cover($echo, $limit, $cover, $type);
    }

    /**
     * 覆盖数组
     * @param array $args 数组集合
     * @param int $limit 次数限制
     * @param boolean|string $cover 覆盖
     * @param boolean $type 类型
     * @return array
     */
    public function cover($args, int $limit = -1, $cover = true, $type = false)
    {
        // 初始化变量
        if (!is_array($args[0]) || $limit === 0) {
            $args[0] = $args[1];
        } else if (is_array($args[1])) {
            if ($limit > 0) {
                $limit--;
            }
            // 数组融合，已存在配置参数则覆盖
            foreach ($args[1] as $key => $value) {
                if (!isset($args[0][$key])) {
                    $args[0][$key] = $value;
                } else if (is_array($value)) {
                    $args[0][$key] = $this->cover([$args[0][$key], $value], $limit, $cover, $type);
                } else if ($cover === true) {
                    $args[0][$key] = $value;
                } else if ($cover == 'blank' && is_blank($args[0][$key])) {
                    $args[0][$key] = $value;
                }
                // 转换类型
                if (!$type) continue;
                // 识别字段类型
                switch (gettype($value)) {
                    case 'integer':
                        // 整型
                        $args[0][$key] = (int) $args[0][$key];
                        break;
                    case 'double':
                        // 浮点型
                        $args[0][$key] = (float) $args[0][$key];
                        break;
                }
            }
        }
        return $args[0];
    }

    /**
     * 设置/获取URL
     * @param array $data 数据
     * @return mixed
     */
    public function url($data)
    {
        // 初始化变量
        $predefined = [
            // 类型
            'type' => null,
            // 地址
            'url' => null,
            // 名称
            'name' => null,
        ];
        $data = $this->define([$data, $predefined], '1.1.1');
        $predefined = [
            // 参数
            'param' => [],
        ];
        $data = $this->define([$data, $predefined], '1.1.3');
        switch ($data['type']) {
            case '1.1':
                // 组装地址
                $data['param'] = $this->merge($this->url([
                    'type' => '2.1',
                ]), $data['param']);
            case '1.2':
                // 组装地址
                // 解析地址
                $data['url'] = !is_blank($data['url']) ? $data['url'] : fxy_env('base.uri');
                $data['url'] = explode('?', $data['url'], 2);
                $data['url'][1] = $this->merge(\fxapp\Text::strDecode($data['url'][1] ?? null), \fxapp\Text::strDecode($data['param']));
                // 疏理参数
                if (!empty($data['url'][1])) {
                    $data['url'][1] = \fxapp\Text::strEncode($data['url'][1]);
                } else {
                    unset($data['url'][1]);
                }
                // 输出地址
                return implode('?', $data['url']);
            case '2.1':
                // 获取参数
                // 解析地址
                $data['url'] = !is_blank($data['url']) ? $data['url'] : fxy_env('base.web') . fxy_env('base.uri');
                $data['url'] = explode('?', $data['url'], 2);
                $data['url'][1] = $this->merge(\fxapp\Text::strDecode($data['url'][1] ?? null), \fxapp\Text::strDecode($data['param']));
                // 获取参数
                if (!is_blank($data['name'])) {
                    return $data['url'][1][$data['name']] ?? null;
                }
                // 输出参数
                return $data['url'][1];
        }
    }

    /**
     * 解析Json
     * @param mixed $var 变量
     * @param string $type 类型
     * @param mixed $param 参数
     * @return mixed
     */
    public function json($var, $type, $param = null)
    {
        // 初始化变量
        $echo = null;
        $type = strtolower($type);
        switch ($type) {
            case 'encode':
                // 编码
                if (is_null($param)) {
                    $param = JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES;
                }
                $echo = json_encode($var, $param);
                break;
            case 'decode':
                // 解码
                if (is_null($param)) {
                    $param = true;
                }
                $echo = json_decode($var, $param);
                break;
        }
        return $echo;
    }
}
