# 配置

配置层把「一个文件一组配置」「多目录按序叠加」「点号路径取值」固定下来，同时提供只解析文本、不写入进程环境的环境变量集合与按目录加载的语言词条。

## 定位

这一层由四部分组成：`Fxri\Config\Config` 是只读容器，`Fxri\Config\ConfigLoader` 负责从目录读取并叠加，`Fxri\Config\Environment` 把 `.env` 文本解析成键值对，`Fxri\Translation\ArrayTranslator` 与 `Fxri\Translation\Loader\PhpFileLoader` 负责语言词条。

`Config` 是内核里唯一必传的服务（见[内核与装配](./kernel)）：`KernelBuilder::build()` 会调用 `ConfigLoader` 把磁盘上的配置读成 `Config`，业务代码再用 `$kernel->config()->get()` 取值。`Config` 也可以脱离内核直接构造，测试里给一份内存数组即可。

`ConfigLoader` 只认「目录 + `*.php` 文件」这一种组织方式，不递归子目录，不支持其他格式。文件名即顶层键，文件返回的数组整体挂在该键下，所以 `config/app.php` 的内容用 `app.name` 读取。

`Environment` 与 `Config` 没有耦合：它不读配置文件，也不把结果写进 `$_ENV`、`$_SERVER` 或 `putenv()`。需要把 `.env` 的值并入配置时，由调用方自行取值再拼装数组。

翻译词条按「语言 / 域 / 词条」三级组织，`ArrayTranslator` 的缺省域是 `messages`；`PhpFileLoader` 只负责把一个目录下的 PHP 文件读成「词条 => 译文」的平铺数组。2.1.0 起内置语言包、嵌套词条与请求检测统一由[语言](./language)层的 `Language` 承担，`ArrayTranslator` 保留为轻量翻译器与内核兜底实现。

## 最小示例

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Config\Config;
use Fxri\Config\ConfigLoader;
use Fxri\Config\Environment;
use Fxri\Translation\ArrayTranslator;
use Fxri\Translation\Loader\PhpFileLoader;

// 键名本身含点时，路径段里用 /_ 表达该点
$config = Config::make([
    'app' => ['name' => '示例应用', 'debug' => true, 'a.b' => '含点的键'],
]);

echo $config->get('app.name');                // 示例应用
echo $config->get('app.a/_b');                // 含点的键
echo $config->get('app.timezone', 'UTC');     // UTC
var_dump($config->has('app.debug'));          // bool(true)

// 目录不存在时无文件可读，等价于空配置，不报错
$loaded = ConfigLoader::make(__DIR__ . '/config')
    ->withDirectory(__DIR__ . '/config.local')
    ->load();

var_dump($loaded->all());                     // array(0) {}

// 只解析文本，不写入进程环境；export 前缀与成对引号会被处理
$env = Environment::fromString("APP_ENV=dev\nexport APP_DEBUG=\"true\"\n# 注释\n");

echo $env->get('APP_ENV');                    // dev
var_dump($env->has('APP_DEBUG'));             // bool(true)

// 语言包目录不存在时词条为空，未命中原样返回词条
$translator = ArrayTranslator::make(
    ['zh-cn' => ['messages' => PhpFileLoader::loadDirectory(__DIR__ . '/language/zh-cn')]],
    'zh-cn',
);

echo $translator->get('欢迎');                // 欢迎
```

## 参数与返回值

### Config

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(array $data = []): self` | 用现成数组创建只读容器 | 配置实例 |
| `all(): array` | 返回全部配置数据 | `array<array-key, mixed>` |
| `get(string $name, mixed $default = null): mixed` | 按点号路径取值，路径段中的 `/_` 还原为点，任一层不是数组或键不存在时返回默认值 | 配置值或默认值 |
| `has(string $name): bool` | 按同样的路径规则判断键是否存在，值为 `null` 也算存在 | 是否存在 |

### ConfigLoader

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string ...$directories): self` | 按传入顺序登记配置目录 | 加载器 |
| `withDirectory(string $directory): self` | 追加一个目录，返回新实例 | 新加载器 |
| `load(): Config` | 逐个目录、逐个文件读取并叠加，生成配置 | 配置实例 |

`load()` 的规则：

- 每个目录取 `*.php` 文件（不递归），用文件名（去掉扩展名）作为顶层键。
- 同一目录内文件按完整路径字节序 `sort()` 排列；不同文件名互不冲突，排序只影响读取顺序。
- 文件 `require` 后返回值不是数组时按空配置处理，不中断整体加载。
- 多目录按登记顺序叠加，后者覆盖前者；同名分组的内层数组递归合并，标量值以覆盖层为准。

### Environment

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromFile(string $file): self` | 读取文件并解析，文件不存在或读取失败返回空集合，不抛异常 | 环境变量集合 |
| `fromString(string $contents): self` | 解析文本内容 | 环境变量集合 |
| `make(array $data = []): self` | 用现成键值对创建 | 环境变量集合 |
| `get(string $name, mixed $default = null): mixed` | 取单个键，缺失时返回默认值 | 值或默认值 |
| `has(string $name): bool` | 判断键是否存在，以键存在为准 | 是否存在 |
| `all(): array` | 返回全部键值对 | `array<string, string>` |
| `merge(self $other): self` | 合并另一份集合，同名键以后者为准，返回新实例 | 新集合 |

`.env` 文本的解析边界：

- 逐行处理，先去两侧空白；空行与以 `#` 开头的整行注释跳过。
- 行首 `export ` 会被去掉，`export APP_ENV=dev` 与 `APP_ENV=dev` 等价。
- 以第一个 `=` 切分，`=` 左侧为键（去掉空白、不可为空），右侧为值（去掉空白）。
- 值两侧成对的单引号或双引号会被去掉，只去引号本身，不做转义还原。
- 不处理行内注释：`APP_ENV=dev # 本地` 的值就是 `dev # 本地`。
- 没有 `=` 的行被忽略；重复键以最后一行为准。
- 解析结果只留在集合里，不写入进程环境。

### ArrayTranslator

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(array $messages = [], string $locale = 'zh-cn'): self` | 用「语言 / 域 / 词条」三级数组创建 | 翻译器 |
| `fromDirectory(string $directory, string $locale = 'zh-cn'): self` | 用目录内的语言包填充指定语言的缺省域 | 翻译器 |
| `get(string $id, ?string $domain = null, ?string $locale = null): string` | 取译文，`$domain` 缺省为 `messages`、`$locale` 缺省为当前语言，未命中返回词条本身 | 译文 |
| `trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string` | 取译文后替换占位符 | 译文 |
| `has(string $id, ?string $domain = null, ?string $locale = null): bool` | 判断词条是否存在 | 是否存在 |
| `getLocale(): string` | 返回当前语言标识 | 语言标识 |
| `withLocale(string $locale): self` | 切换语言，返回新实例 | 新翻译器 |

占位符以 `%名称%` 表示，参数键写 `name` 或 `%name%` 等价（构造时统一补 `%` 并去掉原有 `%`）；参数值经 `Type::toString()` 转成字符串，布尔转 `1` / `0`，数组与不可转字符串的对象转空串。

其它两点：`DOMAIN` 常量固定为 `messages`；`has()` 用 `isset` 判定，`get()` 用 `??` 兜底，语言或域不存在时两者都按「未命中」处理。

### PhpFileLoader

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `load(string $file): array` | 加载单个语言包，文件不存在或返回值不是数组时返回空数组 | `array<string, string>` |
| `loadDirectory(string $directory): array` | 加载目录下全部 `*.php`，按完整路径排序后合并，同名词条以后者为准 | `array<string, string>` |

归一规则：只保留值为字符串的条目，非字符串值（数字、数组、对象、布尔）直接丢弃；键统一转成字符串。语言包不递归子目录，一个文件就是一组平铺词条。

## 注意事项

- `Config::get()` 的路径分隔符是 `.`，键名里真实的点必须写成 `/_`，否则会被当成层级。例如配置项 `a.b` 要读作 `app.a/_b`。
- `Config::get()` 一旦中途遇到非数组值就返回默认值，不会做更深的探底；`has()` 与 `get()` 的路径语义完全一致，但 `has()` 对 `null` 值返回 `true`。
- `ConfigLoader` 的叠加不是整体替换：同名分组的内层数组会递归合并，所以想删掉基底里的某个子键只能显式覆盖成新值，不能靠「给一个更小的数组」实现。列表型配置同样按索引逐项覆盖。
- 配置文件名区分大小写（取决于文件系统），`App.php` 与 `app.php` 是两个顶级键。
- `load()` 用 `require` 执行文件，配置文件里不要写副作用代码；它的返回值必须 `return` 一个数组，否则该文件被静默忽略。
- `Environment::get()` 用 `??` 取键，`make()` 里传 `null` 值时 `get()` 会返回默认值而 `has()` 仍为 `true`；`fromString()` 产出的值恒为字符串。
- `Environment::fromFile()` 对不存在的文件不抛异常，容易把「路径写错」误判成「文件为空」，路径来自配置时建议先自行 `is_file()` 校验。
- `ArrayTranslator` 未命中的词条原样返回，因此不会出现空串；要区分「已翻译」与「未翻译」用 `has()`。
- `ArrayTranslator::withLocale()` 只换当前语言，不会为新语言补词条；用 `make()` 传入的多语言数组才能跨语言取词。
- `PhpFileLoader::loadDirectory()` 用 `array_merge()` 合并，同名词条后者覆盖前者；需要按语言目录分层时，每个语言一个目录分别加载。
