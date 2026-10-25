风音框架 V1.1
===============

> 运行环境要求PHP7.1 ~ 8.4

## 基础信息

* 可以接入任意PHP项目，内置诸多实用方法，是一款轻量级PHP开发辅助框架。

## 基础部署

~~~
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
// 加载风音框架
require $root . 'extend' . DIRECTORY_SEPARATOR . 'fxri' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'autoload.php';
// 初始化应用
\fxyin\App::init();
~~~

## 项目开发

请参阅 [风音框架](https://gitee.com/fxri/fxyin)。

## 版权信息

名称：风音框架

作者：唐启云 <tqy@fxri.net>

出品：方弦研究所

版权：Copyright © 2016-2026 唐启云. All rights reserved.

网站：[方弦研究信息网](https://fxri.net:444/)

协议：[Apache License 2.0](./LICENSE)

商标："方弦®"为第42类注册商标（注册号89648411），本开源许可不授予商标使用权，详见 [TRADEMARK.md](./TRADEMARK.md)

> 方弦研究所为唐启云个人项目品牌与出品方，非独立法人实体；本软件著作权归唐启云所有。

本项目包含的第三方源码和二进制文件之版权信息另行标注。
