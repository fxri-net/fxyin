---
title: API 对照
---

# API 对照

2.0 是一次不保留 API 兼容层的大版本改版。旧的门面层、全局函数、点分模式号与数字码配置全部移除，改由具名类与具名枚举承担。本文是 API 对照附录，逐项列出旧写法与新写法的对应关系。

> 升级步骤、接入形态与扩展目录迁移见[1.x 升级 2.0 指南](./)；本页只回答「旧写法对应什么新写法」。

数字码与门面方法的逐项对照分别见[数字码对照](./codes)与[门面方法对照](./facades)。

## 一、改动概览

| 维度 | 1.x | 2.0 |
| --- | --- | --- |
| 安装方式 | 解压框架包到 `extend/fxri/base` | `composer require fxri/fxyin` |
| 加载方式 | 自带 `autoload.php` + `Loader` 注册 | Composer PSR-4，`Fxri\` → `src/` |
| 入口 | `\fxyin\App::init()` 隐式初始化 | `KernelBuilder::make()->build()` 显式装配 |
| 调用方式 | `\fxapp\Base::config()` 等静态门面 | `Kernel` 实例 + `Fxri\*` 纯静态工具类 |
| 模式选择 | 点分数字串（`1.1.2`）与整型 `type` 参数 | 具名枚举 + 链式方法 |
| 响应结构 | 配置驱动的 6 元位置数组 | `Envelope` 固定五字段 |
| 错误处理 | `fxy_echo` 返回数组、`throwable()` 直接 `exit` | 抛异常，由接入层统一映射为信封 |
| PHP 版本 | 7.1 ~ 8.4 | 8.2 起 |

## 二、安装与装配

~~~bash
composer require fxri/fxyin
~~~

最小装配示例：

~~~php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;
use Fxri\Translation\ArrayTranslator;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->withTranslator(ArrayTranslator::fromDirectory(__DIR__ . '/language/zh-cn'))
    ->build();

// 取配置
$name = $kernel->config()->get('app.name', '未命名');

// 取翻译
echo $kernel->trans('欢迎');
~~~

装配项说明：

| 方法 | 作用 |
| --- | --- |
| `withConfigDir(string $directory)` | 追加配置目录，后追加者覆盖同名文件 |
| `withLogger(LoggerInterface $logger)` | 注入 PSR-3 日志 |
| `withTranslator(TranslatorInterface $translator)` | 注入翻译器 |
| `withHttpClient(object $client)` | 注入 PSR-18 客户端 |
| `withAdapter(object $adapter)` | 注入接入方适配器（如 ThinkPHP 容器适配） |

配置文件按「文件名即分组名」加载，`config/app.php` 返回的数组整体位于 `app` 键下，用 `$kernel->config()->get('app.name')` 取值。

## 三、命名空间与类映射

| 旧 | 新 |
| --- | --- |
| `\fxyin\App` | `Fxri\KernelBuilder`、`Fxri\Kernel` |
| `\fxyin\Config` | `Fxri\Config\Config`、`Fxri\Config\ConfigLoader` |
| `\fxyin\Env` | `Fxri\Config\Environment`、`Fxri\Http\ServerContext` |
| `\fxyin\Lang` | `Fxri\Translation\ArrayTranslator` |
| `\fxyin\Cookie` | `Fxri\Http\Cookie\CookieJar` |
| `\fxyin\Dir` | `Fxri\File\Filesystem`、`Fxri\File\Path` |
| `\fxyin\Loader` | Composer PSR-4（不再需要） |
| `\fxyin\Facade` | 移除，改用 `Kernel` 与纯静态类 |
| `\fxapp\Base` | `Fxri\Kernel`、`Fxri\Http\*`、`Fxri\Translation\*` |
| `\fxapp\Client` | `Fxri\Http\ClientInfo` |
| `\fxapp\Data` | `Fxri\Data\*`、`Fxri\Sql\*` |
| `\fxapp\File` | `Fxri\File\*`、`Fxri\Support\Formatter` |
| `\fxapp\Math` | `Fxri\Support\Random` |
| `\fxapp\Param` | `Fxri\Param\*` |
| `\fxapp\Safe` | `Fxri\Security\*` |
| `\fxapp\Server` | `Fxri\Http\Envelope`、`Responder`、`ServerContext`、`RequestContext` |
| `\fxapp\Service` | `Fxri\Http\HttpClient\Client`、`ClientFactory` |
| `\fxapp\Text` | `Fxri\Text\*`、`Fxri\Support\Type` |
| `\fxapp\View` | `Fxri\Http\View`、`Fxri\Http\Renderer` |

旧门面 `\fxapp\Base` ~ `\fxapp\View` 与 `app/controller/facade/` 一并移除，不再提供任何转发入口。

## 四、全局函数迁移

### 4.1 11 个 `is_*` 校验函数

| 旧函数 | 新写法 |
| --- | --- |
| `is_json($data)` | `Fxri\Support\Type::isJson()` |
| `is_blank($data)` | `Fxri\Support\Type::isBlank()` |
| `is_time($time, $format)` | `Fxri\Support\Type::isTime()` |
| `is_function($data)` | `Fxri\Support\Type::isCallable()` |
| `is_mobile($data)` | `Fxri\Support\Type::isMobile()` |
| `is_email($data)` | `Fxri\Support\Type::isEmail()` |
| `is_zipcode($data)` | `Fxri\Support\Type::isZipcode()` |
| `is_ipv4($data)` | `Fxri\Support\Type::isIpv4()` |
| `is_ipv6($data)` | `Fxri\Support\Type::isIpv6()` |
| `is_url($data)` | `Fxri\Support\Type::isUrl()` |
| `is_ip($data)` | `Fxri\Support\Type::isIp()` |

### 4.2 7 个 `fxy_*` 函数

| 旧函数 | 新写法 |
| --- | --- |
| `fxy_lang($name, $vars, $lang)` | `$kernel->trans($id, $parameters)` |
| `fxy_config(...$vars)` | `$kernel->config()->get($name, $default)` |
| `fxy_cookie(...$vars)` | `Fxri\Http\Cookie\CookieJar` |
| `fxy_env(...$vars)` | `Fxri\Http\ServerContext`、`Fxri\Config\Environment` |
| `fxy_dump(...$vars)` | `Fxri\Support\Dump::write(...)` |
| `fxy_json($var, $type, $param)` | `Fxri\Param\Json::encode()`、`Json::decode()` |
| `fxy_echo($message, $code, $data, $extend)` | `Fxri\Http\Envelope` + `Fxri\Http\Responder` |

## 五、响应与异常

旧 `Server::format()` 按 `type` 决定输出结构，`Base::throwable()` 在非调试环境下直接 `exit` 输出。2.0 改为「业务抛异常、接入层统一映射」：

| 场景 | 旧写法 | 新写法 |
| --- | --- | --- |
| 校验失败 | `return \fxapp\Base::echo('参数不合法', 0)` | `throw new Fxri\Exception\ValidationException('参数不合法')` |
| 数据未变化 | `return \fxapp\Data::updateContrast(...)` | `throw new Fxri\Exception\SameDataException('数据相同')` |
| 未知异常 | `\fxapp\Base::throwable('系统异常')` | 交接入层捕获并映射，如 `Fxri\Integration\ThinkPHP\ExceptionHandler` |
| 输出响应 | `\fxapp\Server::format($echo, 2)` | `Fxri\Http\Responder::format($envelope)` |

`Server::format($data, 2)` 的「按 `facade.server.echo.format` 映射键名」能力没有等价实现，键名已在 `Envelope::toArray()` 中固定，不再可配置。

## 相关文档

- [升级总览](./)：升级路线、阶段与回滚。
- [接入形态](./runtime)：ThinkPHP 6、ThinkPHP 5、非 ThinkPHP、无 Composer。
- [扩展目录](./extend)：`extend/fxri/data` 的逐项处理。
- [检查清单](./checklist)：阶段清单与基线模板。
- [行为变更](./behavior)：不再提供的能力与高频调用差异。
- [数字码对照](./codes)：旧数字码与具名枚举的对应关系。
- [门面方法对照](./facades)：按门面类列出旧方法与新入口。
- [快速开始](../getting-started)：从零安装、装配与最小可运行示例。
- [更新日志](../changelog)：各版本完整变更条目，含 2.0 的分组说明。
