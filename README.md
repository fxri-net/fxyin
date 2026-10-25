风音框架 2.0
===============

> 运行环境要求 PHP 8.2 起，需开启 `mbstring` 扩展，安全相关功能另需 `openssl`

## 基础信息

* 可以接入任意 PHP 项目，内置诸多实用方法，是一款轻量级 PHP 开发辅助框架。
* 不引入依赖注入容器：装配结果是一组不可变对象，业务代码按需取用；工具类均为纯静态，可直接调用。
* 不提供路由、中间件与 MVC 基类，只补齐业务开发里高频出现的参数、数据、文本、文件、HTTP 与安全能力。

## 安装

~~~bash
composer require fxri/fxyin
~~~

自动加载由 Composer 的 PSR-4 完成，`Fxri\` 对应 `src/`，不需要手动引入任何启动文件。

## 装配

~~~php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();

// 取配置，config/app.php 的内容位于 app 键下
$name = $kernel->config()->get('app.name', '未命名');

// 取翻译，未注入翻译器时由内置词条兜底
echo $kernel->trans('欢迎');
~~~

装配项均可选，`KernelBuilder` 的每个 `with*` 方法返回新实例，可任意顺序链式调用：

| 方法 | 作用 |
| --- | --- |
| `withConfigDir(string $directory)` | 追加配置目录，后追加者覆盖同名文件 |
| `withLogger(LoggerInterface $logger)` | 注入 PSR-3 日志，缺省为无操作日志 |
| `withTranslator(TranslatorInterface $translator)` | 注入翻译器，缺省为内置数组翻译 |
| `withHttpClient(object $client)` | 注入 PSR-18 客户端 |
| `withAdapter(object $adapter)` | 注入接入方适配器 |

配置按「文件名即分组名」加载，目录内 `app.php` 返回的数组整体落在 `app` 键下。

## 能力划分

| 命名空间 | 内容 |
| --- | --- |
| `Fxri\Param` | 参数定义、校验、合并、挑选与 URL 拼装 |
| `Fxri\Data` | 字段表达式、空值策略、数据比对与树形结构转换 |
| `Fxri\Sql` | 链式条件构造与 MySQL 方言 |
| `Fxri\Text` | 字符串、编码、IP、时间与 UUID |
| `Fxri\File` | 目录读写与路径处理 |
| `Fxri\Support` | 类型判定、随机串、格式化与调试输出 |
| `Fxri\Security` | 摘要、对称与非对称加解密、令牌 |
| `Fxri\Http` | 响应信封、请求与服务器上下文、Cookie、视图与 HTTP 客户端 |
| `Fxri\Config`、`Fxri\Translation` | 配置加载与环境变量、数组翻译与词条文件加载 |

数字码与点分模式号已全部改为具名枚举与链式方法，模块选择不再依赖数字约定。

## 接入已有框架

以 ThinkPHP 为例，把装配好的内核绑定进容器即可在控制器中取用：

~~~php
<?php

declare(strict_types=1);

use Fxri\Integration\ThinkPHP\ThinkPHPAdapter;
use Fxri\KernelBuilder;

$kernel = KernelBuilder::make()->withConfigDir(__DIR__ . '/config')->build();

ThinkPHPAdapter::bind(app(), $kernel);
~~~

随后用 `ThinkPHPAdapter::kernel(app())` 取回内核；异常经 `ExceptionHandler` 转换为统一响应信封。

## 项目开发

从 1.x 升级请参阅 [2.0 迁移说明](./docs/migration-2.0.md)，其中逐项列出旧 API 到新 API 的对照与不再提供的能力。

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
