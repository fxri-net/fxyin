# 文本

把字符串、编解码、IP、时间与标识串这些反复出现的转换收成无状态静态方法，统一按 UTF-8 处理，失败时抛异常而不是返回 `false`。

## 定位

`Fxri\Text` 是横向工具层，不依赖内核，也不持有任何配置或连接。框架内 `Fxri\Data\Field::date()` 直接用 `DateGranularity` 决定时间格式；其他需要 IP 入库、时间展示或标识串生成的模块按需调用，不构成编译期依赖。

这一层的取舍是「只封装标准行为」：`Encode` 不发明自有格式，`Str` 不提供正则语法糖，`Time` 统一用 Unix 时间戳并以 `date_default_timezone_get()` 的时区解释。需要更复杂的处理时，调用方直接使用 PHP 原生函数即可，不必绕经这里。

与 `Fxri\Support\Type` 的分工：`Type` 只做类型判定与宽松转换且从不抛异常；`Text` 做业务层面的编解码与格式化，输入不合法时明确失败，避免 `false` 与空串混在后续流程里。

## 最小示例

```php
<?php

declare(strict_types=1);

use Fxri\Text\DateGranularity;
use Fxri\Text\Encode;
use Fxri\Text\Ip;
use Fxri\Text\Str;
use Fxri\Text\Time;
use Fxri\Text\Uuid;
use Fxri\Text\UuidMode;
use Fxri\Text\UuidStyle;

// 固定时区，下方时间输出才可复现
date_default_timezone_set('UTC');

// a,b：去空白、去空段、默认去重
echo implode(',', Str::explode(',', ' a, b ,a')) . PHP_EOL;

// a-b：空串与 null 被跳过
echo Str::implode('-', ['a', ['b', ''], null]) . PHP_EOL;

// yes：字符数按 UTF-8 计，上下界都是闭区间
echo (Str::isLength('风音', 2, 4) ? 'yes' : 'no') . PHP_EOL;

// a,b / a,b,c：已存在时不重复追加
echo Str::splice('a,b', 'b', ',') . ' / ' . Str::splice('a,b', 'c', ',') . PHP_EOL;

// Znh5aW4= / 667879696e
echo Encode::base64Encode('fxyin') . ' / ' . Encode::hexEncode('fxyin') . PHP_EOL;

// q=%E9%A3%8E%20%E9%9F%B3&page=2：空格编码为 %20
echo Encode::queryEncode(['q' => '风 音', 'page' => 2]) . PHP_EOL;

// 2130706433 / 127.0.0.1
echo Ip::toLong('127.0.0.1') . ' / ' . Ip::fromLong(2130706433) . PHP_EOL;

// 7f000001：IPv4 为 4 字节，IPv6 为 16 字节
echo bin2hex(Ip::toBinary('127.0.0.1')) . PHP_EOL;

// 2023-11-14 22:13:20 / 2023-11-14 / 80000
echo Time::format(1700000000) . ' / '
    . Time::format(1700000000, DateGranularity::Date) . ' / '
    . Time::truncate(1700000000, DateGranularity::Time) . PHP_EOL;

// 1700000000：时间串解析回时间戳
echo Time::parse('2023-11-14 22:13:20') . PHP_EOL;

// 36 / 32 / 16：形态决定长度，样式再二次加工
echo strlen(Uuid::make()) . ' / '
    . strlen(Uuid::make(UuidMode::Upper32)) . ' / '
    . strlen(Uuid::make(UuidMode::Standard, 'seed', UuidStyle::Lower16)) . PHP_EOL;
```

## 参数与返回值

### Str

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `explode(string $separator, string $value, bool $unique = true)` | 按分隔符拆分，逐段 `trim` 后丢弃空段；`$unique` 默认 `true`，去重并重排索引；分隔符为空串抛 `InvalidArgumentException` | `array` |
| `implode(string $glue, array $values, bool $unique = true)` | 递归拉平嵌套数组，跳过无法表达为字符串的项与空串，可按 `$unique` 去重 | `string` |
| `isLength(string $value, ?int $start = null, ?int $end = null)` | 字符数是否落在闭区间内；`null` 表示该侧不限制 | `bool` |
| `length(string $value)` | 按 UTF-8 计算字符数 | `int` |
| `splice(string $value, string $append, string $glue = '')` | 把 `$append` 追加到 `$value`，已存在时不重复追加；`$append` 为空串或整串已相等时原样返回 | `string` |

### Encode

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `base64Encode(string $value)` | 标准 Base64 编码 | `string` |
| `base64Decode(string $value, bool $strict = false)` | Base64 解码；`$strict` 为 `true` 时拒绝非法字符，解码失败抛 `InvalidArgumentException` | `string` |
| `hexEncode(string $value, bool $upper = false)` | 转十六进制串；`$upper` 为 `true` 时输出大写 | `string` |
| `hexDecode(string $value)` | 十六进制串还原为原始字节，非法或奇数长度抛 `InvalidArgumentException` | `string` |
| `htmlEncode(string $value, bool $doubleEncode = true)` | 按 `ENT_QUOTES \| ENT_SUBSTITUTE`、UTF-8 转义实体；`$doubleEncode` 为 `true` 时已有实体会被再次编码 | `string` |
| `htmlDecode(string $value)` | 按 `ENT_QUOTES \| ENT_HTML5`、UTF-8 还原实体 | `string` |
| `queryEncode(array $data)` | 生成查询串，固定 `PHP_QUERY_RFC3986`（空格为 `%20`） | `string` |
| `queryDecode(string $query)` | 解析查询串，基于 `parse_str` 语义 | `array` |
| `urlEncode(string $value)` | 按 `rawurlencode` 编码，`/` 也会被编码为 `%2F` | `string` |
| `urlDecode(string $value)` | 按 `rawurldecode` 解码，不把 `+` 当空格 | `string` |
| `convert(string $value, string $type)` | `hexstr` 字符串转十六进制、`strhex` 十六进制转字符串，其它类型原样返回 | `string` |
| `encodeAll(array $data)` | 嵌套数组编码为 RFC 3986 查询串 | `string` |
| `decodeAll(string $query)` | 查询串还原为嵌套数组 | `array` |
| `letter(string $value, string $inCharset = 'UTF-8', string $outCharset = 'GB2312')` | 单个字符取首字母：英文转大写，中文按 GBK 区间映射 A-Z | `string` |
| `letters(string $value, string $inCharset = 'UTF-8', string $outCharset = 'GB2312')` | 逐字符取首字母并拼接 | `string` |

### Ip

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `toLong(string $ip)` | 可读地址转整数；仅接受 IPv4，其他输入抛 `InvalidArgumentException` | `int` |
| `fromLong(int $long)` | 整数转可读地址；按 `pack('N')` 还原 32 位网络字节序，语义等同 IPv4 | `string` |
| `toBinary(string $ip)` | 可读地址转二进制；IPv4 得 4 字节、IPv6 得 16 字节，非法地址抛 `InvalidArgumentException` | `string` |
| `fromBinary(string $binary)` | 二进制转可读地址；长度不合法抛 `InvalidArgumentException` | `string` |

### Time

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `format(int $timestamp, DateGranularity $granularity = DateGranularity::DateTime)` | 按粒度格式化为日期时间串，时区取 PHP 默认时区 | `string` |
| `parse(int\|string $value)` | 整数原样返回，数字串转 `int`，其余交给 `strtotime`；失败抛 `InvalidArgumentException` | `int` |
| `milli(?int $timestamp = null)` | 取毫秒时间戳；传参时按秒乘 1000，不传时取当前毫秒 | `int` |
| `milliFormat(int $milliseconds)` | 毫秒时间戳转 `Y-m-d H:i:s.mmm` | `string` |
| `milliParse(int\|string $value)` | 13 位毫秒串或 `Y-m-d H:i:s.mmm` 时间串转毫秒时间戳；其它形态抛 `InvalidArgumentException` | `int` |
| `decompose(int $seconds)` | 把秒数按 30 天/月、12 月/年分解为年、月、天、时、分、秒 | `array` |
| `duration(int $seconds, array $labels = [], bool $zero = false, ?TimeUnit $max = null, bool $before = true)` | 拼接「1个月2小时」；标签留空关闭该单位，`$zero` 输出零值，`$max` 指定最高单位，`$before` 为 false 时只保留最高单位 | `string` |
| `truncate(int $timestamp, DateGranularity $granularity = DateGranularity::DateTime)` | 截断到粒度起点：`Date` 归零时刻，`Time` 把日期重置为 1970-01-01，`DateTime` 原样返回 | `int` |
| `change(int\|string $value, ?string $type = null, string $format = 'Y-m-d H:i:s')` | 字符串先解析为时间戳；类型 `1` 按格式截断后返回时间戳，`2` 返回格式化字符串；失败返回 `null` | `int\|string\|null` |
| `range(string\|array $time, array $start = [], array $end = [])` | 解析逗号串或两项数组，缺项用起止配置补齐，按各自类型输出 | `array` |
| `relative(int $timestamp, array $options = [])` | 生成「3小时前」「2天后」等相对描述，`$options['now']` 可指定当前时间戳 | `string` |

### DateGranularity

| 分支 | 含义 |
| --- | --- |
| `Date` | 仅日期，格式串 `Y-m-d` |
| `DateTime` | 日期与时间，格式串 `Y-m-d H:i:s`，默认粒度 |
| `Time` | 仅时间，格式串 `H:i:s` |

枚举自带 `format(): string`，返回枚举的 `value` 本身，免去调用方另存一份格式串。

### TimeUnit

| 分支 | 含义 | 默认标签 |
| --- | --- | --- |
| `Year` | 年 | 年 |
| `Month` | 月 | 个月 |
| `Day` | 天 | 天 |
| `Hour` | 时 | 小时 |
| `Minute` | 分 | 分钟 |
| `Second` | 秒 | 秒 |

`label(): string` 返回默认标签；`Time::duration()` 的 `$labels` 以枚举值为键覆盖标签，空串表示关闭该单位。

### Uuid

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(UuidMode $mode = UuidMode::Standard, ?string $seed = null, ?UuidStyle $style = null)` | 生成标识串；`$seed` 为 `null` 时取 `random_bytes(16)`，否则用 `md5($seed)`；`$style` 非空时在形态之后二次加工 | `string` |
| `format(string $value, ?UuidStyle $style = null)` | 把现有标识串去横线、转大写并重新按 8-4-4-4-12 分段，对应 1.x 对现有 UUID 的格式化 | `string` |

### UuidMode

| 分支 | 含义 |
| --- | --- |
| `Standard` | 标准 36 位带横线串（8-4-4-4-12） |
| `Upper32` | 32 位大写串，无横线 |
| `Md5Upper` | 32 位大写串，语义上表示由种子派生的 MD5 |

### UuidStyle

| 分支 | 含义 |
| --- | --- |
| `Lower16` | 去掉横线后第 9~24 位的中间 16 位小写 |
| `Lower32` | 去掉横线的 32 位小写 |
| `Upper16` | 去掉横线后第 9~24 位的中间 16 位大写 |
| `Upper32` | 去掉横线的 32 位大写 |

样式统一实现为 `apply(string $uuid): string`：先 `str_replace('-', '', $uuid)` 再下标切片或大小写转换，因此带横线串与压缩串会得到一致结果。

## 注意事项

- ⚠️ `Str::explode()` 的分隔符不能为空串；每段 `trim` 后为空即丢弃，所以 `' a , ,b '` 与 `'a,b'` 结果相同，无法保留段内的有效空白。
- ⚠️ `Str::explode()` 的 `$unique` 默认是 `true`，需要保留重复项必须显式传 `false`；去重按字符串比较，`'1'` 与 `'01'` 视为不同项。
- ⚠️ `Str::isLength()` 的上下界都是闭区间，`null` 表示该侧不限制；`$start > $end` 时不会抛异常，而是恒为 `false`。
- ⚠️ `Str::implode()` 会递归展开嵌套数组，`null`、数组与不具备 `Stringable` 的对象被跳过；`false` 会被转成 `'0'` 而不是空串，布尔值混入时要留意。
- ⚠️ `Str::splice()` 的存在性判断依赖 `$glue` 拆分：`$glue` 为空串时只有整串等于 `$append` 才算已存在，`splice('ab', 'b')` 会得到 `'abb'`。
- ⚠️ `Encode::base64Decode()` 解码失败抛异常而不返回 `false`；默认 `$strict = false` 会跳过非法字符，可能解出空串或残缺内容，校验场景应传 `true`。
- ⚠️ `Encode::htmlEncode()` 的 `$doubleEncode` 默认为 `true`，对已含实体的文本会二次编码；保存富文本或重复处理时必须显式传 `false`。
- ⚠️ `Encode::queryEncode()` 固定 RFC 3986，空格是 `%20` 而不是 `+`；`queryDecode()` 基于 `parse_str`，键名中的 `.` 与空格会变成下划线，同名键后值覆盖前值。
- ⚠️ `Ip::toLong()` 只接受 IPv4，IPv6 会抛异常；IPv6 只能走 `toBinary()` / `fromBinary()`，没有整数形态。
- ⚠️ `Ip::fromLong()` 按 32 位网络字节序还原，传入超出 32 位的整数会被截断，不要用它承载 IPv6 或 64 位标识。
- ⚠️ `Ip::toBinary()` 的结果长度取决于地址族（4 或 16 字节），入库时字段长度需按最长 16 字节设计。
- ⚠️ `Time` 的所有转换都按 `date_default_timezone_get()` 解释时区，跨时区项目应在入口处显式设置默认时区，否则同一时间戳会格式化成不同结果。
- ⚠️ `Time::parse()` 见到纯数字串会直接转 `int`，不做位数与时区校验；`1700000000000` 这类毫秒值不会被自动降级为秒。
- ⚠️ `Time::truncate()` 的 `Time` 粒度是把日期重置为 1970-01-01，得到的是「当天时刻对应的秒数」（示例中的 `80000`），不是当天零点。
- ⚠️ `Uuid::make()` 传 `$seed` 后由 `md5($seed)` 派生，结果可预测且固定，只能用于需要复现的测试或幂等场景。
- ⚠️ `Uuid::format()` 只做格式归一，不做合法性校验：非十六进制字符会原样保留，长度不足 32 位时会得到不完整的分段。
- ⚠️ `Time::duration()` 的 `$max` 是「最高单位」而不是「最大数量」：`max: TimeUnit::Day` 时 30 天会输出 `30天`，而不是 `1个月`。
- ⚠️ `Time::milliParse()` 对 13 位纯数字按毫秒时间戳处理，10 位秒级时间戳请先用 `Time::parse()`。
- ⚠️ `UuidMode::Md5Upper` 与 `UuidMode::Upper32` 的输出形式完全一致（32 位大写），区别只在语义；不传种子时 `Md5Upper` 同样取随机源。
- ⚠️ `UuidStyle::Lower16` / `Upper16` 取的是去掉横线后的第 9~24 位，不是开头 16 位；需要首段标识时不要用它。
- `UuidMode` 与 `UuidStyle` 没有 backed value，不能当字符串比较或直接输出，需要转换时用 `Uuid::make()` 的返回值。
