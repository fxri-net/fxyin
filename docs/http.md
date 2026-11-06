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


各类的构造方式、方法签名与返回值见[HTTP API](./http-api)。

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
