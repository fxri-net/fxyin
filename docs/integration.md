# 接入

把装配好的内核绑进接入方容器，并把框架内的可预期异常映射成固定响应信封，这两件事由 `Fxri\Integration\ThinkPHP` 提供。

## 定位

接入层位于 `src/Integration/ThinkPHP`，是可选的集成代码，框架自身不依赖 ThinkPHP：`composer.json` 的 `require` 中只有 `psr/log` 与 `symfony/translation-contracts` 两个包依赖，`topthink/framework` 仅出现在 `suggest` 中，说明为「需要 ThinkPHP 容器适配与异常映射时安装」。

`ThinkPHPAdapter` 只做容器绑定与解析，`ExceptionHandler` 只做异常到信封的映射。两者的公共入口都是静态方法或工厂方法，不注册全局异常处理器、不接管 ThinkPHP 的响应流程，何时启用由接入方决定。

这两个类不引用任何 `think\*` 类定义：判定用 `class_exists('think\App')`，调用容器方法用 `method_exists()` 加 `ReflectionMethod`。因此未安装 ThinkPHP 时类依然可以加载，`isAvailable()` 返回 `false`，接入方据此跳过绑定即可。

`ExceptionHandler` 与[HTTP](./http)层的 `Envelope`、`Responder` 衔接：`envelope()` 只产出信封对象，`respond()` 再交给 `Responder::send()` 落到底层输出，格式由 `Fxri\Http\Formatter` 决定，缺省是 JSON。

## 最小示例

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Exception\ValidationException;
use Fxri\Integration\ThinkPHP\ExceptionHandler;
use Fxri\Integration\ThinkPHP\ThinkPHPAdapter;
use Fxri\KernelBuilder;
use think\App;

$kernel = KernelBuilder::make()->build();

// 一、应用初始化阶段：把内核绑进容器，标识缺省为 fxri.kernel
if (ThinkPHPAdapter::isAvailable()) {
    $app = new App();
    ThinkPHPAdapter::bind($app, $kernel);

    // 任意位置按同一标识取回内核
    $resolved = ThinkPHPAdapter::kernel($app);
}

// 二、异常统一渲染：校验失败映射为 invalid，其余异常只回通用文案
$handler = ExceptionHandler::make($kernel);

try {
    throw new ValidationException('参数不合法');
} catch (Throwable $error) {
    $envelope = $handler->envelope($error);

    echo $envelope->code();     // invalid
    echo $envelope->message();  // 参数不合法

    // 需要直接输出时调用 respond()：写 JSON 并置状态码（失败为 400）
    // $handler->respond($error);
}
```

## 参数与返回值

### ThinkPHPAdapter

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `isAvailable(): bool` | 判定当前运行时是否具备 ThinkPHP，条件是 `class_exists('think\App')` | 是否可用 |
| `bind(object $container, Kernel $kernel, string $id = self::KERNEL): void` | 通过反射调用容器的 `instance($id, $kernel)` 完成绑定；容器缺少 `instance()` 时抛 `LogicException` | 无 |
| `kernel(object $container, string $id = self::KERNEL): Kernel` | 通过反射调用容器的 `make($id)` 取回内核；容器缺少 `make()`、或解析结果不是 `Kernel` 时抛 `LogicException` | 内核实例 |

`KERNEL` 常量的值为 `'fxri.kernel'`，同时作为 `bind()` 与 `kernel()` 的缺省标识。

### ExceptionHandler

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(Kernel $kernel, ?Formatter $formatter = null): self` | 创建实例，`$formatter` 为 `Fxri\Http\Formatter`，缺省用 `JsonFormatter` | 异常响应适配 |
| `envelope(Throwable $error): Envelope` | 按异常类型映射为响应信封 | `Fxri\Http\Envelope` |
| `respond(Throwable $error): string` | 先 `envelope()` 再 `Responder::send()`，输出响应体并返回 | 已输出的响应体 |

类内还定义了三个失败码常量：`INVALID`（`'invalid'`）、`UNCHANGED`（`'unchanged'`）、`INTERNAL`（`'internal'`）。

映射规则按源码逐条对应：

- `Fxri\Exception\ValidationException` 映射为 `Envelope::failure('invalid', $error->getMessage())`，业务码 `invalid`，提示语取异常消息。
- `Fxri\Exception\SameDataException` 映射为 `Envelope::failure('unchanged', $error->getMessage())`，业务码 `unchanged`，提示语取异常消息。
- 其余 `Throwable` 先调用 `$kernel->logger()->error($error->getMessage(), ['exception' => $error])` 记日志，再返回 `Envelope::failure('internal', '服务内部错误')`，不透传原始消息。
- `Envelope::failure()` 的 `ok` 为 `false`；`Responder::send()` 置 `Content-Type` 为格式化器的内容类型（JSON 缺省为 `application/json; charset=utf-8`），状态码按 `isOk()` 取 200 或 400，已发出响应头时只写正文。

## 注意事项

- 本层要求接入方自行安装 `topthink/framework`，框架不把它列入 `require`。未安装时两个类可正常加载，但 `isAvailable()` 为 `false`，绑定与解析需要真实的 ThinkPHP 容器。
- `bind()` 与 `kernel()` 的 `$id` 必须一致；用了自定义标识就要两处都传，否则 `kernel()` 抛「容器中未绑定内核」。
- `bind()` 只校验容器有 `instance()` 方法，不校验该类是否为 ThinkPHP 容器；传入任何具备同名方法的对象都会被接受，出错时表现为容器内部行为异常而非清晰报错。
- 容器方法通过反射按位置传参，因此容器方法签名变化（如 `instance()` 增加必填参数）不会被静态分析提前发现，升级 ThinkPHP 后需回归这两处调用。
- `envelope()` 对未知异常只返回 `internal` 与「服务内部错误」，原始消息只进日志；排查线上问题时从日志取 `exception` 上下文，而不是看响应体。
- `ValidationException` 与 `SameDataException` 的消息会原样返回给调用方，构造消息时不要拼接 SQL、密钥、内部路径等敏感内容。
- `respond()` 会直接 `echo` 并设置响应头与状态码。ThinkPHP 已有自己的响应生命周期，在控制器或中间件里应优先用 `envelope()` 把信封交回框架渲染，避免重复输出。
- 映射只按异常类型判断，不看 `code` 或 HTTP 状态。`ValidationException` 继承 `InvalidArgumentException`、`SameDataException` 继承 `RuntimeException`，自定义异常若只继承这两个基类，不会命中前两条分支，仍落到 `internal`。
- `isAvailable()` 只判 `think\App` 是否存在，不校验版本；绑定与解析依赖容器的 `instance()`、`make()` 方法名，换大版本前先跑一次绑定与解析验证。
- 绑定的是内核实例本身，容器不会延迟构造内核；若需要容器按需构建，需接入方自行封装工厂，本适配器不提供。
- 失败码是字符串 `invalid`、`unchanged`、`internal`，与成功码 `Envelope::OK`（`'ok'`）不是同一套取值，前端判断应看 `ok` 字段而不是比对 `code` 的具体值。
- 需要非 JSON 输出时，实现 `Fxri\Http\Formatter` 接口并通过 `make()` 的第二个参数注入；`respond()` 的内容类型随之改变，`envelope()` 不受影响。
- 内核只承载配置、日志、翻译等读取入口（见[内核与装配](./kernel)），适配器只是被绑定的对象之一，框架不会把参数、数据、SQL 等静态能力注册进容器。
