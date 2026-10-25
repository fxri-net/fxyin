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
 * 视图类
 */
class View
{
    /**
     * 处理数据-输出
     * @param mixed $data 数据
     * @param string $type 类型
     * @return mixed
     */
    public function echo($data, $type = '')
    {
        // 返回格式
        $type = strtolower($type);
        switch ($type) {
            case 'json':
                // JSON
            case 'xml':
                // XML
                header('Content-Type:application/json; charset=utf-8');
                $data = fxy_json($data, 'encode');
                break;
        }
        return $data;
    }
}
