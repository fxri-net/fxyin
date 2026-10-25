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
 * 服务类
 */
class Service
{
    /**
     * HTTP请求
     * @param string $url 网址
     * @param array|string $data 数据
     * @param array $header 请求头
     * @param string $method 请求方式
     * @param array $option 选项
     * @return mixed
     */
    public function http($url, $data = '', $header = [], $method = null, $option = [])
    {
        // 初始化变量
        if (is_null($url)) {
            return false;
        }
        if (is_null($method)) {
            $method = 'GET';
        }
        $method = strtoupper($method);
        // 疏理选项
        $predefined = [
            // 网址
            'url' => $url,
            // 数据
            'data' => $data,
            // 请求头
            'header' => $header,
            // 方法
            'method' => $method,
            // 返回结果
            'return' => true,
            // 跟踪重定向
            'track' => true,
            // 响应数据
            'res_data' => null,
            // 响应头
            'res_header' => false,
            // 响应头大小
            'res_header_size' => 0,
            // 响应内容
            'res_body' => true,
            // 检查ssl证书
            'ssl_peer' => false,
            // 检查ssl主机名
            'ssl_host' => false,
            // 默认访问IPv4
            'ipv4' => true,
            // 简易结果
            'easy' => true,
        ];
        $option = \fxapp\Param::define([$option, $predefined], '1.1.2');
        // 初始化请求
        $request = curl_init();
        // 识别请求方法
        switch ($option['method']) {
            default:
            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                // 默认
                // 数据进行字符串编码
                $option['data'] = \fxapp\Text::strEncode($option['data']);
                // 配置请求
                curl_setopt($request, CURLOPT_POST, true);
                curl_setopt($request, CURLOPT_POSTFIELDS, $option['data']);
                break;
            case 'GET':
                // GET
                // 数据合并到url
                $option['url'] = \fxapp\Param::url(['type' => '1.2', 'url' => $option['url'], 'param' => $option['data']]);
                // 配置请求
                curl_setopt($request, CURLOPT_POST, false);
                break;
        }
        // 配置请求地址
        curl_setopt($request, CURLOPT_URL, $option['url']);
        // 配置请求方法
        curl_setopt($request, CURLOPT_CUSTOMREQUEST, $option['method']);
        // 配置请求头
        curl_setopt($request, CURLOPT_HTTPHEADER, $option['header']);
        // 配置返回结果
        curl_setopt($request, CURLOPT_RETURNTRANSFER, $option['return']);
        // 配置跟踪重定向
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, $option['track']);
        // 配置响应头
        curl_setopt($request, CURLOPT_HEADER, $option['res_header']);
        // 配置响应内容
        curl_setopt($request, CURLOPT_NOBODY, !$option['res_body']);
        // HTTPS请求，不验证证书和主机名
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, $option['ssl_peer']);
        curl_setopt($request, CURLOPT_SSL_VERIFYHOST, $option['ssl_host']);
        // 设置curl默认访问为IPv4
        if ($option['ipv4'] && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($request, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        // 发送请求
        $option['res_data'] = curl_exec($request);
        // 清除BOM头
        $option['res_data'] = ltrim($option['res_data'], "\XEF\XBB\XBF");
        // 解析响应头
        if ($option['res_header']) {
            // 获得响应头大小
            $option['res_header_size'] = curl_getinfo($request, CURLINFO_HEADER_SIZE);
            // 根据头大小获取头信息
            $option['res_header'] = substr($option['res_data'], 0, $option['res_header_size']);
        }
        // 解析响应内容
        if ($option['res_body']) {
            // 根据头大小获取内容
            $option['res_body'] = substr($option['res_data'], $option['res_header_size']);
        }
        // 关闭连接
        curl_close($request);
        // 返回数据
        return $option['easy'] ? $option['res_body'] : $option;
    }

    /**
     * HTTP请求-下载
     * @param string $url 文件远程路径
     * @param string $file 文件本地路径
     * @return mixed
     */
    public function httpDown($url, $file)
    {
        if (!is_dir(dirname($file))) {
            \fxyin\Dir::create(dirname($file));
        }
        // 请求文件
        $response = $this->http($url, '', [], 'get');
        // 保存文件
        $downloaded_file = fopen($file, 'w');
        fwrite($downloaded_file, $response);
        fclose($downloaded_file);
        if (\fxapp\Data::paramEmpty([$response], 1)[0]) {
            return $file;
        } else {
            return false;
        }
    }
}
