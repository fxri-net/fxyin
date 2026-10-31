# 配置加载与覆盖

## 组织方式

配置层只认「目录 + `*.php` 文件」这一种形态，一个文件就是一组配置，文件名去掉扩展名后作为顶层键：

```text
项目根/
└── config/
    ├── app.php          → app.*
    └── facade/
        └── model.php    → facade.model.*
```

`config/app.php` 直接 `return` 一个数组。

## 加载与叠加

`KernelBuilder::build()` 内部用 `ConfigLoader` 读盘：默认先加载随包的 `resources/config`（`app`、`branch`、`env`），再按 `withConfigDir()` 的登记顺序追加项目配置。

| 规则 | 说明 |
| --- | --- |
| 目录递归 | 每个目录递归取 `*.php`；同一层先文件（按文件名排序），再子目录（按目录名排序），后加载覆盖先加载 |
| 多目录叠加 | 按登记顺序叠加，后者覆盖前者；同名分组的内层数组递归合并，标量以覆盖层为准 |
| 列表配置 | 同样按索引逐项覆盖，不是整体替换 |
| 返回值 | 文件 `require` 后必须返回数组，否则抛 `RuntimeException` 并给出文件路径 |
| 目录不存在 | `withConfigDir()` 对不存在的目录抛 `InvalidArgumentException`；`ConfigLoader` 直接构造时无文件可读，等价空配置 |

想删掉基底里的某个子键，只能显式覆盖成新值，给一个更小的数组不会生效。

## 取值

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fxri\Config\Config;

$config = Config::make([
    'app' => ['name' => '示例应用', 'a.b' => '含点的键'],
]);

echo $config->get('app.name');        // 示例应用
echo $config->get('app.a/_b');        // 含点的键，路径段里的 /_ 还原为点
echo $config->get('app.timezone', 'UTC');
var_dump($config->has('app.name'));
```

- 路径分隔符是 `.`，键名里真实的点必须写成 `/_`，否则会被当成层级。
- 中途遇到非数组值就返回默认值，不做更深探底。
- `has()` 对 `null` 值返回 `true`，取值语义与 `get()` 一致。

## 运行期覆盖

`Config` 是只读基底，`Store` 在其上加覆盖层：`set()` 只写覆盖层，`base()` 与 `$kernel->config()` 保持原值，只有 `configStore()` 的读取结果变化。

```php
$kernel->configStore()->set('app.lang.default', 'en-us');
echo $kernel->configStore()->get('app.lang.default');   // en-us
echo $kernel->config()->get('app.lang.default');        // 仍是基底值
```

- 覆盖层优先：即使覆盖值是 `null`，也返回 `null` 而不回落基底。
- `set()` 返回 `void`，连续写入分行调用。
- 需要让覆盖值全局生效，应在启动阶段写入同一份 `Store`，不要另建一份。

## 环境变量

`Fxri\Config\Environment` 只解析 `.env` 文本成键值对，不读配置文件，也不写入 `$_ENV`、`$_SERVER` 或 `putenv()`；需要并入配置时由调用方自行取值拼装。

| 边界 | 行为 |
| --- | --- |
| 行首 `export ` | 去掉，`export APP_ENV=dev` 等价 `APP_ENV=dev` |
| 切分 | 以第一个 `=` 切分，左侧为键、右侧为值，两侧去空白 |
| 引号 | 只去掉成对的单/双引号，不做转义还原 |
| 行内注释 | 不处理，`APP_ENV=dev # 本地` 的值就是 `dev # 本地` |
| 缺失文件 | `fromFile()` 返回空集合，不抛异常，路径写错会被误判成空文件 |

旧版 `Env` 的运行期叠加由 `Store` 的 `env` 命名空间承接：`env.view.lang` 这类路径读同一份存储，不是指 `Environment`。

## 纯数组约束

配置文件只描述数据，不承担运行：

- 只 `return` 数组，不 echo、不写文件、不建连接。
- 不调用框架门面、不读 `$_SERVER`、`$_COOKIE` 等请求态，需要请求信息时在入口取好再传。
- 文件之间不依赖加载顺序，跨文件引用改为在代码里读 `$kernel->config()`。
- 路径拼接放在运行时（`Fxri\File\Path::join()`）或用项目常量，不在配置里现算。

## 注意事项

- 配置文件名区分大小写（取决于文件系统），`App.php` 与 `app.php` 是两个顶级键。
- 语言词条按「语言 / 域 / 词条」组织，`ArrayTranslator` 的缺省域是 `messages`，未命中原样返回词条本身。

明细与全部字段见仓库 `docs/config.md`，语言相关见 `docs/language.md`。
