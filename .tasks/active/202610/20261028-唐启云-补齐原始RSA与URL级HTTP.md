---
owner: 唐启云
status: 待办
created: 20261028
updated: 20261028
completed: ''
depends_on: []
scope: common
---

# 补齐原始 RSA 与 URL 级 HTTP

延续「补齐命令行入参与运行时环境」（见 active/202610/20261028-唐启云-补齐命令行入参与运行时环境.md）。逐方法复核发现两处能力只有安全的子集实现，本轮补齐原始语义。

计划位置：本轮第 3 项；前置：补齐命令行入参与运行时环境。

## 背景

- `Safe::rsapri` / `Safe::rsapub` 支持原始 RSA：私钥加密、公钥解密、`OPENSSL_NO_PADDING` 与自定义填充字符，按密钥长度分块。v2 `Security\Rsa` 只有 PKCS#1 公钥加密/私钥解密与 SHA-256 签名验签。
- `Service::http($url, $data, $header, $method, $option)` 以 URL 串为入口发请求，支持方法改写、GET 参数拼接、响应头与响应体分离、重定向与下载。v2 `HttpClient\Client` 只接受 PSR-7 请求对象，没有 URL 级入口。
- `Server::ip()` 通过外部 HTTP 服务取本机公网地址，与 `ClientInfo::ip()` 的客户端地址不是一回事。

## 实施方案

1. 新增 `Security\RsaPadding` 枚举（`Pkcs1`、`NoPadding`），由枚举给出填充常量与每块填充字节数。
2. `Rsa` 新增 `privateEncrypt()` 与 `publicDecrypt()`：按密钥字节数分块，`NoPadding` 时按指定字符左填充与解码后左侧剥离；现有 `encrypt()` / `decrypt()` 保持不变并复用同一分块逻辑。
3. 新增 `HttpClient\Curl`：`request()` 返回 `CurlResponse`（状态码、头、正文），`send()` 返回正文，`download()` 落盘；GET 的参数拼进地址，其余方法作为请求体；默认校验证书、跟随重定向、优先 IPv4。
4. 新增 `HttpClient\CurlResponse`：只读状态码、头与小写头名取值。
5. `composer.json` 的 `suggest` 增加 `ext-curl`；未安装扩展时调用即抛异常，不静默降级。
6. 补测试：原始 RSA 往返与超长分块、`NoPadding` 填充剥离、URL 级请求（本机临时服务）、非 2xx 下载抛异常。
7. 更新对等快照与文档：`Safe::rsapri/rsapub` → `Rsa::privateEncrypt/publicDecrypt`，`Service::http` → `HttpClient\Curl`，`Server::ip` → `HttpClient\Curl` + `Param\Json` 组合。

## 影响范围

`src/Security/RsaPadding.php`（新增）、`src/Security/Rsa.php`、`src/Http/HttpClient/Curl.php`（新增）、`src/Http/HttpClient/CurlResponse.php`（新增）、`composer.json`、对应测试、`docs/security.md`、`docs/http.md`、`docs/parity-1.6.1.md`、`tests/Parity/legacy-api.json`；不改变现有 PSR-18 客户端行为。

## 验证方式

1. 新增 RSA 与 HTTP 用例通过，本机服务不可用时跳过而不误报。
2. `pnpm verify` 全绿；对等审计计数不变。
3. 未安装 `ext-curl` 的环境仍可加载并运行其他能力。

## 回滚方案

新增枚举、方法与新类可整体回退；`Rsa` 现有方法签名与行为不变，回退不影响既有调用。
