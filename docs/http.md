# HTTP

HTTP 层把一次请求的进出两端收敛成只读对象：入口是请求与访问者上下文，出口是固定四字段的响应信封，中间夹着可替换的格式化器、渲染器与 Cookie 容器。

## 定位

这一层不接管路由与中间件，也不替调用方做决策：请求对象要自己从全局变量或 PSR-7 请求里取，响应要不要真的发出去也由接入方决定。它保证的是同一份输入在不同接入方式下得到同样的结构。

| 环节 | 类 | 职责 |
| --- | --- | --- |
| 入口 | `ServerContext`、`RequestContext`、`ClientInfo` | 把 `$_SERVER` 与 PSR-7 请求收敛成只读快照 |
| 出口 | `Envelope`、`Formatter`、`JsonFormatter`、`Responder` | 固定四字段信封，按格式化器落成响应体 |
| 视图 | `View`、`Renderer` | 只做模板路径解析与安全校验，模板引擎由接入方实现 |
| Cookie | `CookieJar`、`CookieOptions`、`SameSite` | 读写、删除与 HMAC 签名 |
| 出站 | `HttpClient\Client`、`ClientFactory` | 包装 PSR-18 客户端，不绑定具体实现 |

与相邻层的交界：`JsonFormatter` 的编码交给 `Fxri\Param\Json`（见[参数](./param)），`View` 用 `Fxri\File\Path::join()` 拼模板路径（见[文件](./file)）；内核只暴露 `httpClient()` 一个入口，装配方式见[内核与装配](./kernel)；异常到信封的映射在[接入](./integration)。

PSR 相关能力都是可选的：`RequestContext::fromPsrRequest()` 与 `ServerContext::fromPsrRequest()` 只按方法名探测，没有 PSR-7 实现也能加载类；`HttpClient\Client` 与 `ClientFactory` 需要接口存在（`Psr\Http\Client\ClientInterface` 与 PSR-7 消息接口），未安装时调用即报错。

## 最小示例

三个场景写在同一个脚本里：返回统一 JSON 响应、取请求上下文与客户端地址、读写带签名的 Cookie。Cookie 密钥由部署环境注入：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Http\ClientInfo;
use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\Cookie\CookieOptions;
use Fxri\Http\Envelope;
use Fxri\Http\IpSource;
use Fxri\Http\RequestContext;
use Fxri\Http\Responder;

// 1. 控制器返回统一 JSON 响应，键固定为 ok、code、message、data
Responder::make()->send(Envelope::success(['id' => 7], '获取成功'));
// {"ok":true,"code":"ok","message":"获取成功","data":{"id":7}}

// 2. 从全局变量取请求上下文与客户端地址
// 以下按一次 Web 请求给出预期输出：REMOTE_ADDR 为 10.0.0.2，X-Forwarded-For 为 203.0.113.9
$request = RequestContext::fromGlobals();
$client = ClientInfo::fromGlobals(IpSource::Forwarded, ['10.0.0.0/8']);

echo $request->method() . ' ' . $request->url() . PHP_EOL;   // GET http://example.com/order?page=2
echo $client->ip() . ' ' . $client->browser() . PHP_EOL;     // 203.0.113.9 Google Chrome

// 3. 读写带签名的 Cookie，APP_COOKIE_SECRET 由部署环境注入
$jar = CookieJar::fromGlobals((string) getenv('APP_COOKIE_SECRET'));
$options = CookieOptions::make(time() + 86400, '/', '', true, true);
$jar->set('uid', $jar->signedValue('uid', '7'), $options);
echo $jar->signed('uid');   // 7
```

## 参数与返回值

签名按源码列出，省略参数上的 `#[\SensitiveParameter]` 标注。

### Envelope

成功码固定为常量 `Envelope::OK`（值 `'ok'`），`code` 传空串会在构造期抛 `InvalidArgumentException`。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(bool $ok, string $code, mixed $data = null, string $message = ''): self` | 通用构造，注意 `$data` 在 `$message` 之前 | `self` |
| `success(mixed $data = null, string $message = '', string $code = self::OK): self` | 构造成功响应 | `self` |
| `failure(string $code, string $message = '', mixed $data = null): self` | 构造失败响应 | `self` |
| `isOk(): bool` | 是否成功 | `bool` |
| `code(): string` | 取业务码 | `string` |
| `message(): string` | 取提示语 | `string` |
| `data(): mixed` | 取业务数据 | `mixed` |
| `toArray(): array` | 转为 `ok`、`code`、`message`、`data` 四键数组 | `array{ok: bool, code: string, message: string, data: mixed}` |

### Formatter

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `contentType(): string` | 响应内容类型 | `string` |
| `format(Envelope $envelope): string` | 把信封转成响应体 | `string` |

### JsonFormatter

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(int $flags = JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES): self` | 创建格式化器，`$flags` 会追加给 `Json::encode()` | `self` |
| `contentType(): string` | 固定返回 `application/json; charset=utf-8` | `string` |
| `format(Envelope $envelope): string` | 编码 `toArray()` 的结果，失败抛 `InvalidArgumentException` | `string` |

### XmlFormatter

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(): self` | 创建 XML 格式化器 | `self` |
| `contentType(): string` | 固定返回 `application/xml; charset=utf-8` | `string` |
| `format(Envelope $envelope): string` | 输出 XML 声明与 `<response>` 根节点；数组按键名展开，列表项统一为 `item`，非法节点名替换为下划线 | `string` |

### Renderer

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `render(string $template, array $data): string` | 渲染模板，`$template` 是模板文件路径 | `string` |

### Responder

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(?Formatter $formatter = null): self` | 创建实例，缺省用 `JsonFormatter::make()` | `self` |
| `send(Envelope $envelope): string` | 未发头时设置 `Content-Type` 与状态码（成功 200、失败 400），随后 `echo` 正文 | `string` |
| `format(Envelope $envelope): string` | 只格式化，不输出 | `string` |
| `contentType(): string` | 透传格式化器的内容类型 | `string` |
| `withFormatter(Formatter $formatter): self` | 换格式化器，返回新实例 | `self` |

### View

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $directory, Renderer $renderer): self` | 指定模板目录与渲染器 | `self` |
| `directory(): string` | 取模板目录 | `string` |
| `render(string $template, array $data = []): string` | 解析路径后交给渲染器；模板名为空、含 `..` 或文件不存在时抛 `InvalidArgumentException` | `string` |
| `withDirectory(string $directory): self` | 换模板目录，返回新实例 | `self` |

### RequestContext

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromGlobals(): self` | 读 `php://input` 后交给 `fromServer(ServerContext::fromGlobals(), $body)` | `self` |
| `fromPsrRequest(object $request): self` | 依次探测 `getHeaders()`、`getUri()`、`getMethod()`、`getBody()`，缺方法按缺省值处理 | `self` |
| `fromServer(ServerContext $server, string $body = ''): self` | 从服务端变量还原方法、路径、查询串、主机与安全标记 | `self` |
| `make(string $method, string $path, array $headers = [], string $body = '', string $host = '', bool $secure = false, string $queryString = ''): self` | 手工构造 | `self` |
| `method(): string` | 请求方法，构造时转大写 | `string` |
| `path(): string` | 请求路径，空串归一为 `/` | `string` |
| `queryString(): string` | 查询串，去掉开头的 `?` | `string` |
| `url(): string` | 完整地址；主机名为空时只返回路径与查询串 | `string` |
| `header(string $name, string $default = ''): string` | 取单个请求头，头名大小写不敏感 | `string` |
| `headers(): array` | 全部请求头，键为小写 | `array<string, string>` |
| `host(): string` | 取主机名 | `string` |
| `isSecure(): bool` | 是否 HTTPS | `bool` |
| `body(): string` | 取请求体 | `string` |

### ServerContext

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromGlobals(): self` | 快照 `$_SERVER` | `self` |
| `fromPsrRequest(object $request): self` | 取 `getServerParams()`，方法不存在时为空数组 | `self` |
| `make(array $server = []): self` | 手工构造，键统一转字符串 | `self` |
| `all(): array` | 全部变量 | `array<string, mixed>` |
| `get(string $name, string $default = ''): string` | 取单个变量，缺失或值为空串时返回默认值 | `string` |
| `has(string $name): bool` | 变量是否存在，值为空串也算存在 | `bool` |

### BranchContext

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `resolve(string $host, array $store, string $default = ''): self` | 从分支配置解析主机：完整主机名优先，其次 `^` 前缀部分匹配，最后 `$` 包裹的完整正则；键内多个主机用逗号分隔 | `self` |
| `branch(): string` | 命中的分支名，未命中时为默认分支 | `string` |
| `config(): mixed` | 分支对应的配置，未命中时为 `null` | `mixed` |
| `host(): string` | 原始主机名 | `string` |

### ClientInfo

默认只信 `REMOTE_ADDR`；`$trustedProxies` 是 CIDR 网段列表（如 `10.0.0.0/8`），用于判定对端是否为可信代理。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromGlobals(IpSource $ipSource = IpSource::Remote, array $trustedProxies = []): self` | 用 `ServerContext::fromGlobals()` 构造 | `self` |
| `make(ServerContext $server, IpSource $ipSource = IpSource::Remote, array $trustedProxies = []): self` | 用给定服务端上下文构造 | `self` |
| `ip(): string` | 客户端地址；对端无法识别时为 `0.0.0.0` | `string` |
| `userAgent(): string` | 原始 UA 串 | `string` |
| `browser(CaseFormat $format = CaseFormat::Original): string` | 浏览器名，未命中为 `Others` | `string` |
| `system(CaseFormat $format = CaseFormat::Original): string` | 操作系统名，未命中为 `Others` | `string` |
| `isTrustedProxy(): bool` | 对端地址是否落在 `$trustedProxies` 内 | `bool` |

浏览器按固定顺序匹配 UA 片段：`micromessenger`、`alipayclient`、`edg/`、`opr/`、`firefox`、`chrome`、`safari`、`trident`，依次对应 `MicroMessenger`、`Alipay`、`Microsoft Edge`、`Opera`、`Firefox`、`Google Chrome`、`Safari`、`Internet Explorer`；系统按 `windows nt`、`android`、`iphone`/`ipad`/`ipod`、`mac os x`、`linux` 匹配为 `Windows`、`Android`、`iOS`、`macOS`、`Linux`。

### CaseFormat

| 分支 | 含义 |
| --- | --- |
| `Original` | 保持原样，也是 `browser()` 与 `system()` 的默认值 |
| `Lower` | 转为小写 |
| `Upper` | 转为大写 |

`apply(string $value): string` 按当前分支转换字符串。

### IpSource

| 分支 | 含义 |
| --- | --- |
| `Remote` | 直连对端地址（`REMOTE_ADDR`），默认值 |
| `Forwarded` | 转发链地址（`X-Forwarded-For`），需同时配置可信代理网段 |

### CookieJar

`$secret` 传 `null` 或空串都视为未配置签名密钥。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(array $cookies = [], ?string $secret = null): self` | 用给定 Cookie 数组构造 | `self` |
| `fromGlobals(?string $secret = null): self` | 读取 `$_COOKIE` 构造 | `self` |
| `get(string $name, string $default = ''): string` | 读取值，缺失时返回默认值 | `string` |
| `set(string $name, string $value, ?CookieOptions $options = null): bool` | 下发 Cookie；已发头或 `setcookie()` 失败时返回 `false` | `bool` |
| `delete(string $name, ?CookieOptions $options = null): bool` | 复用原选项下发过期时间并清掉镜像，失败返回 `false` | `bool` |
| `has(string $name): bool` | 镜像中是否存在 | `bool` |
| `all(): array` | 全部 Cookie | `array<string, string>` |
| `signed(string $name, string $default = ''): string` | 读值并验签，缺失或验签失败返回默认值 | `string` |
| `signedValue(string $name, string $value): string` | 生成「值 + 64 位 HMAC」的待写入串 | `string` |

签名是 `hash_hmac('sha256', $name . '|' . $value, $secret)` 的十六进制结果，直接追加在值后面；未配置密钥时签名相关调用抛 `LogicException`。

### CookieOptions

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true, SameSite $sameSite = SameSite::Lax): self` | 创建选项，`$expires` 是时间戳，0 表示会话级 | `self` |
| `toArray(): array` | 转成 `setcookie()` 的选项数组 | `array` |

属性 `expires`、`path`、`domain`、`secure`、`httpOnly`、`sameSite` 均为 `public readonly`；`toArray()` 只在 `domain` 非空时带 `domain`、只在 `secure` 为真时带 `secure`，`samesite` 展开为字面量。

### SameSite

| 分支 | 含义 |
| --- | --- |
| `Strict` | 仅同站请求发送 |
| `Lax` | 同站请求与顶层跳转发送，默认值 |
| `None` | 跨站请求也发送，需同时开启 `secure` |

### Client

三个接口名以字符串常量暴露：`Client::CLIENT_INTERFACE`、`Client::REQUEST_INTERFACE`、`Client::RESPONSE_INTERFACE`。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(object $client): self` | 校验实现了 `Psr\Http\Client\ClientInterface`，不符抛 `InvalidArgumentException` | `self` |
| `send(object $request): object` | 校验请求实现 `Psr\Http\Message\RequestInterface`，调用 `sendRequest()` 并校验响应实现 `ResponseInterface` | `object` |
| `client(): object` | 取底层客户端 | `object` |
| `download(object $request, string $file): string` | 发送请求并把响应体写入文件；非 2xx 抛 `RuntimeException`，父目录自动创建 | `string` |
| `withClient(object $client): self` | 换底层客户端，返回新实例 | `self` |

### ClientFactory

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `detect(string ...$candidates): Client` | 按顺序返回第一个可用实现，全部不可用抛 `RuntimeException` | `Client` |
| `has(string $class): bool` | 类存在且实现 `ClientInterface` | `bool` |
| `fromClass(string $class, array $arguments = []): Client` | 反射实例化后包装，类不存在抛 `InvalidArgumentException` | `Client` |

## 注意事项

- `Envelope::make()` 的参数顺序是 `$ok, $code, $data, $message`，与 `success()`、`failure()` 都不同，按位置传参容易把提示语塞进 `data`。
- `code` 不能是空串，构造期就抛 `InvalidArgumentException`；失败场景请给稳定的业务码，而不是用 `''` 表示「无码」。
- `Responder::send()` 在 `headers_sent()` 为真时只写正文，`Content-Type` 与状态码都不会生效；模板已输出、`echo` 过调试信息之后别再指望 400 落到状态行。
- 状态码只有 200 与 400 两档，其余语义靠 `code` 字段表达；需要 401、404、422 时自行调用 `http_response_code()` 或不用 `send()`。
- `JsonFormatter` 走 `Fxri\Param\Json::encode()`，会追加 `JSON_THROW_ON_ERROR`：`NAN`、非法 UTF-8 等会抛 `InvalidArgumentException`，不是静默输出 `false`。
- `Responder` 不可变，`withFormatter()` 返回新实例；拿到返回值后忘了重新赋值，用的还是旧的格式化器。
- `View::render()` 的模板名不接受 `..`，且模板文件必须已存在，越界或拼错都抛 `InvalidArgumentException`；`Renderer` 接口只收「路径 + 数据」，模板引擎的缓存与转义由实现方负责。
- `RequestContext::fromGlobals()` 每次都重新读 `php://input`，同一个请求里请复用实例，而不是到处调用。
- `fromServer()` 的方法缺省为 `GET`，主机取 `HTTP_HOST` 再回落 `SERVER_NAME`，`isSecure()` 只看 `HTTPS` 是否为 `on` 或 `1`，不读 `X-Forwarded-Proto`；反代终止 TLS 时要自行判断安全标记。
- `headers()` 的键统一小写、多值头用 `, ` 合并；`header()` 查值时也不区分大小写，但不要拿 `headers()` 的键去做大小写敏感的比较。
- `fromPsrRequest()` 不读 `getServerParams()`，需要服务端变量时另用 `ServerContext::fromPsrRequest()`；缺 `getUri()` 时路径落到 `/`、主机取 `host` 头。
- `ClientInfo` 默认忽略 `X-Forwarded-For`；切到 `IpSource::Forwarded` 还需 `REMOTE_ADDR` 命中 `$trustedProxies`，否则仍返回对端地址。
- `$trustedProxies` 只认 CIDR 写法：不带 `/` 的裸 IP 永远匹配不上，IPv4 与 IPv6 网段不能混用。
- 转发链从右往左取第一个非可信地址，候选不是合法 IP 时回落到对端地址；整条链都不可信等于没配，别把 `0.0.0.0` 当成真实客户端。
- `browser()` 与 `system()` 的匹配有顺序：微信内置浏览器的 UA 同时含 `chrome` 与 `safari`，返回的是 `MicroMessenger`；匹配基于小写副本，`userAgent()` 返回原始串。
- `CookieJar::set()` 在已发头或 `setcookie()` 失败时返回 `false` 而不抛异常，调用方要显式判断；写入成功后镜像同步更新，同请求内 `get()` 能读到新值。
- `delete()` 默认按 `CookieOptions::make()` 的路径下发过期时间，原 Cookie 的路径或域不同就删不掉，请传入写入时的同一份选项。
- Cookie 签名把名字并入 HMAC，改名后验签必然失败；`signed()` 对短于 64 字符的值直接返回默认值，未配置密钥且值够长时抛 `LogicException`。
- `SameSite::None` 必须配 `secure => true`，否则浏览器会丢弃 Cookie；`CookieOptions::toArray()` 不会替你补这个组合。
- `Client::make()` 与 `ClientFactory::has()` 依赖 PSR 接口已安装：接口不存在时 `is_a()` 为假，`make()` 直接抛异常，`detect()` 找不到实现也不会静默降级。
- `Client::send()` 会校验请求与响应类型，返回的是 PSR-7 响应对象；`ClientFactory::fromClass()` 的 `$arguments` 按位置传给构造函数。
