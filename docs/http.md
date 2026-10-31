# HTTP

HTTP 层把一次请求的进出两端收敛成只读对象：入口是请求与访问者上下文，出口是固定五字段的响应信封，中间夹着可替换的格式化器、渲染器与 Cookie 容器。

## 定位

这一层不接管路由与中间件，也不替调用方做决策：请求对象要自己从全局变量或 PSR-7 请求里取，响应要不要真的发出去也由接入方决定。它保证的是同一份输入在不同接入方式下得到同样的结构。

| 环节 | 类 | 职责 |
| --- | --- | --- |
| 入口 | `ServerContext`、`RequestContext`、`RequestInput`、`ClientInfo` | 把 `$_SERVER`、PSR-7 请求与各类入参收敛成只读快照 |
| 出口 | `Envelope`、`Formatter`、`JsonFormatter`、`Responder` | 固定五字段信封，按格式化器落成响应体 |
| 视图 | `View`、`Renderer` | 只做模板路径解析与安全校验，模板引擎由接入方实现 |
| Cookie | `CookieJar`、`CookieOptions`、`SameSite` | 读写、删除与 HMAC 签名 |
| 出站 | `HttpClient\Client`、`ClientFactory`、`HttpClient\Curl` | 包装 PSR-18 客户端，或直接以 URL 串发起请求，不绑定具体实现 |

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

// 1. 控制器返回统一 JSON 响应，键固定为 code、message、data、extend、debug
Responder::make()->send(Envelope::success(['id' => 7], '获取成功'));
// {"code":200,"message":"获取成功","data":{"id":7},"extend":[],"debug":{}}

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

对外固定五键：`code`、`message`、`data`、`extend`、`debug`；`success` 只在内部判断成败，不参与输出。`code` 是整数业务码，成功缺省为常量 `Envelope::SUCCESS`（值 `200`）。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `success(mixed $data = null, string $message = '', int $code = self::SUCCESS, array $extend = [], array $debug = []): self` | 构造成功响应 | `self` |
| `failure(int $code, string $message = '', mixed $data = null, array $extend = [], array $debug = []): self` | 构造失败响应 | `self` |
| `isSuccess(): bool` | 是否成功 | `bool` |
| `code(): int` | 取业务码 | `int` |
| `message(): string` | 取提示语 | `string` |
| `data(): mixed` | 取业务数据 | `mixed` |
| `extend(): array` | 取扩展参数 | `array<array-key, mixed>` |
| `debug(): array` | 取调试信息 | `array<array-key, mixed>` |
| `toArray(): array` | 转为五键数组；`debug` 为空时输出空对象 | `array{code: int, message: string, data: mixed, extend: array, debug: array\|object}` |

分页接口的 `data` 使用 `Fxri\Data\Page` 的四键壳（`total`、`records`、`page`、`size`），见[数据](./data)。

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
| `send(Envelope $envelope): string` | 未发头时只设置 `Content-Type`，随后 `echo` 正文 | `string` |
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
| `fromGlobals(array $defaults = [])` | 读 `php://input` 后交给 `fromServer(ServerContext::fromGlobals($defaults), $body)` | `self` |
| `fromPsrRequest(object $request): self` | 依次探测 `getHeaders()`、`getUri()`、`getMethod()`、`getBody()`，缺方法按缺省值处理 | `self` |
| `fromServer(ServerContext $server, string $body = ''): self` | 从服务端变量还原方法、路径、查询串、主机与安全标记 | `self` |
| `make(string $method, string $path, array $headers = [], string $body = '', string $host = '', bool $secure = false, string $queryString = ''): self` | 手工构造 | `self` |
| `method()` / `path()` / `queryString()` / `url()` / `host()` / `isSecure()` / `body()` / `headers()` / `header(string $name, string $default = '')` | 只读取值：方法名转大写、空路径归一为 `/`、查询串去掉开头 `?`、主机为空时 `url()` 只返回路径与查询串；`headers()` 键统一小写，`header()` 不区分大小写 | `string` / `bool` / `array<string, string>` |

### RequestInput

把查询串、表单、JSON 请求体与命令行参数收敛成一个只读取值入口，承接 1.x 门面 `Client::param()` 的能力。`all()` 按查询串、表单、JSON 请求体、命令行参数依次合并，同名键后出现的来源覆盖；请求体不是 JSON 对象时整段忽略。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromGlobals(array $cli = [])` | 读 `$_GET`、`$_POST` 与 `php://input` | `self` |
| `fromRequest(RequestContext $request, array $post = [], array $cli = [])` | 查询串按嵌套结构解码，表单需显式传入 | `self` |
| `make(array $query = [], array $post = [], string $body = '', array $cli = [])` | 手工构造 | `self` |
| `query()` / `post()` / `cli()` | 取对应来源的原始数组 | `array` |
| `body()` | 原始请求体 | `string` |
| `json()` | 请求体按 JSON 解析，非 JSON 对象时为 `null` | `?array` |
| `all()` | 合并全部来源 | `array` |
| `get(string $key, mixed $default = null)` | 从合并结果取单值 | `mixed` |
| `only(array $keys)` | 按结构提取，缺失键补 `null`，数组值递归下钻 | `array` |

### ServerContext

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromGlobals(array $defaults = [])` | 快照 `$_SERVER`；`$defaults` 只在真实变量缺失时兜底，已存在变量优先 | `self` |
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
| `download(object $request, string $file): string` | 发送请求并把响应体写入文件；非 2xx 抛 `RuntimeException`，父目录自动创建，响应体写入前去掉 UTF-8 BOM，响应缺少状态码或正文方法时抛异常 | `string` |
| `withClient(object $client): self` | 换底层客户端，返回新实例 | `self` |

### ClientFactory

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `detect(string ...$candidates): Client` | 按顺序返回第一个可用实现，全部不可用抛 `RuntimeException` | `Client` |
| `has(string $class): bool` | 类存在且实现 `ClientInterface` | `bool` |
| `fromClass(string $class, array $arguments = []): Client` | 反射实例化后包装，类不存在抛 `InvalidArgumentException` | `Client` |

### Curl

以 URL 串为入口发起请求，承接 1.x 门面 `Service::http()`；不依赖 PSR-7/PSR-18，需要 `ext-curl`（列在 `suggest`，未安装时调用即抛 `RuntimeException`）。GET 与 HEAD 的数组数据拼进查询串，其余方法作为请求体并按 RFC 3986 编码；响应体去掉 UTF-8 BOM。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `request(string $url, array\|string $data = '', array $headers = [], string $method = 'GET', ?CurlOptions $options = null)` | 发起请求，返回状态码、响应头与正文 | `CurlResponse` |
| `send(...)` | 同 `request()`，只返回正文 | `string` |
| `download(string $url, string $file, array $headers = [], ?CurlOptions $options = null)` | 下载到文件；非 2xx 抛 `RuntimeException`，父目录自动创建 | `string` 文件路径 |

请求头接受 `['Name: value']` 列表与 `['Name' => 'value']` 映射两种写法。

### CurlOptions

不可变传输选项，替代 1.x 的字符串键选项数组。

| 属性 | 默认 | 说明 |
| --- | --- | --- |
| `timeout` | 30 | 请求超时秒数 |
| `connectTimeout` | 10 | 连接超时秒数 |
| `follow` | `true` | 是否跟随重定向 |
| `maxRedirects` | 5 | 最大重定向次数 |
| `verify` | `true` | 是否校验证书与主机名（1.x 默认关闭，v2 默认开启） |
| `ipv4` | `true` | 是否优先 IPv4 |
| `userAgent` | `null` | 自定义 UA |

`CurlOptions::make(...)` 用同名参数创建实例；属性均为 `public readonly`。

### CurlResponse

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `status(): int` | 状态码 | `int` |
| `isSuccess(): bool` | 是否 2xx | `bool` |
| `headers(): array` | 全部响应头，键为小写 | `array<string, string>` |
| `header(string $name, string $default = ''): string` | 取单个响应头，头名大小写不敏感 | `string` |
| `body(): string` | 响应正文 | `string` |

## 注意事项

- `success()` 与 `failure()` 的参数顺序不同：前者是 `$data, $message, $code`，后者是 `$code, $message, $data`，按位置传参容易错位。
- `code` 是整数业务码，成功缺省用 `Envelope::SUCCESS`；失败场景请给稳定的整数码，不要用 `0` 或负数兼表「无码」。
- `Responder::send()` 在 `headers_sent()` 为真时只写正文，`Content-Type` 也不会生效；模板已输出、`echo` 过调试信息之后别再调 `send()`。
- 框架不设置 HTTP 状态码，成功与失败都按默认 200 输出；需要 401、404、422 等语义时由接入方调用 `http_response_code()`。
- `debug` 恒常输出：`toArray()` 里空数组会转成对象，保证 JSON 侧始终是 `{}` 而不是 `[]`。
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
- `HttpClient\Curl` 需要 `ext-curl`，未安装时 `request()` / `send()` / `download()` 都抛 `RuntimeException`；PSR-18 路线不依赖该扩展。
- `Curl` 默认校验证书与主机名（`CurlOptions::verify` 为 `true`），与 1.x `ssl_peer` / `ssl_host` 默认关闭相反；对接自签证书时才显式传 `verify: false`。
- `Curl::download()` 只在 2xx 时写文件，非 2xx 直接抛异常且不改动目标路径；1.x 会把错误页写进去并用返回值区分。
- `Curl` 的 GET 只拼接数组数据，字符串数据会被忽略；POST 等方法接受数组或原始字符串，数组按 RFC 3986 编码。
- `CurlResponse::headers()` 的键为小写，重定向时只保留最后一跳的响应头；`header()` 查询不区分大小写。
