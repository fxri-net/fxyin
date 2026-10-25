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
 * 数据类
 */
class Data
{
    /**
     * SQL-条件
     * @param array $data 数据
     * @param array $param 参数
     * @return array
     */
    public function where($data, $param)
    {
        // 初始化变量
        $predefined = fxy_config('facade.data.where');
        $param = \fxapp\Param::define([$param, $predefined], '1.1.1');
        $predefined = [
            // 表达式
            'build' => $param[-1],
            // 键名
            'key' => $param[0],
            // 键值
            'value' => $param[1],
            // 方法
            'method' => $param[2],
            // 逻辑
            'logic' => $param[3],
            // 分组
            'group' => $param[4],
            // 取反
            'not' => $param[5],
            // 大小写敏感
            'case' => $param[6],
        ];
        $param = \fxapp\Param::define([$param, $predefined], '1.1.1');
        if (is_null($param['build'])) {
            if (!is_array($data)) {
                return $data;
            }
            // 过滤非法字符
            $param['value'] = $this->whereSafe($param['value'], $param['method'], $param['case']);
            if (false === $param['value']) {
                return $data;
            }
            // 搭建表达式
            $param['build'] = $this->whereBuild($param['key'], $param['value'], $param['method']);
        }
        $elem = [
            // 表达式
            'build' => $param['build'],
            // 逻辑
            'logic' => $param['logic'],
            // 分组
            'group' => $param['group'],
            // 取反
            'not' => $param['not'],
        ];
        $data[] = $elem;
        return $data;
    }

    /**
     * SQL-条件安检
     * @param string $value 键值
     * @param string $method 方法
     * @param int $case 大小写敏感
     * @return string
     */
    public function whereSafe($value, &$method, $case = 0)
    {
        // 初始化变量
        if (!(is_string($value) || is_numeric($value) || is_array($value) || is_object($value))) {
            return false;
        } else if (is_null($method)) {
            return $value;
        }
        // 条件过滤
        $value = $this->whereFilter($value, $method, $case);
        // 解析方法
        switch ($method) {
            case 'like fuzzy':
                // 模糊匹配
                $method = 'like';
                break;
            case 'not like fuzzy':
                // 模糊匹配-取反
                $method = 'not like';
                break;
        }
        return $value;
    }

    /**
     * SQL-条件过滤
     * @param string $value 键值
     * @param string $method 方法
     * @param int $case 大小写敏感
     * @return string
     */
    public function whereFilter($value, $method, $case = 0)
    {
        // 初始化变量
        if (!(is_string($value) || is_numeric($value) || is_array($value) || is_object($value))) {
            return false;
        } else if (is_null($method)) {
            return $value;
        }
        if (is_numeric($value)) {
            $value = (string) $value;
        } else if (is_object($value)) {
            $value = (array) $value;
        }
        // 拆解方法
        switch ($method) {
            default:
                // 默认
                if (!is_array($value)) {
                    $value = [$value];
                }
                break;
            case 'in':
                // 批量
            case 'not in':
                // 批量-取反
                $value = \fxapp\Text::implode(',', $value, true, false);
                break;
            case 'like':
                // 匹配
            case 'not like':
                // 匹配-取反
            case 'like fuzzy':
                // 模糊匹配
            case 'not like fuzzy':
                // 模糊匹配-取反
                if (!is_array($value)) {
                    $value = [$value];
                }
                break;
            case 'between':
                // 范围
            case 'not between':
                // 范围-取反
                if (is_string($value) && mb_strpos($value, ' and ', 0, 'utf-8') !== false) {
                    $value = explode(' and ', $value);
                } else if (!is_array($value)) {
                    $value = \fxapp\Text::implode(',', $value, true, false);
                }
                if (count($value) < 2) {
                    return false;
                }
                break;
            case 'find_in_set':
                // 批量
                $value = \fxapp\Text::implode(',', $value, true, false);
                break;
            case 'json array':
                // JSON-数组
                break;
            case 'json object':
                // JSON-对象
                $value = \fxapp\Text::implode(',', $value, true, false);
                break;
        }
        // 过滤键值
        $search = ['\\', '\'', '"'];
        $replace = ['\\\\', '\\\'', '\\"'];
        foreach ($value as $index => $elem) {
            // 包装键值
            switch ($method) {
                case 'like fuzzy':
                    // 模糊匹配
                case 'not like fuzzy':
                    // 模糊匹配-取反
                    $search = array_merge($search, ['/', '%', '_']);
                    $replace = array_merge($replace, ['//', '/%', '/_']);
                    break;
            }
            // 过滤键值
            foreach ($search as $key2 => $value2) {
                $elem = str_replace($value2, $replace[$key2], $elem);
            }
            // 包装键值
            switch ($method) {
                default:
                    // 默认
                    $elem = '\'' . $elem . '\'';
                    break;
                case 'like fuzzy':
                    // 模糊匹配
                case 'not like fuzzy':
                    // 模糊匹配-取反
                    $elem = '\'%' . $elem . '%\'';
                    break;
                case 'field':
                    // 字段
                case 'json array':
                    // JSON-数组
                case 'json object':
                    // JSON-对象
                    break;
            }
            $value[$index] = $elem;
        }
        // 组装方法
        $tray['delimiter'] = '';
        $tray['tail'] = '';
        switch ($method) {
            default:
                // 默认
                break;
            case 'in':
                // 批量
            case 'not in':
                // 批量-取反
                if (!count($value)) {
                    $value[] = '\'\'';
                }
                $tray['delimiter'] = ',';
                break;
            case 'like':
                // 匹配
            case 'not like':
                // 匹配-取反
            case 'like fuzzy':
                // 模糊匹配
            case 'not like fuzzy':
                // 模糊匹配-取反
                $tray['tail'] = ' escape \'/\'';
                break;
            case 'between':
                // 范围
            case 'not between':
                // 范围-取反
                $tray['delimiter'] = ' and ';
                break;
            case 'find_in_set':
                // 批量
                if (!count($value)) {
                    $value[] = '\'\'';
                }
                $tray['delimiter'] = ',';
                break;
            case 'json array':
                // JSON-数组
                if (!count($value)) {
                    $value[] = '\'\'';
                }
                $case = 0;
                $tray['delimiter'] = ',';
                break;
            case 'json object':
                // JSON-对象
                if (!count($value)) {
                    $value[] = '\'\'';
                }
                if (count($value) % 2 == 1) {
                    $value[] = '\'\'';
                }
                $case = 0;
                $tray['delimiter'] = ',';
                break;
        }
        // 组装方法
        $value = array_map(function ($value) use ($case) {
            if ($case && !is_numeric(trim($value, '\'"'))) $value = 'binary ' . $value;
            return $value;
        }, $value);
        $value = implode($tray['delimiter'], $value) . $tray['tail'];
        return $value;
    }

    /**
     * SQL-条件搭建
     * @param array|string $key 键名
     * @param string $value 键值
     * @param string $method 方法
     * @return string
     */
    public function whereBuild($key, $value, $method)
    {
        // 初始化变量
        // 疏理键名
        if (!is_array($key)) {
            $key = [$key];
        }
        // 疏理字符串
        if (strpos($key[0], '->') !== false) {
            // JSON
            $key[0] = $this->fieldJson($key[0], $key[1], 1);
        } else if (isset($key[1])) {
            // 疏理替换
            if (!isset($key[1])) {
                $key[1] = null;
            } else if (is_string($key[1])) {
                $key[1] = '\'' . $key[1] . '\'';
            }
            // 默认值
            $key[0] = 'ifnull(' . $key[0] . ',' . $key[1] . ')';
        }
        // 组装函数
        switch ($method) {
            default:
                // 默认
                $echo = [$key[0], $method, $value];
                $echo = array_filter($echo, function ($value) {
                    return !is_blank($value);
                });
                $echo = implode(' ', $echo);
                break;
            case 'in':
                // 批量
            case 'not in':
                // 批量
                $echo = $key[0] . ' ' . $method . ' (' . $value . ')';
                break;
            case 'find_in_set':
                // 批量
                $value = \fxapp\Text::implode(',', $value, true, false);
                $echo = [];
                foreach ($value as $cell) {
                    $echo[] = $method . '(' . $cell . ',' . $key[0] . ')';
                }
                $echo = '(' . implode(' or ', $echo) . ')';
                break;
            case 'json array':
                // JSON-数组
                $echo = 'json_contains(' . $key[0] . ', json_array(' . $value . '))';
                break;
            case 'json object':
                // JSON-对象
                $echo = 'json_contains(' . $key[0] . ', json_object(' . $value . '))';
                break;
        }
        return $echo;
    }

    /**
     * SQL-条件装配
     * @param array $data 数据
     * @return string
     */
    public function whereAssemble($data)
    {
        // 初始化变量
        if (!is_array($data)) {
            return $data;
        }
        $echo = [];
        foreach ($data as $key => $value) {
            if (isset($value['self'])) {
                $echo[$key][] = $value['self'];
            }
            if (isset($value['child'])) {
                $value['child'] = $this->whereAssemble($value['child']);
                $echo[$key][] = $value['child'];
            }
            $echo[$key] = implode(' and ', $echo[$key]);
            $echo[$key] = '(' . $echo[$key] . ')';
        }
        $echo = implode(' and ', $echo);
        return $echo;
    }

    /**
     * SQL-条件组装
     * @param array $data 数据
     * @param int $type 类型
     * @return string
     */
    public function whereMake($data, $type = 1)
    {
        // 初始化变量
        if (!is_array($data)) {
            return $data;
        }
        // 疏理条件
        $where = [];
        foreach ($data as $key => $value) {
            $where = $this->where($where, $value);
        }
        // 打包条件
        $tray['save'] = [];
        foreach ($where as $key => $value) {
            // 校验表达式
            if (is_blank($value['build'])) continue;
            // 配置取反
            if ($value['not']) $value['build'] = '!(' . $value['build'] . ')';
            // 疏理分组
            if (isset($tray['save'][$value['group']])) {
                $tray['save'][$value['group']] .= ' ' . $value['logic'] . ' ' . $value['build'];
            } else {
                $tray['save'][$value['group']] = $value['build'];
            }
        }
        // 分拣条件
        $echo = [];
        foreach ($tray['save'] as $key => $value) {
            $key = explode('.', $key);
            $key = array_reverse($key);
            foreach ($key as $key2 => $value2) {
                $tray['echo'] = [];
                if ($key2 == 0) {
                    $tray['echo'][$value2]['self'] = $value;
                    $value = $tray['echo'];
                } else {
                    $tray['echo'][$value2]['child'] = $value;
                    $value = $tray['echo'];
                }
            }
            $echo = \fxapp\Param::merge($echo, $value);
        }
        // 装配条件
        $echo = $this->whereAssemble($echo);
        switch ($type) {
            default:
            case 1:
                // 无处理
                break;
            case 2:
                // 拼接Where
                $echo = strlen($echo) > 0 ? 'where ' . $echo : $echo;
                break;
        }
        return $echo;
    }

    /**
     * SQL-插入
     * @param string $table 表名
     * @param array $data 数据
     * @return bool|string
     */
    public function insert($table, $data)
    {
        if (!is_string($table) || !is_array($data) || count($data) < 1) {
            return false;
        }
        // 疏理表名
        $table = $this->fieldEscape($table);
        // 疏理键名
        $tray['key'] = array_keys(current($data));
        $tray['key'] = array_map(function ($value) {
            return $this->fieldEscape($value);
        }, $tray['key']);
        $tray['key'] = implode(',', $tray['key']);
        // 疏理键值
        $tray['value'] = [];
        foreach ($data as $elem) {
            $elem = array_map(function ($data) {
                // 校验空值
                if (is_null($data)) return 'null';
                // 过滤键值
                $search = ['\\', '\'', '"'];
                $replace = ['\\\\', '\\\'', '\\"'];
                foreach ($search as $key => $value) {
                    $data = str_replace($value, $replace[$key], $data);
                }
                $data = '\'' . $data . '\'';
                return $data;
            }, $elem);
            $elem = '(' . implode(',', $elem) . ')';
            $tray['value'][] = $elem;
        }
        $tray['value'] = implode(',', $tray['value']);
        // 疏理表达式
        $echo = 'insert into ' . $table . ' (' . $tray['key'] . ')' . ' values' . $tray['value'];
        return $echo;
    }

    /**
     * SQL-更新
     * @param string $table 表名
     * @param array $data 数据
     * @param array $param 参数
     * @return bool|string
     */
    public function update($table, $data, $param = [])
    {
        if (!is_string($table) || !is_array($data) || !is_array($param)) {
            return false;
        }
        // 疏理表名
        $table = $this->fieldEscape($table);
        // 疏理参数
        $predefined = [
            // 主键
            'key' => 'id',
            // 条件
            'where' => null,
        ];
        $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
        // 疏理参数
        $predefined = [
            // 复合主键
            'keys' => $param['key'],
        ];
        $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
        // 转义复合主键
        $param['keys'] = $this->fieldEscape($param['keys']);
        // 疏理数据
        $data = array_map(function ($data) {
            $data = array_map(function ($data) {
                // 校验空值
                if (is_null($data)) return 'null';
                // 过滤表达式
                $search = ['\\', '\'', '"'];
                $replace = ['\\\\', '\\\'', '\\"'];
                foreach ($search as $key => $value) {
                    $data = str_replace($value, $replace[$key], $data);
                }
                return '\'' . $data . '\'';
            }, $data);
            return $data;
        }, $data);
        $tray['value'] = [];
        // 疏理替换值
        foreach ($data as $elem) {
            foreach ($elem as $key => $cell) {
                $tray['value'][$key][$elem[$param['key']]] = $cell;
            }
        }
        // 疏理替换值
        $tray['value'] = array_map(function ($value) {
            foreach ($value as $key => $value) {
                $data[] = implode(' ', ['when' . (is_numeric(trim($key, '\'"')) ? '' : ' binary'), $key, 'then', $value]);
            }
            $data = implode(PHP_EOL, $data);
            return $data;
        }, $tray['value']);
        $echo = [];
        foreach ($tray['value'] as $key => $value) {
            if ($key == $param['key']) continue;
            // 转义键名
            $key = $this->fieldEscape($key);
            $echo[] = implode('', [PHP_EOL, $key, ' = case ', $param['keys'], PHP_EOL, '', $value, PHP_EOL, 'else ', $key, PHP_EOL, 'end']);
        }
        $echo = implode(',', $echo);
        $tray['key'] = array_column($data, $param['key']);
        $tray['key'] = array_map(function ($value) {
            return (is_numeric(trim($value, '\'"')) ? '' : 'binary ') . $value;
        }, $tray['key']);
        // 疏理表达式
        $echo = 'update ' . $table . ' set ' . $echo . PHP_EOL . 'where ' . $param['keys'] . ' in (' . implode(',', $tray['key']) . ')';
        // 扩展条件
        if (!is_blank($param['where'])) {
            $echo .= ' and ' . $param['where'];
        }
        // 替换换行
        $echo = str_replace(PHP_EOL, ' ', $echo);
        return $echo;
    }

    /**
     * 处理参数-更新比较
     * @param array $data_new 新数据
     * @param array $data_old 旧数据
     * @return array
     */
    public function updateContrast($data_new, $data_old)
    {
        // 初始化变量
        $echo = \fxapp\Server::echo();
        if (!isset($data_new)) {
            // 新数据不存在
            return fxy_echo('缺少新数据', 0);
        } else if (!isset($data_old)) {
            // 旧数据不存在
            return fxy_echo('缺少旧数据', 0);
        } else if (!is_array($data_new) || !is_array($data_old)) {
            // 数据非数组
            return fxy_echo('数据格式错误', 0);
        }
        // 疏理数据不同
        $tray['key'] = [];
        foreach ($data_new as $key => $value) {
            if ($value != $data_old[$key]) {
                $tray['key'][] = $key;
            } else {
                unset($data_new[$key]);
            }
        }
        // 校验数据不同
        if (count($tray['key'])) {
            $echo[2] = fxy_lang(['数据', '[', fxy_config('app.lang.prefix') . implode('、', $tray['key']), ']', '不相同']);
            $echo[3] = $data_new;
            return $echo;
        }
        // 数据相同
        return fxy_echo('数据相同', 0);
    }

    /**
     * 处理存储-递归上级
     * @param array $table_name 表名
     * @param int $link_id 初始连接ID
     * @param string $link_name 连接名称
     * @param string $index 索引
     * @return string
     */
    public function storeUpperLevel($table_name, $link_id = 0, $link_name = 'parent_id', $index = 'id')
    {
        // 初始化变量
        if (!is_string($table_name)) {
            return false;
        }
        $echo = 'select group_concat(`id`) `ids`
        from (
            select `id`,
            if(find_in_set(`id`, @link_ids) > 0,
                @link_ids := concat(@link_ids, \',\', `link_id`),
                0
            ) `link_ids`
            from (
             select ' . $index . ' `id`,' . $link_name . ' `link_id`
             from ' . $table_name . '
             order by `link_id` desc
            ) `data`,
            (select @link_ids := \'' . $link_id . '\') `base`
        ) `data`
        where `link_ids` != \'0\' and `id` not in (\'' . $link_id . '\')';
        return $echo;
    }

    /**
     * 处理存储-递归下级
     * @param array $table_name 表名
     * @param int $link_id 初始连接ID
     * @param string $link_name 连接名称
     * @param string $index 索引
     * @return string
     */
    public function storeLowerLevel($table_name, $link_id = 0, $link_name = 'parent_id', $index = 'id')
    {
        // 初始化变量
        if (!is_string($table_name)) {
            return false;
        }
        $echo = 'select group_concat(`id`) `ids`
        from (
            select `id`,
            if(find_in_set(`link_id`, @link_ids) > 0,
                @link_ids := concat(@link_ids, \',\', `id`),
                0
            ) `link_ids`
            from (
             select ' . $index . ' `id`,' . $link_name . ' `link_id`
             from ' . $table_name . '
             order by `link_id`
            ) `data`,
            (select @link_ids := \'' . $link_id . '\') `base`
        ) `data`
        where `link_ids` != \'0\'';
        return $echo;
    }

    /**
     * 处理字段-计算经纬度距离
     * @param string $lngs 经度起点-longitude start
     * @param string $lats 维度起点-latitude start
     * @param string $lnge 经度终点-longitude end
     * @param string $late 维度终点-latitude end
     * @return string
     */
    public function fieldDistance($lngs, $lats, $lnge, $late)
    {
        // 初始化变量
        if (is_null($lngs) || is_null($lats) || is_null($lnge) || is_null($late)) {
            return false;
        }
        $echo = 'acos(
            sin(' . $lats . ' * 3.1415 / 180 ) *
            sin(' . $late . ' * 3.1415 / 180 ) +
            cos(' . $lats . ' * 3.1415 / 180 ) *
            cos(' . $late . ' * 3.1415 / 180 ) *
            cos(' . $lngs . ' * 3.1415 / 180 - ' . $lnge . ' * 3.1415 / 180 ) 
        ) * 6378.137';
        $echo = str_replace([" ", "　", "\t", "\n", "\r"], '', $echo);
        return $echo;
    }

    /**
     * 处理字段-首字母
     * @param string $field 字段名
     * @return string
     */
    public function fieldInitial($field)
    {
        // 初始化变量
        if (is_null($field)) {
            return false;
        }
        $echo = 'elt(
            interval(conv(hex(left(convert(' . $field . ' using gbk),\'1\')),\'16\',\'10\'),
            0,
            0xB0A1,0xB0C5,0xB2C1,0xB4EE,0xB6EA,0xB7A2,0xB8C1,0xB9FE,0xBBF7,
            0xBFA6,0xC0AC,0xC2E8,0xC4C3,0xC5B6,0xC5BE,0xC6DA,0xC8BB,0xC8F6,
            0xCBFA,0xCDDA,0xCEF4,0xD1B9,0xD4D1),
            UPPER(left(' . $field . ',\'1\')),
            \'A\',\'B\',\'C\',\'D\',\'E\',\'F\',\'G\',\'H\',\'J\',\'K\',\'L\',\'M\',\'N\',\'O\',\'P\',
            \'Q\',\'R\',\'S\',\'T\',\'W\',\'X\',\'Y\',\'Z\'
        )';
        $echo = str_replace(["  ", "　", "\t", "\n", "\r"], '', $echo);
        return $echo;
    }

    /**
     * 处理字段-除法
     * @param string $dividend 被除数
     * @param string $divisor 除数
     * @return string
     */
    public function fieldDivision($dividend, $divisor)
    {
        // 初始化变量
        if (is_null($dividend) || is_null($divisor)) {
            return false;
        }
        $echo = 'if(' . $divisor . '=0,0,' . $dividend . '/' . $divisor . ')';
        return $echo;
    }

    /**
     * 处理字段-Json
     * @param string $field 字段名
     * @param string $replace 替换值
     * @param int $param 参数
     * @return string
     */
    public function fieldJson($field, $replace = '', $param = 1)
    {
        // 初始化变量
        $field = explode('->', $field, 2);
        // 疏理子集
        $field[1] = ltrim($field[1], '$.');
        switch ($param) {
            default:
            case 1:
                // 默认
                // 疏理字段
                $field[0] = explode('.', $field[0], 2);
                $field[0] = array_map(function ($value) {
                    // 疏理数据
                    if (strpos($value, '`') === false) {
                        $value = '`' . $value . '`';
                    }
                    return $value;
                }, $field[0]);
                $field[0] = implode('.', $field[0]);
                // 疏理子集
                $field[1] = explode('.', $field[1]);
                $field[1] = array_map(function ($value) {
                    // 疏理键名
                    if (strpos($value, '[') === 0) {
                        // 数组
                    } else if (strpos($value, '"') === false) {
                        // 对象
                        $value = '"' . $value . '"';
                    }
                    return $value;
                }, $field[1]);
                $field[1] = implode('.', $field[1]);
                break;
            case 2:
                // 原生
                break;
        }
        // 疏理子集
        if (strpos($field[1], '[') === 0) {
            // 数组
            $field[1] = '$' . $field[1];
        } else {
            // 对象
            $field[1] = '$.' . $field[1];
        }
        // 疏理替换
        if (is_null($replace)) {
            $replace = 'null';
        } else if (is_string($replace)) {
            $replace = '\'' . $replace . '\'';
        }
        // 疏理输出
        $echo = 'if(json_valid(' . $field[0] . '),ifnull(json_unquote(json_extract(' . $field[0] . ',\'' . $field[1] . '\')),' . $replace . '),' . $replace . ')';
        return $echo;
    }

    /**
     * 处理字段-文本
     * @param string $field 字段名
     * @param string $replace 替换值
     * @param int $param 参数
     * @return string
     */
    public function fieldText($field, $replace = '', $param = 1)
    {
        // 初始化变量
        if (!$this->paramEmpty([$field], 1)[0]) {
            $field = '\'' . $field . '\'';
        }
        switch ($param) {
            default:
            case 1:
                // 默认
                if (is_numeric($replace) && is_int($replace + 0)) {
                    $echo = 'convert(if(length(' . $field . ')=0 or isnull(' . $field . '),' . $replace . ',' . $field . '),signed)';
                } else if (is_numeric($replace)) {
                    $echo = 'if(length(' . $field . ')=0 or isnull(' . $field . '),' . $replace . ',' . $field . ')';
                } else {
                    $echo = 'if(length(' . $field . ')=0 or isnull(' . $field . '),\'' . $replace . '\',' . $field . ')';
                }
                break;
            case 2:
                // 为空则替换
                $echo = 'if(length(' . $field . ')=0 or isnull(' . $field . '),' . $replace . ',' . $field . ')';
                break;
            case 3:
                // 转为整型
                $echo = 'convert(if(length(' . $field . ')=0 or isnull(' . $field . '),' . $replace . ',' . $field . '),signed)';
                break;
        }
        return $echo;
    }

    /**
     * 处理字段-日期
     * @param string $field 字段名
     * @param string $replace 替换值
     * @param int|string $param 参数
     * @return string
     */
    public function fieldDate($field, $replace = '', $param = 1)
    {
        // 初始化变量
        if (!$this->paramEmpty([$field], 1)[0]) {
            $field = '\'' . $field . '\'';
        }
        switch ($param) {
            case 1:
                // 日期时间
                $param = '%Y-%m-%d %H:%i:%s';
                break;
            case 2:
                // 日期
                $param = '%Y-%m-%d';
                break;
            case 3:
                // 时间
                $param = '%H:%i:%s';
                break;
        }
        $param = 'from_unixtime(' . $field . ',\'' . $param . '\')';
        if (is_null($replace)) {
            $replace = $param;
        } else {
            $replace = '\'' . $replace . '\'';
        }
        // 疏理输出
        $echo = 'if(' . $field . '=0 or isnull(' . $field . '),' . $replace . ',' . $param . ')';
        return $echo;
    }

    /**
     * 处理字段-别名
     * @param string $data 数据
     * @param string $alias 别名
     * @param string $delimiter 分隔
     * @return string
     */
    public function fieldAlias($data, $alias, $delimiter = '.')
    {
        // 初始化变量
        if (strpos($data, $delimiter) === false && !is_blank($alias)) {
            $data = $alias . $delimiter . $data;
        } else if (strpos($data, $delimiter) === 0) {
            $data = substr($data, 1);
        }
        return $data;
    }

    /**
     * 处理字段-转义
     * @param string $data 数据
     * @param string $delimiter 分隔
     * @return string
     */
    public function fieldEscape($data, $delimiter = '.')
    {
        // 初始化变量
        if (!is_blank($delimiter)) {
            $data = explode($delimiter, $data, 2);
        } else {
            $data = [$data];
        }
        $data = array_map(function ($value) {
            // 疏理数据
            if (strpos($value, '`') === false) {
                $value = '`' . $value . '`';
            } else if (strpos($value, '``') === 0) {
                $value = substr($value, 2);
            }
            return $value;
        }, $data);
        $data = implode($delimiter ?? '', $data);
        return $data;
    }

    /**
     * 处理HTML-过滤
     * @param mixed $data 数据
     * @param string $flags 标签
     * @return mixed
     */
    public function htmlFilter($data, $flags = null)
    {
        // 初始化变量
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->htmlFilter($val, $flags);
            }
        } else if (!is_blank($data) && !is_numeric($data)) {
            if ($flags === null) {
                $data = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $data);
                if (strpos($data, '&amp;#') !== false) {
                    $data = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $data);
                }
            } else {
                if (PHP_VERSION < '5.4.0') {
                    $data = htmlspecialchars($data, $flags);
                } else {
                    $data = htmlspecialchars($data, $flags, 'utf-8');
                }
            }
        }
        return $data;
    }

    /**
     * 处理HTML-移除
     * @param mixed $data 数据
     * @param string $flags 标签
     * @return mixed
     */
    public function htmlRemove($data, $flags = null)
    {
        // 初始化变量
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->htmlRemove($val, $flags);
            }
        } else if (!is_blank($data) && !is_numeric($data)) {
            $data = strip_tags($data, $flags);
        }
        return $data;
    }

    /**
     * 列表格式树
     * @param array $list 未树化数组
     * @param int $parent_id 初始父ID
     * @param string $parnet_name 父名称
     * @param string $index 索引
     * @param string $child_name 子名称
     * @return array
     */
    public function listToTree($list, $parent_id = 0, $parnet_name = 'parent_id', $index = 'id', $child_name = '_child')
    {
        // 初始化变量
        $tree = [];
        if (!is_array($list)) return $tree;
        $refer = [];
        foreach ($list as $key => $value) {
            $refer[$value[$index]] = &$list[$key];
        }
        foreach ($list as $key => $value) {
            $parentId = $value[$parnet_name];
            if ($parentId == $parent_id) {
                $tree[] = &$list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = &$refer[$parentId];
                    $parent[$child_name][] = &$list[$key];
                }
            }
        }
        return $tree;
    }

    /**
     * 处理参数-补全
     * @param array $param 参数
     * @return array
     */
    public function paramRepair($param)
    {
        // 初始化变量
        $echo = \fxapp\Server::echo();
        if (!isset($param)) {
            return fxy_echo('缺少参数', 0);
        } else if (!is_array($param)) {
            return fxy_echo('参数格式错误', 0);
        }
        // 抽取主键
        $tray['format'] = [];
        foreach ($param as $key => $value) {
            foreach ($value as $key2 => $value2) {
                $tray['format'][$key2] = '';
            }
        }
        // 补全主键
        foreach ($param as $key => $value) {
            foreach ($tray['format'] as $key2 => $value2) {
                $param[$key][$key2] = $param[$key][$key2] ?? $value2;
            }
            ksort($param[$key]);
        }
        $echo[2] = '请求成功';
        $echo[3] = $param;
        return $echo;
    }

    /**
     * 处理参数-过滤
     * @param array $param 参数
     * @return array
     */
    public function paramFilter($param)
    {
        // 初始化变量
        $echo = \fxapp\Server::echo();
        if (!isset($param)) {
            return fxy_echo('缺少参数', 0);
        } else if (!is_array($param)) {
            return fxy_echo('参数格式错误', 0);
        }
        foreach ($param as $key => $value) {
            if (is_null($value)) {
                unset($param[$key]);
            }
        }
        $echo[2] = '请求成功';
        $echo[3] = $param;
        return $echo;
    }

    /**
     * 检查参数-空参
     * @param array $param 参数
     * @param int $type 类型
     * @return array
     */
    public function paramEmpty($param, $type = 1)
    {
        // 初始化变量
        $echo = \fxapp\Server::echo();
        $echo[3] = $param;
        if (!isset($param)) {
            return fxy_echo('缺少参数', 0);
        } else if (!is_array($param)) {
            return fxy_echo('参数格式错误', 0);
        }
        // 疏理键值
        foreach ($param as $key => $value) {
            // 识别类型
            switch ($type) {
                default:
                case 1:
                    // 默认
                    if (!is_blank($value)) continue 2;
                    break;
                case 2:
                    // 非NULL
                    if (!is_null($value)) continue 2;
                    break;
            }
            $name = is_numeric($key) ? 'param' : $key;
            if ($echo[0]) {
                $echo[0] = false;
                $echo[1] = 0;
                $echo[4]['error']['name'] = 'lack';
            }
            $echo[4]['error']['data'][$name] = $value;
        }
        // 疏理消息
        if (!$echo[0]) {
            $echo[2] = array_keys($echo[4]['error']['data']);
            $echo[2] = array_map(function ($value) {
                $value = ['and2', '[', $value, ']'];
                return $value;
            }, $echo[2]);
            unset($echo[2][0][0]);
            $echo[2] = fxy_lang(['缺少', $echo[2]]);
        }
        return $echo;
    }

    /**
     * 检查参数-非空
     * @param array $param 参数
     * @return array
     */
    public function paramExist($param)
    {
        // 初始化变量
        $echo = \fxapp\Server::echo();
        if (!isset($param)) {
            return fxy_echo('缺少参数', 0);
        } else if (!is_array($param)) {
            return fxy_echo('参数格式错误', 0);
        }
        foreach ($param as $key => $value) {
            if (is_blank($value)) {
                $echo = fxy_echo('缺少参数', 0);
            } else {
                $echo = fxy_echo('检查成功', 1);
                break;
            }
        }
        return $echo;
    }
}
