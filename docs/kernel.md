# 内核与装配

风音框架把散落的配置、日志、翻译、HTTP 客户端与接入方适配器收进一个不可变对象，让调用方在任意入口处只依赖一个实例取服务。

## 定位

内核位于框架的最外层，由 `Fxri\Kernel` 与 `Fxri\KernelBuilder` 两个类组成，包元信息由 `Fxri\Package` 承载。它解决的是「服务从哪来」的问题：装配项在启动阶段一次性确定，业务代码只读不写。

内核只做两件事——装配与读取入口，不提供依赖注入容器、不注册路由、不扫描目录。同名的「内核」在不少框架里兼做容器与调度中心，这里刻意不做：容器交给接入方（如 ThinkPHP 的 `app()`），路由交给接入方的路由组件，框架自身只保证拿到的配置与翻译可用。

装配通过构建器完成。`KernelBuilder` 的构造方法是私有的，只能用 `make()` 起手；每个 `with*` 方法都返回新实例，因此同一个构建器可以被复用出多套装配，互不影响。

配置文件由 `ConfigLoader` 在 `build()` 时读入（细节见[配置](./config)），翻译器只要求实现 Symfony 的 `TranslatorInterface`，日志只要求 PSR-3 的 `LoggerInterface`，HTTP 客户端只要求任意对象（约定为 PSR-18 客户端）。接入方适配器同样是任意对象，ThinkPHP 场景下的用法见[接入](./integration)。

默认装配还会加载内置中英文语言包与 `resources/config` 默认配置，生成 `Fxri\Translation\Language`（细节见[语言](./language)）；`withoutBuiltinLanguage()` / `withoutDefaultConfig()` 或 `bare()` 可分别关闭，`withLanguageDir()` 可追加项目语言目录并覆盖内置词条，`withConfigDir()` 追加的项目配置覆盖内置配置。

内核装配完成后不可变：自身没有任何 `set*` 方法，`config()` 返回的 `Config` 也是只读容器。需要运行期覆盖配置时走 `configStore()->set()`，覆盖只进存储层；需要改变装配本身，只能重新 `build()` 一份新的内核。

## 最小示例

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\KernelBuilder;
use Fxri\Package;
use Fxri\Translation\ArrayTranslator;

$kernel = KernelBuilder::make()
    ->withTranslator(ArrayTranslator::make([
        'zh-cn' => ['messages' => ['欢迎' => '欢迎使用 %name%']],
    ]))
    ->build();

// 默认已加载内置配置，项目配置可覆盖
echo $kernel->config()->get('app.name', '未命名');        // 未命名
echo $kernel->config()->get('app.lang.default');          // zh-cn

// 运行期写入只进配置存储的覆盖层，只读配置不变
$kernel->configStore()->set('app.lang.default', 'en-us');
echo $kernel->configStore()->get('app.lang.default');     // en-us
echo $kernel->config()->get('app.lang.default');          // zh-cn

// 翻译走内核的 trans()，占位符参数键可省略百分号
echo $kernel->trans('欢迎', ['name' => '风音框架']);  // 欢迎使用 风音框架

// 默认已装配内置语言包，关闭时用 withoutBuiltinLanguage() 或 bare()
// 注入了自定义翻译器时，$kernel->trans() 走自定义翻译器，内置词条仍可从语言服务读取
echo $kernel->language()->get('delete');              // 删除

// 未注入日志时落为无操作实现
echo get_class($kernel->logger());                   // Psr\Log\NullLogger

// 未注入 HTTP 客户端与适配器时为 null
var_dump($kernel->httpClient(), $kernel->adapter()); // NULL NULL

// 包标识与版本
echo Package::describe();                            // fxri/fxyin 2.0.0-dev
```

## 参数与返回值

### KernelBuilder

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(): self` | 创建构建器，构造方法私有，只能由此进入 | 构建器 |
| `bare(): self` | 关闭内置语言与内置配置，只保留显式装配部分 | 新实例 |
| `withoutBuiltinLanguage(): self` | 关闭内置语言包 | 新实例 |
| `withoutDefaultConfig(): self` | 关闭内置默认配置 | 新实例 |
| `withConfigDir(string $directory): self` | 追加配置目录，后追加者覆盖内置配置与先前目录的同名配置；目录不存在时抛 `InvalidArgumentException` | 新实例 |
| `withLanguageDir(string $directory, string $locale = 'zh-cn'): self` | 追加项目语言目录，覆盖内置同名词条；目录不存在时抛 `InvalidArgumentException` | 新实例 |
| `withLanguage(Language $language): self` | 显式指定语言服务，接管内置与项目语言装配 | 新实例 |
| `withLocale(string $locale): self` | 指定默认语言，优先于配置 `app.lang.default` | 新实例 |
| `withLocaleResolver(LocaleResolver $resolver): self` | 指定语言检测器，缺省用 `app.lang.name` 与 `app.lang.list` 装配 | 新实例 |
| `withLogger(LoggerInterface $logger): self` | 指定 PSR-3 日志 | 新实例 |
| `withTranslator(TranslatorInterface $translator): self` | 指定 Symfony 翻译器；指定后 `trans()` 走该实例 | 新实例 |
| `withHttpClient(object $client): self` | 指定 HTTP 客户端 | 新实例 |
| `withAdapter(object $adapter): self` | 指定接入方适配器 | 新实例 |
| `build(): Kernel` | 按当前装配加载配置与语言并生成内核 | 内核实例 |

`build()` 每次都重新走一遍 `ConfigLoader::make(...$this->configDirectories)->load()` 与语言组装，改了磁盘上的配置或语言包后重新 `build()` 即可生效。

### Kernel

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `config(): Config` | 取只读配置，构造内核时传入 `Config` 或 `Store` | `Fxri\Config\Config` |
| `configStore(): Store` | 取配置存储，`set()` 只写覆盖层 | `Fxri\Config\Store` |
| `language(): ?Language` | 取语言服务，关闭内置语言且未显式指定时为 `null` | `Fxri\Translation\Language` 或 `null` |
| `logger(): LoggerInterface` | 取日志，未注入时为 `NullLogger` | PSR-3 日志 |
| `translator(): TranslatorInterface` | 优先返回注入的翻译器，其次取语言服务的翻译器，都没有时为 `ArrayTranslator::make()` | Symfony 翻译器 |
| `httpClient(): ?object` | 取 HTTP 客户端，未注入时为 `null` | 对象或 `null` |
| `adapter(): ?object` | 取接入方适配器，未注入时为 `null` | 对象或 `null` |
| `trans(string $id, array $parameters = []): string` | 等价于 `translator()->trans($id, $parameters)` | 译文 |

构造函数签名是 `__construct(Config|Store $config, ?LoggerInterface $logger = null, ?TranslatorInterface $translator = null, ?object $httpClient = null, ?object $adapter = null, ?Language $language = null)`，虽是公开方法，常规路径仍建议走构建器。

### Package

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `describe(): string` | 拼接包名与版本号 | 形如 `fxri/fxyin 2.0.0-dev` |

类上另有 `NAME`（`fxri/fxyin`）与 `VERSION`（`2.0.0-dev`）两个常量。

## 注意事项

- `withConfigDir()` 是追加语义，不是替换。内置配置先加载，想「覆盖某组配置」应把覆盖目录放在后面。
- 默认配置随包提供，`config()->get()` 在未追加项目配置时也有值；需要 2.0 的空配置环境时用 `withoutDefaultConfig()` 或 `bare()`。
- `with*` 返回新实例，旧变量仍指向旧装配。写成 `$builder->withLogger($logger);` 而不接返回值，等于什么都没做。
- `withHttpClient()` 与 `withAdapter()` 接受任意 `object`，类型不做校验；传错实现只会在使用处才暴露，注入前自行确认接口。
- 日志与翻译器有缺省实现，因此完全不传装配项也能 `build()`；默认装配还会加载内置语言包，关闭后用 `ArrayTranslator` 兜底。HTTP 客户端与适配器没有缺省对象，取到的是 `null`，调用方需自行判空。
- 内核不缓存 `build()` 结果，也不提供单例。同一进程内多次 `build()` 会多次读盘，且得到互不相同的实例。
- 内核不承载参数、数据、SQL、文本、文件、安全等能力，这些是不经过内核的纯静态类（见[快速开始](./getting-started)）。
- 适配器与 HTTP 客户端在内核里只是被承载，内核不会主动调用它们；取到之后由业务代码或集成层自行使用，ThinkPHP 的绑定方式见[接入](./integration)。
- `Package::VERSION` 当前为 `2.0.0-dev`，随发版变化；用它做版本校验时按语义化版本比较，不要硬编码整个字符串。
- `build()` 期间读取配置若抛出异常会直接向上冒泡，装配阶段不做兜底；启动失败策略由接入方自行决定。
- 配置与翻译的加载细节、目录叠加顺序在[配置](./config)与[语言](./language)中说明。
