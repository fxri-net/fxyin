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
 * 安全类
 */
class Safe
{
    /**
     * 解析数据-加密
     * @param mixed $data 数据
     * @param string $type 类型
     * @param string $param 参数
     * @return mixed
     */
    public function crypt($data, $type, $param = null)
    {
        // 初始化变量
        $type = strtolower($type);
        switch ($type) {
            default:
                // 默认
                $data = null;
                break;
            case 'encode':
                // 编码
                if (!is_array($param)) {
                    $param = null;
                }
                $predefined = [
                    'method' => 'des-ecb', 'password' => '00000000', 'options' => null,
                    'iv' => null,
                ];
                $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
                $data = openssl_encrypt($data, $param['method'], $param['password'], $param['options'], $param['iv']);
                break;
            case 'decode':
                // 解码
                if (!is_array($param)) {
                    $param = null;
                }
                $predefined = [
                    'method' => 'des-ecb', 'password' => '00000000', 'options' => null,
                    'iv' => null,
                ];
                $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
                $data = openssl_decrypt($data, $param['method'], $param['password'], $param['options'], $param['iv']);
                break;
        }
        return $data;
    }

    /**
     * 生成MD5
     * @param string $data 数据
     * @param int $type 类型
     * @return string
     */
    public function md5($data, $type = null)
    {
        // 初始化变量
        $type = intval($type);
        switch ($type) {
            default:
            case 1:
                // 32位
                $data = md5($data);
                break;
            case 2:
                // 16位
                $data = substr(md5($data), 8, 16);
                break;
        }
        return $data;
    }

    /**
     * 解析数据-令牌
     * @param mixed $data 数据
     * @param string $type 类型
     * @return mixed
     */
    public function token($data, $type)
    {
        // 初始化变量
        $type = strtolower($type);
        switch ($type) {
            default:
                // 默认
                $data = null;
                break;
            case 'encode':
                // 编码
                if (!$data) {
                    // 空字符串
                    return false;
                } else if (is_array($data)) {
                    // 数组
                    $data = implode(',', $data);
                } else if (!is_string($data)) {
                    // 非字符串
                    return false;
                }
                // 计算加密长度
                $data = ',' . $data . ',';
                $strlen = strlen($data);
                $exp = 5;
                do {
                    $pow = pow(2, $exp);
                    $strdiff = $pow - $strlen;
                    ++$exp;
                } while ($strdiff < 0);
                $strmax = $pow;
                // 填充令牌
                $data = str_pad($data, $strmax, \fxapp\Math::rand($strdiff / 2), STR_PAD_BOTH);
                // 加密令牌
                $data = \fxapp\Base::crypt($data, 'encode');
                $data = bin2hex($data);
                break;
            case 'decode':
                // 解码
                if (!$data) {
                    // 空字符串
                    return false;
                } else if (!is_string($data)) {
                    // 非字符串
                    return false;
                } else if (strlen($data) % 2 != 0) {
                    // 不解析单数字符串
                    return false;
                } else if (!ctype_xdigit($data)) {
                    // 非纯16进制字符串
                    return false;
                }
                // 解密令牌
                $data = hex2bin($data);
                $data = \fxapp\Base::crypt($data, 'decode');
                $data = explode(',', $data);
                array_shift($data);
                array_pop($data);
                $data = implode(',', $data);
                break;
        }
        return $data;
    }

    /**
     * 解析数据-RSA私钥
     * @param mixed $data 数据
     * @param string $type 类型
     * @param array $param 参数
     * @return mixed
     */
    public function rsapri($data, $type, $param = [])
    {
        // 初始化变量
        $echo = [];
        if (!is_string($data) || !is_array($param)) return false;
        $predefined = [
            'type', 'secret',
        ];
        $param = \fxapp\Param::define([$param, $predefined], '1.2.2');
        $param['secret'] = $this->rsapem($param['secret']);
        $param['secret'] = openssl_pkey_get_private($param['secret']);
        $type = strtolower($type);
        switch ($type) {
            case 'encode':
                // 编码
                $data = str_split($data, 117);
                foreach ($data as $value) {
                    $entrys = null;
                    // 解析填充字符
                    switch ($param['type']) {
                        case OPENSSL_NO_PADDING:
                            // 填充-自定义
                            $predefined = [
                                'pad' => "\0",
                            ];
                            $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
                            $value = str_pad($value, 128, $param['pad'], STR_PAD_LEFT);
                            break;
                    }
                    openssl_private_encrypt($value, $entrys, $param['secret'], $param['type']);
                    $echo[] = $entrys;
                }
                break;
            case 'decode':
                // 解码
                $data = str_split($data, 128);
                foreach ($data as $value) {
                    $entrys = null;
                    openssl_private_decrypt($value, $entrys, $param['secret'], $param['type']);
                    // 解析填充字符
                    switch ($param['type']) {
                        case OPENSSL_NO_PADDING:
                            // 填充-自定义
                            $predefined = [
                                'pad' => "\0",
                            ];
                            $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
                            $entrys = ltrim($entrys, $param['pad']);
                            break;
                    }
                    $echo[] = $entrys;
                }
                break;
        }
        $echo = implode('', $echo);
        return $echo;
    }

    /**
     * 解析数据-RSA公钥
     * @param mixed $data 数据
     * @param string $type 类型
     * @param array $param 参数
     * @return mixed
     */
    public function rsapub($data, $type, $param = [])
    {
        // 初始化变量
        $echo = [];
        if (!is_string($data) || !is_array($param)) return false;
        $predefined = [
            'type', 'secret',
        ];
        $param = \fxapp\Param::define([$param, $predefined], '1.2.2');
        $param['secret'] = $this->rsapem($param['secret']);
        $param['secret'] = openssl_pkey_get_public($param['secret']);
        $type = strtolower($type);
        switch ($type) {
            case 'encode':
                // 编码
                $data = str_split($data, 117);
                foreach ($data as $value) {
                    $entrys = null;
                    // 解析填充字符
                    switch ($param['type']) {
                        case OPENSSL_NO_PADDING:
                            // 填充-自定义
                            $predefined = [
                                'pad' => "\0",
                            ];
                            $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
                            $value = str_pad($value, 128, $param['pad'], STR_PAD_LEFT);
                            break;
                    }
                    openssl_public_encrypt($value, $entrys, $param['secret'], $param['type']);
                    $echo[] = $entrys;
                }
                break;
            case 'decode':
                // 解码
                $data = str_split($data, 128);
                foreach ($data as $value) {
                    $entrys = null;
                    openssl_public_decrypt($value, $entrys, $param['secret'], $param['type']);
                    // 解析填充字符
                    switch ($param['type']) {
                        case OPENSSL_NO_PADDING:
                            // 填充-自定义
                            $predefined = [
                                'pad' => "\0",
                            ];
                            $param = \fxapp\Param::define([$param, $predefined], '1.1.2');
                            $entrys = ltrim($entrys, $param['pad']);
                            break;
                    }
                    $echo[] = $entrys;
                }
                break;
        }
        $echo = implode('', $echo);
        return $echo;
    }

    /**
     * 解析数据-RSA密钥
     * @param string $data 数据
     * @return string
     */
    public function rsapem($data)
    {
        // 初始化变量
        if (!is_string($data)) {
            // 检查类型
            return false;
        } else if (is_file($data)) {
            // 读取密钥
            $data = file_get_contents($data);
        }
        // 移除换行
        $data = str_replace("\n", '', $data);
        // 处理头尾
        $regular = '/-+[^-]+-+/i';
        preg_match_all($regular, $data, $match);
        $data = preg_replace($regular, '', $data);
        // 组装密钥
        $data = ($match[0][0] ?? '') . "\n" .
            wordwrap($data, 64, "\n", true) .
            "\n" . ($match[0][1] ?? '');
        return $data;
    }
}
