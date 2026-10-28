# 应用启动

应用层把「扫描项目目录、装配内核、注册服务、执行启动钩子」收敛为一次显式调用，不接管请求生命周期，也不提供全局入口。

## 定位

这一层由四部分组成：

- `Fxri\Application\Bootstrap`：按项目目录扫描 `config/`、`language/*`、`function/*.php`、`store/*.php`，构建内核并执行生命周期钩子。
- `Fxri\Application\Services`：显式服务注册表，支持实例、工厂与按名称解析，替代 1.x 的 `facade_ext` 与 `oracle`。
- `Fxri\Application\Module`：模块契约，获得启动器后自行追加配置、语言、函数、服务与钩子。
- `Fxri\Application\Router`：可选路由，从请求路径解析控制器与方法，默认不装配。

`Bootstrap::boot()` 是唯一启动入口：构造启动器只做参数校验，不读目录、不执行脚本。项目目录结构沿用 1.x：

```text
project/
├── config/*.php            # 覆盖内置配置
├── language/{locale}/*.php # 覆盖内置语言
├── function/*.php          # 全局函数，启动时 require
└── store/*.php             # 启动脚本，内核构建后执行
```

`store/*.php` 内可直接使用 `$bootstrap`、`$kernel`、`$services` 三个变量；返回可调用对象时，会再以同样三者调用一次。`function/*.php` 与 Composer `autoload.files` 二选一即可。

## 最小示例

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Application\Bootstrap;
use Fxri\Application\Services;

$bootstrap = Bootstrap::make(__DIR__)
    ->withService('demo', new stdClass())
    ->withFactory('lazy', static fn (Services $services): object => new stdClass())
    ->withHook(Bootstrap::PHASE_START, static function (Bootstrap $app): void {
        $app->services()->set('started_at', new DateTimeImmutable());
    });

$kernel = $bootstrap->boot();

echo $kernel->config()->get('app.lang.default');   // 项目覆盖后的值
echo $kernel->language()->get('delete');           // 项目语言覆盖后的词条
var_dump($bootstrap->services()->get('lazy'));     // 工厂首次解析
```

## 参数与返回值

### Bootstrap

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(?string $path = null): self` | 创建启动器，项目目录不存在时抛 `InvalidArgumentException` | 启动器 |
| `boot(): Kernel` | 扫描目录、加载函数、执行钩子并构建内核；重复调用返回同一内核 | 内核 |
| `kernel(): ?Kernel` | 取已构建的内核，未启动时为 `null` | 内核或 `null` |
| `path(): ?string` | 取项目目录 | 目录或 `null` |
| `services(): Services` | 取服务注册表，启动前后都可用 | 注册表 |
| `withConfigDir(string $directory): self` | 追加配置目录，目录不存在时抛异常 | 启动器 |
| `withLanguageDir(string $directory, string $locale): self` | 追加语言目录，目录不存在时抛异常 | 启动器 |
| `withFunctions(string ...$files): self` | 追加函数文件，文件不存在时抛异常 | 启动器 |
| `withHook(string $phase, callable(self): void $hook): self` | 登记阶段钩子，未知阶段抛异常 | 启动器 |
| `withService(string $id, object $service): self` | 登记服务实例 | 启动器 |
| `withFactory(string $id, callable(Services): object $factory): self` | 登记服务工厂 | 启动器 |
| `withModule(Module $module): self` | 注册模块，返回模块处理后的启动器 | 启动器 |

阶段常量：`PHASE_LOAD`（目录扫描与函数加载后、内核构建前）、`PHASE_START`（内核构建后）、`PHASE_READY`（启动脚本执行后）。钩子统一接收启动器，加载阶段 `kernel()` 为 `null`；启动或就绪阶段抛异常时内核被清空，修正后可重新 `boot()`。

### Services

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(): self` | 创建注册表 | 注册表 |
| `set(string $id, object $service): void` | 登记实例，覆盖同名工厂 | 无 |
| `factory(string $id, callable(self): object $factory): void` | 登记工厂，首次 `get()` 时执行 | 无 |
| `get(string $id): object` | 解析服务，工厂结果缓存；未登记时抛 `InvalidArgumentException` | 服务实例 |
| `has(string $id): bool` | 判断是否已登记或已解析 | 是否存在 |
| `ids(): array` | 返回按登记顺序排列的服务名 | `list<string>` |

### Module

```php
interface Module
{
    public function register(Bootstrap $bootstrap): Bootstrap;
}
```

模块在 `withModule()` 时立即执行 `register()`，可链式调用启动器的 `with*` 方法。模块自身不保存全局状态，重复注册由启动器按调用顺序叠加。

### Router

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $namespace = 'App', string $suffix = '', ?callable $resolver = null): self` | 创建路由器；默认直接实例化控制器，传入解析器则完全交给项目执行 | 路由器 |
| `controller(RequestContext $request): string` | 路径最后一段为方法，其余段为控制器路径；路径段转小写，控制器末段首字母大写 | 类名 |
| `method(RequestContext $request): string` | 取路径最后一段作为方法名 | 方法名 |
| `parse(RequestContext $request): array` | 同时返回控制器类名与方法名 | `array{0: string, 1: string}` |
| `dispatch(RequestContext $request): mixed` | 解析并执行；控制器不存在或方法不可调用时抛 `RouteNotFoundException` | 控制器返回值 |
| `withNamespace(string $namespace): self` | 替换控制器命名空间 | 新实例 |
| `withSuffix(string $suffix): self` | 追加控制器类名后缀 | 新实例 |
| `withResolver(callable $resolver): self` | 注入项目解析器，参数为控制器名、方法名、请求上下文 | 新实例 |

路由默认不启用：`Bootstrap` 与 `KernelBuilder` 都不会自动接管请求。ThinkPHP 等宿主仍使用自身路由，需要复用 1.x 的「路径直接映射控制器」行为时，在入口显式调用：

```php
$result = \Fxri\Application\Router::make('App', 'Controller')
    ->dispatch(\Fxri\Http\RequestContext::fromGlobals());
```

## 注意事项

- `Bootstrap` 只在调用 `boot()` 时执行装配；构造启动器、注册模块与钩子都不会读盘或执行项目脚本。
- 项目目录扫描顺序固定：`config` → `language/*` → `function/*.php` → `store/*.php`，文件均按名称排序；显式追加的目录与文件排在扫描结果之后，后加载者覆盖先加载者。
- `withHook()` 的钩子只接收启动器，需要内核时用 `$bootstrap->kernel()`；不要在加载阶段读取内核。
- `store/*.php` 是普通 PHP 脚本，执行时已构建内核；返回可调用对象时只调用一次，不返回则按脚本副作用处理。
- `Services` 是显式注册表，不扫描类、不做自动注入；工厂首次解析后缓存，需要每次新建时请自行返回新实例或改用工厂外逻辑。
- 启动失败时 `kernel()` 被清空，但已加载的函数文件不会回滚；函数文件应保持幂等，避免重复定义。
- 应用层不接管路由与请求生命周期，HTTP 入口由接入方框架负责；`Application\Router` 只在显式调用 `dispatch()` 时生效。
- `Router` 只按路径分段解析，不读取 HTTP 方法、不处理查询串；路径少于两段时抛 `RouteNotFoundException`。
- 默认执行方式为无参实例化控制器并调用同名方法；需要依赖注入或容器解析时用 `withResolver()` 接管，解析器异常原样向上抛出。
