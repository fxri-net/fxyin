# 支撑

类型判定、随机串、数值与容量格式化、调试输出这些零散工具被收成四个静态入口，避免每个项目各写一份判定规则和单位换算。

## 定位

支撑层位于 `Fxri\Support`，是不经过内核的纯静态能力：直接 `use` 类名调用即可，不依赖任何装配项，也不持有状态。它与[内核与装配](./kernel)没有耦合，在内核尚未建立时同样可用。

`Type` 承接 1.x 的 `is_*` 系列全局函数（迁移对照见[2.0 迁移说明](./migration-2.0)），只做无副作用的判断与标量转换，不抛异常、不抛本地化文案；判定不通过就返回 `false` 或 `0` / 空串，由调用方决定怎么报错。

`Random` 统一走密码学安全随机源，替代旧实现里 `rand()`、`mt_rand()`、`uniqid()` 的组合；字符范围由 `RandomCharset` 枚举表达，不再用整型参数选择。需要的是令牌、盐、临时文件名这类不可预测的值，就用它。

`Formatter` 只做展示层转换，不参与计算：`number()` 负责千分位与小位数，`size()` 负责字节到容量单位的换算。它的输出是给人看的字符串，回写到接口里之前先确认调用方是否接受千分位。

`Dump` 是排错工具，`render()` 与 `write()` 分开：前者把结果交给日志或接口，后者直接输出到当前输出流。两者的内容都来自 `var_dump()`，包含类型信息。

## 最小示例

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Support\Dump;
use Fxri\Support\Formatter;
use Fxri\Support\Random;
use Fxri\Support\RandomCharset;
use Fxri\Support\Type;

var_dump(Type::isBlank(''));                     // bool(true)
var_dump(Type::isBlank(0));                      // bool(false)
var_dump(Type::toBool('yes'));                   // bool(true)
var_dump(Type::toBool('off'));                   // bool(false)
var_dump(Type::toInt('12abc'));                  // int(0)
var_dump(Type::isJson('{"a":1}'));               // bool(true)
var_dump(Type::isJson('123'));                   // bool(false)
var_dump(Type::isTime('2026-10-25 08:00:00'));   // bool(true)

// 每次输出不同，位数由第一个参数决定
echo Random::string(8, RandomCharset::Numeric);  // 形如 49270385
echo RandomCharset::Alnum->length();             // 62

echo Formatter::number(1234567.891);             // 1,234,567.89
echo Formatter::size(1572864);                   // 1.50 MB

// 直接输出：CLI 下为纯文本，Web 下为转义后的 pre 片段
Dump::write(['ok' => true]);
// array(1) {
//   ["ok"] => bool(true)
// }
```

## 参数与返回值

### Type

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `isBlank(mixed $value): bool` | 仅 `null` 与 `''` 算空，`0`、`false`、空数组都不算 | 是否为空 |
| `isCallable(mixed $value): bool` | 直接委托 `is_callable()`，闭包、函数名、可调用数组都算 | 是否可调用 |
| `isEmail(string $value): bool` | `FILTER_VALIDATE_EMAIL` 判定 | 是否合法 |
| `isIp(string $value): bool` | `FILTER_VALIDATE_IP` 判定，IPv4 与 IPv6 都接受 | 是否合法 |
| `isIpv4(string $value): bool` | `FILTER_VALIDATE_IP` 加 `FILTER_FLAG_IPV4` | 是否合法 |
| `isIpv6(string $value): bool` | `FILTER_VALIDATE_IP` 加 `FILTER_FLAG_IPV6` | 是否合法 |
| `isJson(string $value): bool` | 去空白后为空即否；能解码且顶层是数组或对象才为是 | 是否合法 |
| `isMobile(string $value): bool` | 匹配 `^1\d{10}$`，即 1 开头的 11 位数字 | 是否合法 |
| `isTime(string $value, string $format = 'Y-m-d H:i:s'): bool` | 按格式解析后再格式化回原格式，必须与原串完全相等 | 是否合法 |
| `isUrl(string $value): bool` | `FILTER_VALIDATE_URL` 判定 | 是否合法 |
| `isZipcode(string $value): bool` | 匹配 `^[1-9]\d{5}$`，即首位非零的 6 位数字 | 是否合法 |
| `toBool(mixed $value): bool` | 字符串先转小写去空白，再按白名单判定，其余走 `(bool)` | 转换结果 |
| `toFloat(mixed $value): float` | `is_numeric()` 通过才转换，否则 `0.0` | 转换结果 |
| `toInt(mixed $value): int` | `is_numeric()` 通过才转换，否则 `0`；小数截断 | 转换结果 |
| `toString(mixed $value): string` | 字符串原样、布尔转 `1` / `0`、数值强转、`Stringable` 转字符串，其余为空串 | 转换结果 |

`toBool()` 的白名单：真值为 `1`、`true`、`yes`、`on`；假值为 `''`、`0`、`false`、`no`、`off`。不在两份名单里的字符串（如 `2`、`00`）走 PHP 原生布尔转换，非空非 `"0"` 即为 `true`。

### Random

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `bytes(int $length): string` | 生成长度为 `$length` 字节的随机二进制串，`$length < 1` 抛 `InvalidArgumentException` | 二进制串 |
| `integer(int $minimum, int $maximum): int` | 生成闭区间内的随机整数，委托 `random_int()` | 随机整数 |
| `string(int $length, RandomCharset $charset = RandomCharset::Alnum): string` | 逐位从字符集取字符，`$length < 1` 抛 `InvalidArgumentException` | 随机串 |

### RandomCharset

| 分支 | 含义 |
| --- | --- |
| `Numeric` | 纯数字，值为 `0123456789` |
| `Lower` | 小写字母，值为 `abcdefghijklmnopqrstuvwxyz` |
| `Upper` | 大写字母，值为 `ABCDEFGHIJKLMNOPQRSTUVWXYZ` |
| `Alnum` | 数字与大小写字母，值为前三者按数字、小写、大写顺序拼接 |

`characters(): string` 返回字符集本身（可直接按位取字符），`length(): int` 返回字符个数，即 `Numeric` 为 10、`Lower` 与 `Upper` 为 26、`Alnum` 为 62。

### Formatter

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `number(int|float $value, int $precision = 2): string` | `number_format()` 加千分位并按 `$precision` 四舍五入 | 格式化字符串 |
| `size(int|float $bytes, int $precision = 2): string` | 负数按 0；每满 1024 进一级，单位上限为 `PB`；数值部分复用 `number()` | 形如 `1.50 MB` |

`size()` 的单位序列为 `B`、`KB`、`MB`、`GB`、`TB`、`PB`，按 1024 进制递进；超过 `PB` 也继续以 `PB` 表示，不做溢出换算。

### Dump

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `render(mixed ...$values): string` | 用 `var_dump()` 抓取每个值的结构，多个值按 `PHP_EOL` 拼接；把 `] =>` 后的换行压成一行；CLI 下返回纯文本，非 CLI 下返回经 `htmlspecialchars()` 转义的 `<pre>` 片段 | 调试文本 |
| `write(mixed ...$values): void` | 输出 `render()` 的结果并在末尾追加 `PHP_EOL` | 无 |

## 注意事项

- `Type` 的所有方法都是静态且无副作用，但 `isTime()` 是「往返相等」判定：`2026-1-1` 按默认格式解析后格式化回 `2026-01-01`，与原串不等，判定为 `false`。月份、日期、时分秒都必须补零。
- `isJson()` 只认顶层数组与对象。合法但为标量的 JSON（`123`、`"abc"`、`true`、`null`）一律返回 `false`，用它是判「是不是 JSON 结构」而非「是不是合法 JSON 文本」。
- `isMobile()` 不含国家码校验，`+8613800138000` 与带空格、短横线的写法都不通过；`isZipcode()` 不接受前导 0。
- `toBool()` 对未列出的字符串走 PHP 原生布尔转换，`'00'`、`'2'` 都会得到 `true`（只有 `'0'` 为假）；解析配置开关时先把取值收敛到白名单再传入。
- `toInt('12abc')` 是 `0` 而非 `12`，`is_numeric()` 不通过就不转换；`toInt(12.9)` 为 `12`，小数被截断而不是四舍五入。
- `toString()` 对数组、非 `Stringable` 对象返回空串，因此它不会触发「数组转字符串」告警，但也会把这类值悄悄吞成空串；调试结构请用 `Dump`。
- `Random::bytes()` 产出的是二进制串，直接入库或放进 URL 前需自行 `bin2hex()` 或 base64 编码；仓储层若按 UTF-8 校验，二进制会直接失败。
- `Random::string()` 的长度是「字符个数」，因四个字符集都是 ASCII，才与字节数一致；扩展字符集时两者会分叉。
- `Random::integer()` 的两端都包含，且未做参数校验，`$minimum > $maximum` 时由 `random_int()` 自行报错。
- `Formatter::number()` 与 `size()` 的整数部分都带千分位，`1023` 字节会输出 `1,023.00 B`；不要把结果直接用于数值比较或再解析。
- `Formatter::size()` 把负数当 0，上传大小、剩余容量这类可能为负的输入不会被报错，需要拦截时在调用前判断。
- `Dump` 依赖 `var_dump()`，输出含类型与结构，且 `render()` 在 Web 下做转义、CLI 下不做；它仅用于调试，不要用于接口响应或日志正文。
- `Dump::write()` 会直接 `echo`，在已开始输出缓冲或已设置响应头的流程里会写进响应体，生产代码不要保留调用。
