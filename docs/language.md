# 语言

语言层把「内置语言包」「取词与占位符」「请求语言检测」拆成四块，默认随内核装配，也可按需关闭或替换。

## 定位

这一层由四部分组成：

- `Fxri\Translation\Catalog`：以「语言 / 域 / 词条」三级存放词条，保留 `lang_list` 这类嵌套数组，写入返回新实例。
- `Fxri\Translation\Translator`：实现 Symfony `TranslatorInterface`，只处理字符串词条与占位符。
- `Fxri\Translation\LocaleResolver`：从查询参数、Cookie、`Accept-Language` 推断语言。
- `Fxri\Translation\Language`：组合以上三者，承担 1.6.1 `Lang` 的取词、嵌套词条、检测与切换能力。

内置语言包位于 `resources/language/{zh-cn,en-us}/{base,env,phrase}.php`，`KernelBuilder::make()->build()` 默认加载；每个语言目录内的文件按文件名顺序合并到 `messages` 域，后者覆盖前者。项目语言目录通过 `withLanguageDir()` 追加，在同域内覆盖内置词条。

## 最小示例

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\KernelBuilder;

// 默认装配内置中英文语言包
$kernel = KernelBuilder::make()->build();
$language = $kernel->language();

echo $kernel->trans('welcome to');            // 欢迎来到
echo $language->get('delete');                 // 删除
echo $language->withLocale('en-us')->get('delete'); // delete

// 嵌套词条原样返回
print_r($language->get('lang_list'));
// ['zh-cn' => '中文（简体）', 'en-us' => '英语（美国）']

// 词条列表拼接：中文直接相连，其它语言以空格分隔
echo $language->phrase(['fxyin', 'delete']);  // 风音删除
echo $language->phrase(['fxyin', 'delete'], 'en-us'); // fxyin delete

// 项目语言目录覆盖内置词条
$kernel = KernelBuilder::make()
    ->withLanguageDir(__DIR__ . '/language/zh-cn', 'zh-cn')
    ->build();

// 关闭内置语言，翻译回落为数组翻译器
$bare = KernelBuilder::make()->withoutBuiltinLanguage()->build();
var_dump($bare->language()); // NULL
```

## 参数与返回值

### Catalog

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(array $messages = []): self` | 用「语言 / 域 / 词条」三级数组创建 | 语言目录 |
| `fromDirectory(string $directory, string $locale = 'zh-cn', string $domain = 'messages'): self` | 从单个语言目录创建 | 语言目录 |
| `fromRoot(string $root): self` | 从语言包根目录创建，子目录名即语言 | 语言目录 |
| `load(string\|array $files, string $locale = 'zh-cn', ?string $domain = null): self` | 加载一个或多个语言包文件，域缺省为文件名 | 新实例 |
| `loadDirectory(string $directory, string $locale = 'zh-cn', string $domain = 'messages'): self` | 加载整个目录，文件按文件名排序合并 | 新实例 |
| `set(string\|array $name, mixed $value = null, string $locale = 'zh-cn', string $domain = 'messages'): self` | 单个或批量写入词条，数组递归合并 | 新实例 |
| `get(string $name, string $locale = 'zh-cn', ?string $domain = null, mixed $default = null): mixed` | 读取词条，值可为嵌套数组 | 词条或默认值 |
| `has(string $name, string $locale = 'zh-cn', ?string $domain = null): bool` | 判断词条是否存在，`null` 视为不存在 | 是否存在 |
| `all(string $locale = 'zh-cn'): array` | 合并该语言全部域的词条 | 词条数组 |
| `locales(): array` | 返回已登记的语言 | `list<string>` |
| `domains(string $locale = 'zh-cn'): array` | 返回该语言的域 | `list<string>` |

### Translator

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(Catalog $catalog, string $locale = 'zh-cn', string $domain = 'messages'): self` | 创建翻译器 | 翻译器 |
| `get(string $id, ?string $domain = null, ?string $locale = null): string` | 取字符串译文，嵌套数组按未命中处理 | 译文或词条本身 |
| `trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string` | 取译文并替换占位符 | 译文 |
| `format(string $message, array $parameters = []): string` | 静态方法，单独替换占位符 | 替换结果 |
| `has(string $id, ?string $domain = null, ?string $locale = null): bool` | 判断字符串词条是否存在 | 是否存在 |
| `getLocale(): string` | 返回当前语言 | 语言标识 |
| `domain(): string` | 返回默认域 | 域名 |
| `withLocale(string $locale): self` | 切换语言，返回新实例 | 新翻译器 |
| `withDomain(string $domain): self` | 切换默认域，返回新实例 | 新翻译器 |

占位符规则：关联参数同时替换 `%name%` 与 `{:name}`，参数键带不带百分号等价；数组第一个键为 `0` 时按 `sprintf` 处理，例如译文 `共 %d 条` 配参数 `[3]` 得到 `共 3 条`。

### LocaleResolver

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $detectVar = 'lang', string $cookieName = 'lang', int $cookieExpire = 3600, array $allowList = [], string $locale = 'zh-cn'): self` | 创建检测器 | 检测器 |
| `resolve(array $query = [], ?CookieJar $cookies = null, ?ServerContext $server = null, bool $persist = true): string` | 推断语言，缺省读全局变量 | 语言标识 |
| `withDetectVar(string $var): self` | 指定查询参数名 | 新实例 |
| `withCookieName(string $name): self` | 指定 Cookie 名 | 新实例 |
| `withCookieExpire(int $expire): self` | 指定 Cookie 有效期（秒） | 新实例 |
| `withAllowList(array $list): self` | 指定允许语言列表，空数组表示不限制 | 新实例 |
| `withLocale(string $locale): self` | 指定兜底语言 | 新实例 |
| `detectVar() / cookieName() / cookieExpire() / allowList() / locale()` | 读取当前配置 | 对应值 |

检测顺序与 1.6.1 一致：查询参数 → Cookie → `Accept-Language` 首段。查询参数与浏览器语言会回写 Cookie；命令行环境或 `$persist = false` 时不回写。检测结果不在允许列表内时使用兜底语言，`zh-hans-cn` 归一为 `zh-cn`。

### Language

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(Catalog $catalog, ?LocaleResolver $resolver = null, string $locale = 'zh-cn'): self` | 创建语言服务，检测器缺省按目录语言生成允许列表 | 语言服务 |
| `get(?string $name = null, array $vars = [], ?string $locale = null): mixed` | 取词条；`$name` 为 `null` 时返回全部词条；字符串替换占位符，数组原样返回 | 词条内容 |
| `has(string $name, ?string $locale = null): bool` | 判断词条是否存在 | 是否存在 |
| `phrase(string\|array\|null $name, ?string $locale = null): mixed` | 对应 1.6.1 的 `langList` + `langParse`：列表逐项取词后拼接 | 拼接结果 |
| `detect(?array $query = null, ?CookieJar $cookies = null, ?ServerContext $server = null): self` | 检测并切换语言，返回新实例 | 新语言服务 |
| `withLocale(string $locale): self` | 切换语言，返回新实例 | 新语言服务 |
| `withResolver(LocaleResolver $resolver): self` | 替换检测器 | 新实例 |
| `load(string\|array $file, ?string $locale = null, string $domain = 'messages'): self` | 加载语言包，返回新实例 | 新语言服务 |
| `set(string\|array $name, mixed $value = null, ?string $locale = null, string $domain = 'messages'): self` | 写入词条，返回新实例 | 新语言服务 |
| `trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string` | 翻译字符串词条 | 译文 |
| `translator(?string $locale = null, ?string $domain = null): Translator` | 生成翻译器 | 翻译器 |
| `catalog(): Catalog` | 取语言目录 | 语言目录 |
| `resolver(): LocaleResolver` | 取检测器 | 检测器 |
| `locale(): string` | 取当前语言 | 语言标识 |
| `all(?string $locale = null): array` | 取该语言全部词条 | 词条数组 |

## 注意事项

- 内核默认装配内置语言包是 2.1.0 的行为变化。需要 2.0 的空语言环境时用 `withoutBuiltinLanguage()` 或 `bare()`。
- `withLanguageDir()` 是追加语义，后追加目录覆盖先追加目录与内置词条；目录不存在时抛 `InvalidArgumentException`。
- `withLanguage()` 会完全接管语言装配，此时内置语言与项目语言目录都不再生效。
- `Language` 的 `detect()`、`withLocale()`、`set()`、`load()` 都返回新实例，原实例不变；内核持有的语言服务也不会被就地修改。
- `Language::get()` 未命中时原样返回名称；要区分「已翻译」与「未翻译」用 `has()`。
- `phrase()` 的中文拼接不带分隔符，其它语言带空格；空值词条会被跳过。
- 自动检测只在显式调用 `detect()` 时发生，内核装配阶段不读取请求变量，命令行与常驻进程不会因全局状态改变默认语言。
- `Translator` 只返回字符串；`lang_list` 这类嵌套词条请走 `Language::get()`。
- `ArrayTranslator` 在 2.x 继续可用，未装配语言服务时作为内核的兜底翻译器。
