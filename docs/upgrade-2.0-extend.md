---
title: 升级 2.0：扩展目录迁移
outline: [2, 3]
---

# 升级 2.0：扩展目录迁移

1.x 的扩展目录通常位于 `extend/fxri/data`，负责注册扩展应用、追加门面方法、覆盖配置、加载语言包与执行启动钩子。2.0 没有对应的扩展机制，这些职责需要拆成「项目配置」「项目服务」「项目引导」三类落点。

> 本页只覆盖 `extend/fxri/base` 与 `extend/fxri/data`。`extend/fxri/safe`、`safe_local` 等独立模块不属于 fxyin，不在本页范围。

## 一、目录职责对照

| 1.x 位置 | 职责 | 2.0 落点 |
| --- | --- | --- |
| `extend/fxri/base` | 解压的框架本体 | Composer 包 `fxri/fxyin`，整目录删除 |
| `extend/fxri/bootstrap.php` | 引导入口 | 并入项目入口，见[接入形态](./upgrade-2.0-runtime) |
| `extend/fxri/data/config.php` | 注册扩展应用、门面目录、加载器目录 | 项目 PSR-4、项目服务、Composer 自动加载 |
| `data/app/config/*` | 扩展配置 | 项目 `config/`，必须是纯数组 |
| `data/app/controller/*` | 扩展控制器与门面 | 项目服务类，或项目内过渡门面 |
| `data/app/controller/facade_ext/*` | 追加到框架门面的方法 | 项目服务类，或过渡门面提供者 |
| `data/app/controller/oracle/*` | 服务定位器 | 项目工厂或宿主容器 |
| `data/app/function/common.php` | 启动时加载的全局函数 | Composer `autoload.files` 或项目类方法 |
| `data/app/language/*` | 语言词条 | `Translation\ArrayTranslator` / `PhpFileLoader` |
| `data/app/store/common.php` | 启动钩子 | 构建内核后的项目引导脚本与中间件 |

## 二、参考样本的共性

对使用 1.x 的接入方做只读盘点后，`data` 目录的结构高度同构，差异集中在规模：

| 维度 | 常见形态 |
| --- | --- |
| 配置文件 | 6–10 个，`app.php`、`env.php`、`facade.php`、`frame.php`、`branch.php`、`safe.php`、`third.php` 等 |
| 控制器 | 14–27 个，多数只是 `extends \fxyin\Facade` 的门面声明 |
| `facade_ext` | 10–12 个，追加项目方法 |
| `oracle` | 0–4 个，服务定位器 |
| 语言包 | 2–6 个文件，按语言目录组织 |
| 应用目录名 | `app/`、`application/`、`apps/` 三种写法 |
| 入口 | 根 `index.php` 与 `public/index.php` 两种 |
| 启动引导 | 少数项目有 `bootstrap.php`，多数在入口直接 `require autoload.php` |

共性意味着可以按同一张表逐项处理；规模差异只影响路线是 S 还是 M/L。

## 三、逐项迁移表

| 1.x 写法 | 2.0 写法 | 注意 |
| --- | --- | --- |
| `env.app` 注册多个应用目录 | 项目 PSR-4 命名空间 | 不再有「多应用目录按优先级加载」 |
| `env.facade[-1]` 注册门面扩展目录 | 项目服务类或过渡门面 | 2.0 没有门面层 |
| `loader.fxapp[1]` 注册控制器目录 | Composer `autoload` | 删除旧加载器配置 |
| `env.base.root` / `env.base.app` | 项目常量或配置项 | 路径在运行时解析，不写进配置数组 |
| `Param::define(..., '1.2.3')` | `Shape` / `Pipeline` | 见 [API 对照](./migration-2.0) |
| `Data::where([...])` 八元数组 | `WhereBuilder` | 条件与绑定值由对象携带 |
| `Server::echo()` 六元数组 | `Envelope` 的五键信封 | 旧 `time` 位并入 `debug`，旧 `extend` 位成为正式字段 |
| `Server::format($echo, 2)` | `Envelope::toArray()` 或项目序列化 | 键名不再可配置 |
| `Base::lang([$a, $b])` | `$kernel->trans()` | 旧的列表拼接语义需项目侧补 |
| `Lang::get()` / `Lang::detect()` | `ArrayTranslator` + 项目 `Context` | 语言列表与自动侦测改为项目服务 |
| `Base::oracle($name)` | 项目工厂或容器 | 定位器不再由框架提供 |
| `View::log()` | PSR-3 日志 | 通过 `KernelBuilder::withLogger()` 注入 |
| `Env::init()` / `Server::branch()` | `ServerContext` / `RequestContext` + 中间件 | 分支选择回到项目 |
| `Text::throwable($e, '1.2')` | 抛异常 + 统一异常映射 | 见[接入形态](./upgrade-2.0-runtime) |

## 四、配置纯度三条硬规则

2.0 的配置加载器按文件名顺序读取**纯数组**。1.x 的配置允许在加载时执行代码，迁移时必须先拆掉三类副作用：

1. 不得调用 `\fxapp\*`、`fxy_*`、`\fxyin\*`。
2. 不得读取 `$_SERVER`、`$_COOKIE`、`$_GET`、`$_POST` 等请求态。
3. 不得依赖其他配置文件的加载结果。

### 4.1 请求态配置改为运行时服务

1.x 的 `env.php` 常在加载时读取请求头、Cookie 与语言：

```php
$param = \fxapp\Client::param();
$param['base'] = \fxapp\Base::json($param['base'] ?? null, 'decode');
\fxyin\Lang::setLangCookieName(\fxapp\Base::config('app.lang.name'));
```

2.0 把这段逻辑移到内核构建之后：

```php
final class Context
{
    public function __construct(
        private readonly \Fxri\Http\RequestContext $request,
        private readonly \Fxri\Http\ServerContext $server,
        private readonly \Fxri\Http\Cookie\CookieJar $cookies,
        private readonly \Fxri\Kernel $kernel,
    ) {
    }

    public function language(): string
    {
        $config = $this->kernel->config()->get('app.lang', []);

        return $config['default'] ?? 'zh-cn';
    }
}
```

原 `env.php` 只保留纯数据：

```php
return [
    'lang' => [
        'default' => 'zh-cn',
        'list' => ['zh-cn', 'en-us'],
    ],
    'skin' => [
        'default' => 'elementui',
        'list' => ['base', 'layui', 'elementui'],
    ],
];
```

### 4.2 路径拼接改为运行时解析

1.x 的 `safe.php`、`third.php` 会在数组里调用 `\fxapp\Base::config()` 拼路径：

```php
'crypt_private' => \fxapp\Base::config('app.base.app') . 'elven/misc/pem/rsa_private.pem',
```

2.0 的配置只存相对路径或占位符，运行时用 `File\Path::join()` 解析：

```php
use Fxri\File\Path;

$private = Path::join($kernel->config()->get('app.base.app'), 'elven/misc/pem/rsa_private.pem');
```

### 4.3 条件与响应配置改为对象

- `facade.data.where` 的数字键配置直接删除，条件改用 `Sql\WhereBuilder`。
- `server.echo.template` 与 `server.echo.format` 删除，内部改用 `Http\Envelope`；对外字段由 `Envelope::toArray()` 的五个键固定。
- `letter.email` 等邮件配置属于项目业务，2.0 不提供邮件能力，迁到项目自己的配置分组。

## 五、门面扩展的三种替代

1.x 的门面扩展依赖 `\fxyin\Facade` 的 `__callStatic`：按 `env.facade` 的目录优先级查找方法，项目方法放在 `facade_ext`。2.0 不再提供门面层，按项目体量选一种：

### 方案一：项目内过渡门面（推荐给 M/L）

在项目里保留 `\fxapp\` 命名空间，Composer 映射到 `app/legacy/fxapp/`；旧调用点不动，门面内部改调 `Fxri\*`。

```json
{
  "autoload": {
    "psr-4": {
      "app\\": "app/",
      "fxapp\\": "app/legacy/fxapp/"
    }
  }
}
```

```php
<?php

declare(strict_types=1);

namespace fxapp;

use App\Support\KernelHolder;

abstract class LegacyFacade
{
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $provider = static::provider();
        if (!is_callable([$provider, $name])) {
            throw new \BadMethodCallException(static::class . '::' . $name . '() 尚未迁移');
        }

        return $provider->{$name}(...$arguments);
    }

    abstract protected static function provider(): object;
}

final class Base extends LegacyFacade
{
    protected static function provider(): object
    {
        return new \App\Support\Legacy\BaseProvider(KernelHolder::get());
    }
}
```

提供者只承接旧方法，内部换成 2.0 入口：

```php
<?php

declare(strict_types=1);

namespace App\Support\Legacy;

use Fxri\Kernel;

final class BaseProvider
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function config(string $name, mixed $default = null): mixed
    {
        return $this->kernel->config()->get($name, $default);
    }

    public function lang(string|array $name, array $vars = []): string
    {
        return $this->kernel->trans(is_array($name) ? implode('', $name) : $name, $vars);
    }
}
```

### 方案二：显式静态类（推荐给 S）

调用量小的项目直接删除 `\fxapp\` 命名空间，把每个门面改成显式类，调用点同步替换：

```php
namespace App\Support;

use Fxri\Kernel;

final class Config
{
    public static function get(Kernel $kernel, string $name, mixed $default = null): mixed
    {
        return $kernel->config()->get($name, $default);
    }
}
```

### 方案三：宿主容器绑定

宿主有容器时，把内核与项目服务绑进容器，调用点改为容器解析。ThinkPHP 用 `ThinkPHPAdapter::bind()`，其他容器用各自的 `instance` / `set` 入口。

### oracle 与自定义定位器

`Base::oracle($name)` 是项目自己的服务定位器，与 fxyin 无关。迁移时保留实现，改成显式工厂：

```php
final class OracleFactory
{
    public static function make(string $name): object
    {
        $class = '\\App\\Oracle\\' . ucfirst(strtolower($name));
        if (!class_exists($class)) {
            throw new \InvalidArgumentException('未定义的 oracle：' . $name);
        }

        return new $class();
    }
}
```

有容器时优先用容器管理单例，避免继续维护静态缓存。

## 六、启动钩子拆分

1.x 的 `data/app/store/common.php` 固定做两件事：

```php
\fxyin\Env::init();
\fxapp\Server::branch();
```

2.0 拆成两部分：

1. **构建内核后执行**的项目引导脚本：初始化环境变量、语言、日志。
2. **请求进入后执行**的中间件：根据请求头选择分支、格式、语言、皮肤，写入项目上下文。

```php
// bootstrap/context.php：构建内核之后执行
$environment = \Fxri\Config\Environment::fromFile(__DIR__ . '/../.env');
$server = \Fxri\Http\ServerContext::fromGlobals();
$request = \Fxri\Http\RequestContext::fromServer($server);
$cookies = \Fxri\Http\Cookie\CookieJar::fromGlobals();
```

## 七、语言与全局函数

- 语言词条是纯数据，目录整体迁到项目 `language/{locale}/`，用 `ArrayTranslator::fromDirectory()` 或 `Translation\Loader\PhpFileLoader` 加载。
- `function/common.php` 若为空直接删除；若非空，迁到 Composer `autoload.files`，或改成项目类方法。函数体里的旧 API 仍需按 [API 对照](./migration-2.0) 替换。
- 旧的 `langList()`、`langParse()` 列表拼接语义没有等价实现，由项目 `BaseProvider::lang()` 按实际业务保留。

## 八、响应协议的保持

旧出口是 `{code, message, data, extend}`，部分项目另加 `time`。2.x 的五键信封已与旧出口对齐，不需要再写响应工厂：

| 旧出口 | 2.x |
| --- | --- |
| `code`（整数业务码） | `code` |
| `message`（出口前翻译） | `message`（构造时用 `$kernel->trans()`） |
| `data` | `data`；分页接口为 `Data\Page` 的四键壳 |
| `extend` | `extend` |
| `time` | 移除，耗时放 `debug` |

需要改键名的项目，在 `Envelope::toArray()` 之后再映射一层即可；不要复刻旧的位置数组。

## 九、框架副本的本地新增方法

`extend/fxri/base` 里的类常被就地加过方法。这类方法不在任何正式标签里，2.0 也没有等价 API，升级前必须逐项定落点，不能跟着目录一起删。

判定方式：

1. 用升级总览里「框架副本可复原」的比对结果，筛出「1.x 正式副本没有、项目副本有」的方法。
2. 在 2.0 里按语义找同类能力；找不到就落成项目服务或工具类。
3. 把落点写进改动清单，与 `facade_ext` 的迁移项分开跟踪。

常见落点：目录创建类方法落到 `File\Filesystem`，语言族方法（`langRange` / `langSet` / `langDetect` / `langCookieExpire`）落到 `Translation\Language` 与 `LocaleResolver`，Cookie 名与有效期由项目配置承接；与共享副本重复的改动按下一节统一处理。

两条注意：

- 这类方法不属于「项目扩展方法」：后者定义在 `facade_ext` 里，随扩展目录迁移；前者寄生在框架副本内，只在副本存在时可用。
- 删除副本前先跑一次基线，确认引用点全部有替代，再执行删除。

## 十、框架副本的通用改动落点

组织内多套 1.x 项目共用同一份就地改造过的框架副本。逐文件比对后，副本改动可归并为 13 类，其中多数在各项目上完全一致，按本表统一处置即可，不必逐仓重做分析。

| 副本改动 | 实际行为 | 2.0 落点 |
| --- | --- | --- |
| `facade/Server.php`：`store` 倒序 + `echo` 兜底 `?? []` | 响应栈按后进先出取值，缺失时返回空数组 | `Http\Envelope`；旧协议由 `Envelope::toArray()` 承接 |
| `facade/Text.php`：`implode()` 支持数组、去重与方括号形式 | 把数组拼成可复用的查询串片段 | `Text\Str::implode()`，已带 `$unique` 参数 |
| `facade/Param.php`：命令行入参前缀由 `-` 改为 `@`，并清理 `argv` | 用 `@` 标记入参且不污染原始参数 | `Application\Cli` 与 `Bootstrap::withCliArguments()` |
| `frame/App.php`：启动去重（`load` 与 `start` 两段各自缓存） | 避免重复加载文件与重复启动应用 | `Application\Bootstrap` 与 `Services` |
| `facade/Data.php`：模糊匹配支持逗号多值与转义 | 多值模糊查询，反斜杠与引号转义 | `Sql\WhereBuilder::like()`，转义由构建器负责 |
| `config/env.php`：语言只取 `HTTP_LANG` 与自动侦测 | 去掉对 `view` 参数解码的依赖 | `Translation\LocaleResolver::resolve()` |
| `function/common.php`：函数返回补 `true` | 修正返回值 | 项目函数迁入 `autoload.files` 或改为项目类方法 |
| `facade/Service.php`：`http()` 增加 `$option` 形参 | 透传 cURL 选项 | `HttpClient\Client` 搭配 `CurlOptions` |
| `facade/Client.php`：UA 缺失时回退空串而非 null | 避免 null 参与字符串比较 | `Http\ClientInfo::userAgent()` |
| `frame/Env.php`、`frame/Config.php`：`explode` 前补 `strval` | 兼容非字符串入参 | 2.0 方法已强制 `string` 参数，无需迁移 |
| `facade/File.php`：新增 `createDirectory()` | 创建目录 | `File\Filesystem::createDirectory()` |
| `facade/Base.php`：新增 `langRange` / `langDetect` / `langSet` / `langCookieExpire`，`?` 前缀的 `has`/`get` | 语言作用域、探测、设置与 Cookie 过期 | `Translation\Language` 与 `LocaleResolver` |
| `app/controller/*.php` 转发类：仅 PHPDoc 可空标注与新增方法声明 | 无行为变化 | 转发类整体删除，调用改为 `Fxri\…` 或项目门面 |

另有约两成差异等于 1.5 / 1.6 官方版本内容，直接采用官方实现即可，不进入上表。

## 十一、删除顺序

1. 备份 `extend/fxri` 与部署包，记录当前 PHP 与宿主版本。
2. 补 `composer.json` 与自动加载，装入 `fxri/fxyin:^2.0`。
3. 迁移配置：先拆副作用，再把纯数组放进 `config/`。
4. 迁移语言与全局函数。
5. 迁移门面、`facade_ext` 与 `oracle`。
6. 拆 `store/common.php` 为项目引导与中间件。
7. 删除 `extend/fxri/base`、`extend/fxri/data` 与 `extend/fxri/bootstrap.php`。
8. 清理 Composer 里为旧目录保留的映射。
9. 运行基线，确认 `\fxapp\` 与点分模式号归零。

## 十二、验收

1. 全仓检索 `extend/fxri/base`、`\fxapp\`、`\fxyin\`、`fxy_`、点分模式号，除过渡层外归零。
2. `config/` 下每个文件只返回数组，不调用函数、不读超全局变量。
3. 启动冒烟与 HTTP 冒烟通过，异常映射返回失败信封。
4. `upgrade-inspect.php baseline --check` 零新增。
5. 过渡层调用量持续下降；归零后删除 `\fxapp\` 命名空间与映射。

## 十三、红线

1. 不把 `safe` / `safe_local` 混进 fxyin 的删除与迁移。
2. 不在同一提交里既删旧目录又改对外响应协议。
3. 不保留 1.x `autoload.php` 与 Composer 双加载。
4. 不把真实密钥、数据库口令、服务器地址写进文档或示例配置。
