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
 * 服务器类
 */
class Server
{
    /**
     * 初始化响应
     * @return array
     */
    public function echo()
    {
        // 初始化变量
        $echo = fxy_config('facade.server.echo.template');
        return $echo;
    }

    /**
     * 获取IP
     * @return mixed
     */
    public function ip()
    {
        $ip = \fxapp\Service::http('http://httpbin.org/ip', '', [], 'get');
        if (!is_json($ip)) return;
        $ip = json_decode($ip, true);
        $ip['origin'] = explode(',', $ip['origin'])[0];
        return $ip['origin'];
    }

    /**
     * 获取系统
     * @param int $type 类型
     * @return mixed
     */
    public function system($type = -1)
    {
        $echo = php_uname();
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
     * 处理数据-格式
     * @param array $data 数据
     * @param int $type 类型
     * @return array
     */
    public function format($data, $type = 1)
    {
        // 初始化变量
        $echo = [];
        $debug['switch'] = fxy_config('app.debug.switch');
        $debug['trace'] = fxy_config('app.debug.trace');
        $debug['param'] = fxy_config('app.param');
        switch ($type) {
            default:
            case 1:
                // 默认
                if (is_json($data)) {
                    $data = \fxapp\Param::json($data, 'decode');
                } else if (is_object($data)) {
                    $data = (array) $data;
                }
                $echo = $data;
                break;
            case 2:
                // 通用
                $base = fxy_config('facade.server.echo.format');
                // 处理数据
                $data[2] = fxy_lang($data[2] ?? '');
                foreach ($base as $key => $value) {
                    if (array_key_exists($key, $data)) {
                        $echo[$value] = $data[$key];
                    }
                }
                break;
        }
        if (!is_array($echo)) return $echo;
        // 调试模式
        if ($debug['switch'] && $debug['trace']) {
            $echo['debug'] = ['' => null];
            $debug['trace'] = \fxapp\Text::explode(',', $debug['trace']);
            $tray['name'] = [];
            foreach ($debug['trace'] as $value) {
                switch ($value) {
                    default:
                        // 匹配
                        if (array_key_exists($value, $debug['param'])) {
                            $tray['name'][] = [$value];
                        }
                        break;
                    case '1':
                        // 全部
                        $tray['name'][] = array_keys($debug['param']);
                        break;
                    case '2':
                        // 入参
                        $tray['name'][] = ['param', 'get', 'post', 'input', 'cli'];
                        break;
                    case '3':
                        // 文件
                        $tray['name'][] = ['files'];
                        break;
                    case '4':
                        // 环境
                        $tray['name'][] = ['server', 'cookie', 'session', 'env'];
                        break;
                }
            }
            $tray['name'] = array_unique(\fxapp\Param::append(...$tray['name']));
            foreach ($tray['name'] as $value) {
                $echo['debug'][$value] = $debug['param'][$value];
            }
        }
        // 空数组转对象
        $echo = \fxapp\Param::object($echo);
        return $echo;
    }

    /**
     * 处理数据-环境
     * @return mixed
     */
    public function env()
    {
        // 配置请求主机
        if (PHP_SAPI === 'cli' && !isset($_SERVER['SERVER_NAME'])) {
            // 载入数据
            $tray['host'] = fxy_config('app.param.cli.svr_name');
            $tray['host'] = !is_null($tray['host']) ? strtolower($tray['host']) : null;
            // 配置数据
            $_SERVER['SERVER_NAME'] = $tray['host'];
            fxy_config('env.base.host', $tray['host']);
            fxy_env('base.host', $tray['host']);
        }
        // 配置请求路径
        if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_URI'])) {
            // 载入数据
            $tray['uri'] = fxy_config('app.param.cli.svr_uri');
            $tray['uri'] = !is_null($tray['uri']) ? strtolower($tray['uri']) : null;
            // 配置数据
            $_SERVER['REQUEST_URI'] = $tray['uri'];
            fxy_config('env.base.uri', $tray['uri']);
            fxy_env('base.uri', $tray['uri']);
        }
    }

    /**
     * 处理数据-分支
     * @return mixed
     */
    public function branch()
    {
        // 载入分支
        $tray['branch'] = strval(fxy_env('base.host'));
        $tray['store'] = fxy_config('branch.store');
        $tray['store'] = array_reverse($tray['store']);
        foreach ($tray['store'] as $index => $elem) {
            foreach (explode(',', $index) as $value) {
                // 全文匹配
                $data['full'][$value] = $index;
                // 特殊匹配
                $data['initial'] = mb_substr($value, 0, 1, 'utf-8');
                $data['after'] = mb_substr($value, 1, null, 'utf-8');
                switch ($data['initial']) {
                    case '^':
                        // 正则表达式
                        $data['regular'][$data['after']] = $value;
                        break;
                    case '$':
                        // 正则表达式-完整
                        $data['regular_all'][$data['after']] = $value;
                        break;
                }
            }
        }
        // 全文匹配
        if (isset($data['full'][$tray['branch']])) {
            $tray['echo'] = $tray['branch'];
        }
        // 特殊匹配-正则表达式
        if (!isset($tray['echo']) && isset($data['regular'])) {
            foreach ($data['regular'] as $key => $value) {
                if (!preg_match('/' . $key . '/i', $tray['branch'])) continue;
                $tray['echo'] = $value;
                break;
            }
        }
        // 特殊匹配-正则表达式-完整
        if (!isset($tray['echo']) && isset($data['regular_all'])) {
            foreach ($data['regular_all'] as $key => $value) {
                if (!preg_match($key, $tray['branch'])) continue;
                $tray['echo'] = $value;
                break;
            }
        }
        // 默认分支
        if (!isset($tray['echo'])) {
            $tray['echo'] = fxy_config('branch.default');
        }
        // 基础分支
        $tray['base'] = $tray['store']['base'] ?? [];
        // 匹配分支
        $tray['echo'] = $tray['store'][$data['full'][$tray['echo']] ?? null] ?? [];
        // 合并分支
        $tray['echo'] = array_merge($tray['base'], $tray['echo']);
        // 配置分支
        foreach ($tray['echo'] as $elem) {
            fxy_config($elem[1], fxy_config($elem[0]));
        }
    }
}
