# 快速开始

风音框架是一个可被任意 PHP 项目接入的辅助框架。它不接管入口、路由与依赖注入，只把参数、数据、文本、文件、HTTP 与安全这些高频能力沉淀成可直接调用的类。`Fxyin` 读作「风音」：`Fx` 取自「方弦」，`yin` 即「音」，取「轻、快」之意。

## 环境要求

- PHP 8.2 起
- 扩展 `mbstring`、`openssl`（均为必需）
- Composer
- 可选依赖：`psr/http-client`、`psr/http-message`、`psr/http-factory` 及其实现（如 `symfony/http-client`、`nyholm/psr7`），只在用到 PSR-18 客户端时安装

## 安装

```bash
composer require fxri/fxyin
```

自动加载由 Composer 的 PSR-4 完成：`Fxri\` 对应 `src/`，不需要手动引入任何启动文件。

## 装配

装配入口是 `KernelBuilder`，先用 `make()` 取一个构建器，再按需挂上装配项，最后 `build()` 出内核：

```php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();
```

装配项全部可选，`make()` 与每个 `with*` 方法都返回新实例，可以任意顺序链式调用。完整方法、参数与缺省值见[内核与装配](./kernel)。

## 最小可运行示例

目录约定如下，配置与语言包都按「一个文件一个分组」组织：

```text
项目根/
├── config/
│   └── app.php
├── language/
│   └── zh-cn/
│       └── app.php
└── index.php
```

`config/app.php` 直接返回数组，文件名即顶层键：

```php
<?php

declare(strict_types=1);

return [
    'name' => '示例应用',
    'debug' => true,
];
```

`language/zh-cn/app.php` 返回「词条 => 译文」的平铺数组：

```php
<?php

declare(strict_types=1);

return [
    '欢迎' => '欢迎使用风音框架',
];
```

`index.php` 把两者装进内核：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\KernelBuilder;
use Fxri\Translation\ArrayTranslator;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->withTranslator(ArrayTranslator::fromDirectory(__DIR__ . '/language/zh-cn'))
    ->build();

// config/app.php 的内容位于 app 键下
echo $kernel->config()->get('app.name');   // 示例应用

// 词条未命中时原样返回词条本身
echo $kernel->trans('欢迎');
```

配置按「文件名即分组名」加载，多个配置目录按追加顺序叠加；加载规则与点号路径细节见[配置](./config)。日志与翻译器缺省时落为无操作实现，因此最小装配只需一个配置目录，甚至完全不传装配项也能 `build()`。

## 按需取用能力

内核只负责配置、日志、翻译、HTTP 客户端与适配器；其余能力都是 `Fxri\*` 下的纯静态类，直接 `use` 即可，不经过内核：

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

## 下一步

- 从 1.x 升级：见 [1.x 升级 2.0 指南](./upgrade-2.0)，按体检、路线与阶段推进；旧 API 对照见 [2.0 API 对照](./migration-2.0)。
- 接入已有框架：`Integration\ThinkPHP\ThinkPHPAdapter::bind()` 把内核绑定进容器，异常经 `ExceptionHandler` 映射为统一响应信封。
- 查各版本变更：见[更新日志](./changelog)。
