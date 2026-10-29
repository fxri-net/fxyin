# 响应信封与分页

## 五键信封

`Fxri\Http\Envelope` 对外固定五个键：`code`、`message`、`data`、`extend`、`debug`。`code` 是整数业务码，成功缺省为常量 `Envelope::SUCCESS`（值 `200`）；成败只由内部 `success` 标记判断，不参与输出。

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Http\Envelope;
use Fxri\Http\Responder;

// success 的参数顺序是 数据、提示、码；failure 是 码、提示、数据
Responder::make()->send(Envelope::success(['id' => 7], '获取成功'));
// {"code":200,"message":"获取成功","data":{"id":7},"extend":[],"debug":{}}
```

| 方法 | 说明 |
| --- | --- |
| `success(mixed $data = null, string $message = '', int $code = 200, array $extend = [], array $debug = [])` | 构造成功响应 |
| `failure(int $code, string $message = '', mixed $data = null, array $extend = [], array $debug = [])` | 构造失败响应 |
| `isSuccess()` / `code()` / `message()` / `data()` / `extend()` / `debug()` | 逐项读值 |
| `toArray()` | 转五键数组；`debug` 为空时输出 `{}` 而非 `[]` |

## 分页四键

分页接口的 `data` 用 `Fxri\Data\Page`，四键为 `total`、`records`、`page`、`size`，把实例直接装进 `Envelope::success($page)` 即可，输出时自动展开；非分页接口不构造 `Page`。

```php
use Fxri\Data\Page;
use Fxri\Http\Envelope;

echo json_encode(Envelope::success(Page::make($rows, 168, 2, 10))->toArray(), JSON_UNESCAPED_UNICODE);
```

| 方法 | 说明 |
| --- | --- |
| `make(array $records, int $total, ?int $page = null, ?int $size = null)` | 构造分页壳 |
| `toArray()` | 输出四键数组，值为 `null` 时保留键 |

## 格式化与输出

| 类 | 说明 |
| --- | --- |
| `Formatter` | 接口，只有 `contentType()` 与 `format(Envelope $envelope)` |
| `JsonFormatter::make(int $flags = ...)` | 缺省 `application/json; charset=utf-8`，编码走 `Fxri\Param\Json` |
| `XmlFormatter::make()` | `application/xml; charset=utf-8`，数组按键名展开，列表项统一为 `item` |
| `Responder::make(?Formatter $formatter = null)` | `send()` 未发头时只设 `Content-Type` 并输出正文；`format()` 只序列化 |

```php
use Fxri\Http\Envelope;
use Fxri\Http\JsonFormatter;
use Fxri\Http\Responder;

// 换格式化器后要接住新实例，Responder 不可变
$responder = Responder::make()->withFormatter(JsonFormatter::make(JSON_UNESCAPED_UNICODE));
echo $responder->format(Envelope::failure(422, '参数不合法'));
```

## 调试字段

- `debug` 恒常输出：空数组转成对象，保证 JSON 侧始终是 `{}`。
- 旧协议的 `time` 没有专门字段，需要时由项目写入 `debug.time`。
- 框架不设置 HTTP 状态码，成功与失败都按 200 输出；需要 401、404、422 等语义时由接入方调用 `http_response_code()` 或交给宿主异常映射。

## 注意事项

- `success()` 与 `failure()` 的参数顺序不同，按位置传参容易错位，失败场景建议用具名参数或先构造再读值。
- `Responder::send()` 在 `headers_sent()` 为真时只写正文，`Content-Type` 也不生效；模板或调试信息已输出后不要再调 `send()`。
- `JsonFormatter` 追加 `JSON_THROW_ON_ERROR`：`NAN`、非法 UTF-8 等会抛 `InvalidArgumentException`，不是静默输出 `false`。
- 异常到信封的映射由接入层负责（`Fxri\Integration\ThinkPHP\ExceptionHandler`），框架自身只提供信封与输出器。

完整参数表与逐条注意事项见仓库 `docs/http.md`；分页壳的字段语义见 `docs/data.md`。
