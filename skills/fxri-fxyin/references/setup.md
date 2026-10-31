# 安装与装配

## 环境与安装

| 项目 | 要求 |
| --- | --- |
| PHP | 8.2 起 |
| 扩展 | `mbstring` 与 `openssl` 均为必需（`composer.json` 的 `require` 已声明） |
| 自动加载 | Composer PSR-4，`Fxri\` 对应 `src/`，无需手动引入启动文件 |

```bash
composer require fxri/fxyin
```

PSR 相关能力全部可选：`psr/http-client`、`psr/http-message`、`psr/http-factory` 及其实现（如 `symfony/http-client`、`nyholm/psr7`）缺省不装，只在使用 `Http\HttpClient\Client` 与 `ClientFactory` 的路径上才需要；`ext-curl` 只服务 URL 级的 `Http\HttpClient\Curl`。

## 内核装配

装配入口是 `KernelBuilder`，构造方法私有，只能从 `make()` 进入；每个 `with*` 都返回新实例，可任意顺序链式调用，最后 `build()` 出内核。

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\KernelBuilder;

// 只追加一个配置目录即可启动，其余装配项都有可用缺省
$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();

// config/app.php 的内容位于 app 键下
echo $kernel->config()->get('app.name', '未命名');
```

| 方法 | 作用 |
| --- | --- |
| `withConfigDir(string $directory)` | 追加配置目录，后追加者覆盖先前目录与内置配置的同名分组 |
| `withLanguageDir(string $directory, string $locale = 'zh-cn')` | 追加项目语言目录，覆盖内置同名词条 |
| `withLogger(LoggerInterface $logger)` | 注入 PSR-3 日志，缺省 `NullLogger` |
| `withTranslator(TranslatorInterface $translator)` | 注入 Symfony 翻译器，指定后 `trans()` 走该实例 |
| `withHttpClient(object $client)` | 注入约定的 PSR-18 客户端，不做类型校验 |
| `withAdapter(object $adapter)` | 注入接入方适配器，不做类型校验 |
| `withoutDefaultConfig()` / `withoutBuiltinLanguage()` / `bare()` | 分别关闭内置配置、内置语言包，或两者都关 |
| `build()` | 读盘加载配置与语言并生成内核 |

缺省装配会加载随包配置 `app`、`branch`、`env` 与内置中英文语言包；`build()` 每次都重新读盘，改完磁盘上的配置重新 `build()` 即生效，同一进程内多次构建得到互不相同的实例。

## 读取入口

内核装配完成后不可变，只提供读取入口：

| 方法 | 返回 |
| --- | --- |
| `config()` | 只读 `Config` |
| `configStore()` | 带覆盖层的 `Store`，`set()` 只写覆盖层 |
| `language()` | 语言服务，关闭内置且未显式指定时为 `null` |
| `logger()` | PSR-3 日志 |
| `translator()` | Symfony 翻译器 |
| `httpClient()` / `adapter()` | 注入的对象或 `null` |
| `trans(string $id, array $parameters = [])` | 翻译词条 |

## 入口与命令行的共用方式

同一个进程只装配一次内核，HTTP 入口与命令行入口都从同一处取，不要在各控制器里反复 `build()`。

HTTP 入口按宿主形态二选一：宿主已有容器就绑定进去，没有容器就在入口持有内核实例。

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Integration\ThinkPHP\ThinkPHPAdapter;
use Fxri\KernelBuilder;
use think\App;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();

// 宿主为 ThinkPHP 时把内核绑进容器，标识缺省 fxri.kernel
if (ThinkPHPAdapter::isAvailable()) {
    ThinkPHPAdapter::bind(new App(), $kernel);
}
```

命令行入口用 `Application\Bootstrap`，只有显式 `withCliArguments()` 才解析 `@` 参数：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Application\Bootstrap;
use Fxri\Application\Cli;
use Fxri\Http\RequestContext;

$bootstrap = Bootstrap::make(__DIR__)
    ->withCliArguments($_SERVER['argv'] ?? []);

$kernel = $bootstrap->boot();

// @ 参数写入 app.param.cli，并为 SERVER_NAME / REQUEST_URI 兜底
$request = RequestContext::fromGlobals(Cli::serverDefaults($kernel->configStore()));
```

- `Bootstrap::make()` 只校验目录；`boot()` 才扫描 `config`、`language/*`、`function/*.php`、`store/*.php` 并构建内核，重复调用返回同一内核。
- 路由默认不启用：`Bootstrap` 与 `KernelBuilder` 都不会接管请求；需要「路径直接映射控制器」时在入口显式 `Application\Router::make(...)->dispatch(...)`。
- 命令行的 `@` 参数不会覆盖普通配置项；`Cli::serverDefaults()` 读的是命令行入参名，不是配置路径。

## 常见错误

- 写成 `$builder->withLogger($logger);` 不接返回值：`with*` 不可变，等于没改。
- 同时保留旧版 `autoload.php` 与 Composer 自动加载：两套加载器互相遮蔽，表现为「找不到类」。
- 误以为 `withConfigDir()` 是替换：它是追加，想覆盖某组配置要把覆盖目录放在后面。
- `withHttpClient()` 与 `withAdapter()` 传错实现不会在装配期报错，只在使用处暴露。

更完整的装配说明见仓库 `docs/kernel.md`，接入宿主框架见 `docs/integration.md`，启动器与命令见 `docs/application.md`。
