# 安全

安全层把摘要、口令散列、对称与非对称加解密、令牌签发这些容易写错的原语收进具名类，调用方只需挑对方法，不必自己拼 IV、填充与签名格式。

## 定位

`Fxri\Security` 是能力层里最独立的一块：不依赖内核，也不经过容器，全部是 `final` 类上的静态方法（`Cipher` 例外，它持有密钥实例），直接 `use` 即可。

| 类 | 解决什么 |
| --- | --- |
| `Cipher` | 应用内部数据的对称加密，`aes-256-gcm` 认证加密 |
| `Hash` | MD5 兼容摘要、口令散列、常量时间比较 |
| `Rsa` | 跨系统交换密钥时的非对称加密与签名 |
| `Token` | 自带过期时间的无状态令牌，可配一次性消费 |
| `DesCipher` | 读写 1.x 由单 DES 写下的历史密文 |
| `DesToken` | 读写 1.x 签发的旧格式令牌 |

选型按「数据要交给谁」分：只由本应用解开的数据用 `Cipher`；要把数据交给对方、或要验证请求确实来自持私钥的一方，用 `Rsa`；只在短消息里携带过期时间与随机标识、又要免存储校验，用 `Token`。`Hash`、`DesCipher`、`DesToken` 属于兼容入口，新数据不应再产生它们对应的格式。

它不替代密钥管理。密钥从环境变量、配置中心或密钥管理服务读取，源码与仓库里只出现占位值；旧口令与签名密钥同理。

与相邻层的交界有三处：出站请求由 `Fxri\Http\HttpClient\Client` 发出，需要把密文放进 URL 时自行做 URL 编码；Cookie 的签名在 `Fxri\Http\Cookie\CookieJar` 内部完成（见 [HTTP](./http)）；异常最终由接入层的异常处理器映射成响应信封，错误信息里不要带明文、口令与密钥。

## 最小示例

示例假定 `APP_KEY`（64 位十六进制，即 32 字节）与 `APP_SIGN_KEY` 已在环境变量中配置，换成自己的密钥来源即可：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Security\Cipher;
use Fxri\Security\Hash;
use Fxri\Security\HashLength;
use Fxri\Security\Token;

// 密钥与签名密钥由部署环境注入，源码里不写死
$key = hex2bin((string) getenv('APP_KEY'));
$signingKey = (string) getenv('APP_SIGN_KEY');

// 对称加密：IV 随机，同一明文每次加密结果都不同
$cipher = Cipher::make($key);
$payload = $cipher->encrypt('{"uid":7}');
echo $cipher->decrypt($payload);   // {"uid":7}

// 口令散列自带随机盐，每次结果不同，只能用 verify 校验
$hash = Hash::password('secret');
var_dump(Hash::verify('secret', $hash));   // bool(true)

// MD5 只用于兼容历史数据，Short16 取 32 位结果的中间 16 位
echo Hash::md5('abc', HashLength::Short16);   // 3cd24fb0d6963f7d

// 令牌默认 1 小时有效，verify 通过后返回载荷
$token = Token::make($signingKey, 600);
var_dump(Token::verify($token, $signingKey));
// array(2) { ["id"]=> string(32) "…", ["expires_at"]=> int(…) }

// 常量时间比较，适用于签名与令牌
var_dump(Hash::equals('abc', 'abc'));   // bool(true)
```

## 参数与返回值

签名按源码列出，省略 `#[\SensitiveParameter]` 标注：密钥、口令、明文参数都带该标注，异常栈里不会显示其值。

### Cipher

算法固定为 `aes-256-gcm`，密钥 32 字节、IV 12 字节、认证标签 16 字节。`encrypt()` 用 `Fxri\Support\Random::bytes()` 取随机 IV，返回 `base64(IV . tag . 密文)`；`decrypt()` 按同样的顺序切分。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $key): self` | 用 32 字节密钥创建实例，长度不符抛 `InvalidArgumentException` | `self` |
| `encrypt(string $plain): string` | 加密并输出 base64 串，底层失败抛 `RuntimeException` | `string` |
| `decrypt(string $payload): string` | 校验并解密，格式不合法抛 `InvalidArgumentException`，验签失败抛 `RuntimeException` | `string` |

### Hash

`md5()` 仅为兼容历史数据保留；新场景一律用 `password()` 与 `verify()`。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `md5(string $data, HashLength $length = HashLength::Full32): string` | 计算 MD5 十六进制摘要，`Short16` 时取 `substr($hash, 8, 16)` | `string` |
| `password(string $password): string` | 用 `password_hash()` 与 `PASSWORD_DEFAULT` 生成可直接入库的散列 | `string` |
| `verify(string $password, string $hash): bool` | 用 `password_verify()` 校验明文与散列 | `bool` |
| `equals(string $known, string $given): bool` | 走 `hash_equals()`，耗时与内容无关 | `bool` |

### HashLength

| 分支 | 含义 |
| --- | --- |
| `Short16` | 16 位，取 32 位结果的中间一段 |
| `Full32` | 32 位完整结果，也是 `md5()` 的默认值 |

### Rsa

`MIN_BITS` 为 2048。`encrypt()` / `decrypt()` 走 PKCS#1 填充，加密按「密钥字节数 - 11」分块，解密按密钥字节数切块，各块原始字节拼接后整体 base64；`privateEncrypt()` / `publicDecrypt()` 支持 `RsaPadding` 指定的填充方式，返回原始字节而不做 base64。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `generate(int $bits = 2048): array` | 生成 PEM 密钥对，小于 2048 位抛 `InvalidArgumentException` | `array{private_key: string, public_key: string}` |
| `encrypt(string $data, string $publicKey): string` | `OPENSSL_PKCS1_PADDING` 分块公钥加密，空串原样返回空串 | `string` |
| `decrypt(string $payload, string $privateKey): string` | base64 非法抛 `InvalidArgumentException`，分块解密失败抛 `RuntimeException` | `string` |
| `privateEncrypt(string $data, string $privateKey, RsaPadding $padding = RsaPadding::Pkcs1, string $pad = "\0"): string` | 私钥加密，用于「私钥加密、公钥解密」；`NoPadding` 时按 `$pad` 左补齐，空串返回空串 | `string` 原始字节 |
| `publicDecrypt(string $payload, string $publicKey, RsaPadding $padding = RsaPadding::Pkcs1, string $pad = "\0"): string` | 公钥解密；`NoPadding` 时剥离左侧补齐字符 | `string` |
| `sign(string $data, string $privateKey): string` | `OPENSSL_ALGO_SHA256` 签名，结果为 base64 | `string` |
| `verify(string $data, string $signature, string $publicKey): bool` | SHA-256 验签，签名 base64 非法时直接返回 `false` | `bool` |
| `normalizePem(string $pem): string` | 接受 PEM 文本或文件路径，去掉空白后按 64 字符折行并保留头尾标记；空内容抛 `InvalidArgumentException` | `string` |

公钥、私钥不合法时抛 `InvalidArgumentException`（文案分别为「公钥不合法」「私钥不合法」）。

### RsaPadding

| 分支 | 含义 | 每块填充字节 |
| --- | --- | --- |
| `Pkcs1` | `OPENSSL_PKCS1_PADDING`，默认值 | 11 |
| `NoPadding` | `OPENSSL_NO_PADDING`，由调用方补齐 | 至少 1（用于左侧补齐） |

`constant()` 返回 openssl 填充常量，`overhead()` 返回填充字节数；分块大小统一为「密钥字节数 - 填充字节数」。

### Token

令牌形态为 `v1.<base64url 载荷>.<base64url 签名>`，签名是 HMAC-SHA256，签名内容为 `v1.<载荷>`，`base64url` 去掉填充。载荷只有 `id`（随机字节的十六进制）与 `exp`（过期时间戳）两个字段。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $key, int $ttl = 3600, int $bytes = 16): string` | 签发令牌；`$ttl` 单位为秒，默认值即 `DEFAULT_TTL`；`$bytes` 为标识的随机字节数 | `string` |
| `verify(string $token, string $key, ?callable $consume = null): ?array` | 校验版本、签名、载荷与过期时间，任何一项不通过返回 `null` | `array{id: string, expires_at: int}` 或 `null` |

`make()` 的三条前置校验：`$key` 不能为空串、`$ttl` 必须大于 0、`$bytes` 不得小于 8，违反时抛 `InvalidArgumentException`。`$consume` 是 `callable(string $id): bool`，返回 `true` 表示首次使用，返回其他值视为已使用。

### DesCipher

只用于读写 1.x 旧数据。旧实现用单 DES，现代 OpenSSL 已移除该算法，这里把口令截断或补零到 8 字节，再重复三份走 `des-ede3-ecb` 等价还原。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `encrypt(string $plain, ?string $password = null): string` | 按旧格式加密并输出 base64；口令为空或 `null` 抛 `InvalidArgumentException` | `string` |
| `decrypt(string $payload, ?string $password = null): string` | `$payload` 为旧实现的 base64 密文；口令为空或 `null` 抛 `InvalidArgumentException`，解密失败抛 `RuntimeException` | `string` |

### DesToken

读写 1.x 令牌：数据用逗号包裹、补随机字符到 2 的幂后走 `DesCipher`，最终输出十六进制串。新数据请用 `Token`。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `encode(string\|array $data, string $password): string` | 生成旧格式令牌；数组按逗号拼接，空数据抛 `InvalidArgumentException` | `string` |
| `decode(string $token, string $password): ?string` | 还原原始逗号串；长度、字符集或解密失败时返回 `null` | `string\|null` |

两个方法都要求显式传入口令；1.x 默认口令为 `00000000`，请按旧项目的实际配置传入。

## 注意事项

- `Cipher::make()` 的密钥必须恰好 32 字节，`random_bytes(16)` 或直接传口令都会抛异常；用 `hex2bin()` 转换时先确认长度。
- 同一明文每次加密结果不同（IV 随机），不能拿密文做相等比较；比较请用明文或 `Hash::equals()`。
- `Cipher::decrypt()` 用严格模式的 `base64_decode()`，密文放进 URL、Header 或 JSON 字段前要自行编码，替换过字符的密文会直接判为格式不合法。
- 密文里 IV 与标签在前、正文在后，长度下限是 28 字节；自行截断或拆分存储时保持这个顺序，否则只能得到「密文校验失败」。
- 「格式不合法」（`InvalidArgumentException`）与「校验失败」（`RuntimeException`）是两类错误：前者是数据被截断或编码方式不对，后者才是密钥错误或密文被篡改，接入层不要合并成一个 500。
- `Hash::md5()` 的 `Short16` 取的是中间 16 位而不是前 16 位，与旧代码里 `substr($hash, 0, 16)` 的结果不同，迁移时不能按长度直接替换。
- 口令一律走 `Hash::password()` / `Hash::verify()`：散列自带随机盐，每次生成结果都不同，不能用 `equals()` 比对两个散列值。
- `Hash::password()` 的输出长度随 `PASSWORD_DEFAULT` 变化（bcrypt 为 60 字符，Argon2 更长），入库字段留足 255 字符，按字符串存储而不要定长截断。
- `Hash::verify()` 会按散列自带的算法前缀校验，PHP 升级默认算法后旧散列仍能通过；换算法的做法是重新散列，不是改 `verify()` 的调用方式。
- `Hash::equals()` 是常量时间比较，适用于签名、令牌与摘要；不要用 `===` 比较这类值。
- `Rsa::generate()` 的下限是 2048 位，传更小的值直接抛异常；返回的 `private_key` 与 `public_key` 都是 PEM 字符串，私钥不要落日志或写进配置仓库。
- `Rsa::generate()` 生成 2048 位密钥有明显耗时，不要放在请求链路里；密钥对生成一次后固化到配置或密钥库。
- RSA 加解密使用 PKCS#1 v1.5 填充（`OPENSSL_PKCS1_PADDING`），与其他系统的 OAEP 密文不互通，对接前先确认填充方式。
- RSA 是「分块拼接后整体 base64」：块长为「密钥字节数 - 11」，解密按「密钥字节数」切，自行实现对接时不要按行或按分隔符切分。
- `privateEncrypt()` / `publicDecrypt()` 返回**原始字节**，不做 base64；跨系统传输前自行编码，长度按「密钥字节数」整块对齐。
- 私钥加密只用于「证明来源」，不提供保密性：公钥可以解密，任何人都能伪造同样的密文；需要保密时用 `encrypt()` 公钥加密。
- `NoPadding` 的分块至少保留 1 字节用于左侧补齐，补齐后整块数值小于模数；按密钥长度整块输入会因 `m >= n` 随机失败。
- `NoPadding` 解密用 `ltrim($plain, $pad)` 剥离补齐字符，若明文本身以补齐字符开头会被一并剥掉；对接时约定不会出现在首部的补齐字符。
- `Rsa::encrypt('')` 与 `Rsa::decrypt(base64_encode(''))` 都返回空串，这是正常返回而非错误，调用方别把它当成解密成功以外的信号。
- `Rsa::verify()` 对 base64 非法的签名返回 `false`，但公钥不合法会抛 `InvalidArgumentException`，两种「验不过」的处理方式不同。
- `Token::make()` 的 `$bytes` 是随机字节数，转成十六进制后 `id` 长度为两倍；不要为了缩短令牌把它调到 8 以下。
- `Token::verify()` 的 `$consume` 在版本、签名、载荷、过期时间全部通过之后才被调用，签名不符或已过期时不会触发，回调不会被「白白消耗」。
- 一次性语义由 `$consume` 的存储侧实现（例如把 `id` 写入已用集合），`verify()` 本身不记录也不删除任何状态；回调必须严格返回 `true`，返回 `1`、`'ok'` 之类的值会被判为已使用。
- 过期判断是 `expires < time()`，`expires` 恰好等于当前秒仍算有效，存在不到 1 秒的边界窗口。
- `Token::verify()` 任何失败都返回 `null`，不区分原因；需要区分「过期」「签名错」「已用」时，请在回调内部记录，不要绕过 `verify()` 自行解析载荷。
- `DesCipher` 没有内置口令，加密与解密都必须显式传入；新数据不要用它写回，它的口令只取前 8 字节并补零，口令变长不会提升强度，只是让同一份旧数据仍能解开。
- `DesToken::decode()` 对格式非法与解密失败一律返回 `null`，不区分原因；需要区分时先自行检查长度与十六进制字符集。
- 旧令牌的补齐字符是随机串，`decode()` 只按逗号首尾剥离，不校验补齐内容；同一份明文每次 `encode()` 结果都不同。
- 明文、口令、密钥参数都标了 `#[\SensitiveParameter]`，能避免异常栈泄漏，但不等于可以关闭日志脱敏；记录异常时仍要过滤上下文。
