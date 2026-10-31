# IDE 元数据

## 解决什么

PHP 无法从字符串字面量推导返回类型：写下 `Locator::get('base')` 后，编辑器只能看到工厂方法声明的返回类型（通常是 `object`），补全不出具体类的方法，也不能跳转。`.phpstorm.meta.php` 补上这条映射，PhpStorm 与 VS Code 的 Intelephense 都会读取项目根目录下的该文件。

该文件只是 IDE 的补充文件：从不被 `require`，不进自动加载，不参与运行。

## 生成与校验

`Fxri\Tooling\MetaGenerator` 是纯静态工具，入参一份映射、出参一段文本，不读容器、不加载运行时。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `render(array $registries)` | 渲染完整文件内容；工厂方法按名称排序，映射键保持传入顺序，值统一输出为 `\全限定类名::class` | `string` |
| `check(string $file, array $registries)` | 读取文件并与 `render()` 结果比对；两侧行尾都按 LF 归一再比较，文件不存在或读不到返回 `false` | `bool` |

`$registries` 的类型是 `array<string, array<string, class-string>>`，即「工厂方法全名 => 名称映射」：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Oracle\Base;
use App\Oracle\User;
use Fxri\Tooling\MetaGenerator;

$file = __DIR__ . '/../.phpstorm.meta.php';

// 工厂方法全名 => 名称映射；键必须与调用处传入的字符串字面量一致
$registries = [
    'App\Locator::get' => [
        'base' => Base::class,
        'user' => User::class,
    ],
];

// --check 只比对不写盘，由 CI 直接看退出码
if (in_array('--check', $argv, true)) {
    exit(MetaGenerator::check($file, $registries) ? 0 : 1);
}

file_put_contents($file, MetaGenerator::render($registries));
```

生成的产物形如：

```php
<?php

namespace PHPSTORM_META {
    override(\App\Locator::get(0), map([
        'base' => \App\Oracle\Base::class,
        'user' => \App\Oracle\User::class,
    ]));
}
```

渲染时的校验规则：

- 工厂方法全名必须为非空字符串且形如 `类名::方法名`，类名可带前导反斜杠；
- 映射键必须是小写短名（允许点号、下划线、连字符）；
- 类名逐段校验，输出时去掉多余前导反斜杠再补一个；
- 任一入参不合法抛 `InvalidArgumentException`，文案为中文；
- 某个工厂方法映射为空时输出 `map([])`，整份映射为空时只留空的命名空间块。

## 挂到 composer 脚本

把生成与校验挂到接入项目的 `composer.json`，本地与流水线共用同一入口：

```json
{
    "scripts": {
        "meta": "@php tools/meta.php",
        "meta:check": "@php tools/meta.php --check"
    }
}
```

在接入项目里定义上述脚本后，日常改动映射执行 `composer meta` 并提交产物；CI 只跑 `composer meta:check`，退出码非 0 即说明文件与映射漂移，不需要在流水线里重新生成。本包不自带这两个脚本。

## 已知边界

- 其它 IDE 不认识该文件，工厂方法照旧回退到声明的返回类型（一般是 `object`），补全变弱但运行不受影响。
- `check()` 只认文本一致：行尾差异已抹平，但多余空行、缩进变化仍会判定不一致，改完映射请重新生成。
- 映射键是字符串字面量匹配；调用处传变量时编辑器无法推断，这是元数据的固有边界。
- 文件必须放在项目根目录，PhpStorm 与 Intelephense 都按这个位置自动读取，放到别处不会被加载。
- 本库不提供现成的 `.phpstorm.meta.php`，由接入项目自行生成并提交。

完整示例与渲染细节见仓库 `docs/ide.md`。
