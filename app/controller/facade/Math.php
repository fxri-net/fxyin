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
 * 数学类
 */
class Math
{
    /**
     * 随机字符
     * @param int $length 长度
     * @param int $type 类型(0：混合；1：纯数字)
     * @return string
     */
    public function rand($length, $type = 0)
    {
        // 初始化变量
        $seed = base_convert(md5(microtime() . __DIR__), 16, $type ? 10 : 35);
        $seed = $type ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
        if ($type) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed[mt_rand(0, $max)];
        }
        return $hash;
    }
}
