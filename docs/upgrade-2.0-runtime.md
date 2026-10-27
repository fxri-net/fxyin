---
title: 升级 2.0：接入形态
outline: [2, 3]
---

# 升级 2.0：接入形态

1.x 的接入方式不止一种：有的项目把框架解压到 `extend/fxri/base`，有的自带 `bootstrap.php`，有的直接 `require autoload.php`，宿主也可能是 ThinkPHP 6、ThinkPHP 5 或自研框架。本页按宿主形态给出 2.0 的引导模板。

> 本页只覆盖 `extend/fxri/base` 与入口引导；扩展目录 `extend/fxri/data` 见[扩展目录迁移](./upgrade-2.0-extend)。`extend/fxri/safe` 等独立模块不在 fxyin 迁移范围内。

## 一、四类接入形态

| 形态 | 识别特征 | 2.0 引导方式 |
| --- | --- | --- |
| ThinkPHP 6 | `composer.lock` 含 `topthink/framework` 6.x，有 `think` 命令 | `KernelBuilder` + `ThinkPHPAdapter::bind()` |
| ThinkPHP 5 | `composer.lock` 含 `topthink/framework` 5.x | 同样绑定内核，保留宿主原有启动代码 |
| 非 ThinkPHP | 无 `think` 命令，入口为根 `index.php` 或自研 `core/start.php` | 静态 `KernelHolder` + 宿主原引导 |
| 无 Composer | 无 `composer.json`、无 `vendor/autoload.php` | 先补 Composer 接入，再按上面三类处理 |

## 二、通用目标结构

无论宿主是什么，2.0 的装配结果都是一个不可变 `Kernel`：

```php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/../config')
    ->build();
```

装配项全部可选；`withConfigDir()` 可多次追加，后追加的目录覆盖同名配置。日志、翻译器、HTTP 客户端与适配器按需注入。

为了让异常处理、旧代码适配层都能拿到同一个内核，建议在项目里放一个极薄的持有者：

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Fxri\Kernel;
use LogicException;

final class KernelHolder
{
    private static ?Kernel $kernel = null;

    public static function set(Kernel $kernel): void
    {
        self::$kernel = $kernel;
    }

    public static function get(): Kernel
    {
        if (null === self::$kernel) {
            throw new LogicException('内核尚未初始化');
        }

        return self::$kernel;
    }
}
```

## 三、宿主为 ThinkPHP 6

入口只保留「构建内核 → 绑定容器 → 交给宿主启动」三步：

```php
<?php

declare(strict_types=1);

use App\Support\KernelHolder;
use Fxri\Integration\ThinkPHP\ThinkPHPAdapter;
use Fxri\KernelBuilder;

require __DIR__ . '/../vendor/autoload.php';

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/../config')
    ->build();

KernelHolder::set($kernel);

$app = new \think\App();
ThinkPHPAdapter::bind($app, $kernel);

$response = $app->http->run();
$response->send();
$app->end($response);
```

异常处理改为「先取信封，再交给宿主输出」：

```php
<?php

declare(strict_types=1);

use App\Support\KernelHolder;
use Fxri\Integration\ThinkPHP\ExceptionHandler;

final class Exception extends \think\exception\Handle
{
    public function render($request, \Throwable $error): \think\Response
    {
        $envelope = ExceptionHandler::make(KernelHolder::get())->envelope($error);

        return json($envelope->toArray());
    }
}
```

宿主若没有全局 `json()` 助手，用 `\think\Response::create($envelope->toArray(), 'json')` 等价构造。

## 四、宿主为 ThinkPHP 5

ThinkPHP 5 的容器同样提供 `instance()` 与 `make()`，`ThinkPHPAdapter::bind()` 与 `kernel()` 可以直接使用。差别主要在启动代码与异常处理的签名，保留宿主原有启动方式即可：

```php
$app = new \think\App();
ThinkPHPAdapter::bind($app, $kernel);

// 以下保留宿主原有启动代码，不照搬 ThinkPHP 6 的 $app->http->run()
```

异常处理仍用 `KernelHolder::get()` 取内核，再返回宿主 `Response`；若 `json()` 助手不可用，用 `\think\Response::create($envelope->toArray(), 'json')`。

## 五、宿主为非 ThinkPHP

非框架项目没有容器可绑定，用 `KernelHolder` 持有内核，在宿主原有引导之前完成装配：

```php
<?php

declare(strict_types=1);

use App\Support\KernelHolder;
use Fxri\KernelBuilder;

require __DIR__ . '/vendor/autoload.php';

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();

KernelHolder::set($kernel);

// 以下保留宿主原有启动代码，例如 require core/start.php
```

旧代码若需要「当前请求」信息，从 `ServerContext` 与 `RequestContext` 取，不再直接读 `$_SERVER`：

```php
use Fxri\Http\RequestContext;
use Fxri\Http\ServerContext;

$server = ServerContext::fromGlobals();
$request = RequestContext::fromServer($server);

echo $request->method();
echo $request->path();
```

`Env::init()`、`Server::branch()` 这类 1.x 启动钩子没有 2.0 等价物：环境变量用 `Fxri\Config\Environment::fromFile()` 解析，分支选择改为项目中间件或引导脚本。

## 六、无 Composer 项目

2.0 只通过 Composer 分发。项目没有 `composer.json` 时，先补一个最小清单：

```json
{
  "require": {
    "php": "^8.2",
    "fxri/fxyin": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "app\\": "app/"
    }
  }
}
```

要点：

- 删除为 `extend/fxri/base` 保留的 PSR-0 / PSR-4 映射，避免旧加载器遮蔽 Composer。
- `vendor/` 是否入库沿用项目现有部署策略；若入库，升级后仍按原方式部署；若不入库，部署阶段执行 `composer install --no-dev`。
- 项目自定义的全局函数迁到 Composer `autoload.files`，或改为项目类方法。

## 七、入口与引导变体

| 1.x 形态 | 2.0 处理 |
| --- | --- |
| 入口引用 `extend/fxri/bootstrap.php` | 删除该文件，把三行引导并入入口 |
| 入口直接 `require extend/fxri/base/autoload.php` | 改为 `require vendor/autoload.php` + `KernelBuilder` |
| 根 `index.php` 与 `public/index.php` 同时存在 | 以实际 Web 根为准，另一个入口同步改造或删除 |
| `extend/fxri/base` 随仓库入库 | 整目录删除；不要保留空目录或旧 `autoload.php` |
| 项目自建 `config.php` 存根 | 迁移到 `config/` 目录，文件名即分组名 |

## 八、请求上下文与分支

1.x 的请求信息散落在 `$_SERVER`、`Env`、`Client::param()` 与 `Server::branch()`。2.0 收敛为三个只读对象：

| 对象 | 用途 | 入口 |
| --- | --- | --- |
| `ServerContext` | 服务端变量快照 | `fromGlobals()`、`make()` |
| `RequestContext` | 方法、路径、查询串、头、体 | `fromGlobals()`、`fromServer()`、`fromPsrRequest()` |
| `Environment` | `.env` 文本解析 | `fromFile()`、`fromString()`、`make()` |

分支、格式、语言、皮肤等业务选择放在项目中间件或引导脚本里，读取 `RequestContext` 后写入项目自己的上下文服务；框架不再内置分支逻辑。

## 九、验收

1. `composer validate --strict` 与 `composer install` 成功。
2. CLI 冒烟：以 ThinkPHP 为例，`php think list` 无弃用与致命错误。
3. HTTP 冒烟：入口返回 200，响应头与内容类型符合预期。
4. 异常冒烟：抛一个 `Fxri\Exception\ValidationException`，确认被映射为失败信封而不是堆栈页。
5. 旧引导文件已删除，全仓检索 `extend/fxri/base` 与 `\fxyin\App::init` 归零。

## 十、边界

- 本页不覆盖 `extend/fxri/data` 的配置、门面扩展、oracle 与启动钩子，见[扩展目录迁移](./upgrade-2.0-extend)。
- `extend/fxri/safe`、`safe_local` 是独立模块，保留原状或单独排期，不并入 fxyin 2.0。
- 宿主容器绑定只做「把内核放进容器」，不接管路由、中间件与 MVC 基类。
