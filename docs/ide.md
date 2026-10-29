# IDE 元数据

`.phpstorm.meta.php` 让「按字符串名称取对象」的工厂方法在编辑器里得到具体返回类；它只是 IDE 的补充文件，不参与运行。

## 定位

`Fxri\Tooling\MetaGenerator` 只负责把映射渲染成文本、再比对文本是否与映射一致，本身不读容器、不加载任何运行时代码。它解决的问题是：PHP 无法从字符串字面量推导返回类型，写下 `Locator::get('base')` 后，编辑器只能看到工厂方法声明的返回类型（通常是 `object`），补全不出 `Base` 的方法，也不能 Ctrl+点击跳过去。

生成的文件遵循 PhpStorm 的元数据语法，PhpStorm 与 VS Code 的 Intelephense 都会读取项目根目录下的 `.phpstorm.meta.php`。其它 IDE 不认识该文件，工厂方法照旧回退到声明的返回类型（一般是 `object`），补全变弱，但运行行为不受影响：文件从不被 `require`，也不进自动加载。

这一层与[支撑](./support)的纯静态工具同样无状态：入参就是一份映射，出参就是一段字符串，写盘与退出码由接入项目的脚本决定。

## 最小示例

在项目里放一个生成脚本，映射写全名、键写字符串字面量，其余交给生成器：

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

生成的 `.phpstorm.meta.php`：

```php
<?php

namespace PHPSTORM_META {
    override(\App\Locator::get(0), map([
        'base' => \App\Oracle\Base::class,
        'user' => \App\Oracle\User::class,
    ]));
}
```

## 参数与返回值

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `render(array $registries)` | 渲染完整文件内容；工厂方法按名称排序，映射键保持传入顺序，值统一输出为 `\全限定类名::class` | `string` |
| `check(string $file, array $registries)` | 读取文件并与 `render()` 结果比对；两侧的行尾都按 LF 归一再比较，文件不存在或读不到返回 `false` | `bool` |

`$registries` 的类型是 `array<string, array<string, class-string>>`，即「工厂方法全名 => 名称映射」。渲染时的校验规则：

- 工厂方法全名必须为非空字符串且形如 `类名::方法名`，类名可带前导反斜杠；
- 映射键必须是小写短名（允许点号、下划线、连字符），与调用处传入的字面量一致；
- 类名逐段校验，输出时去掉多余前导反斜杠再补一个；
- 任一入参不合法抛 `InvalidArgumentException`，文案为中文；
- 某个工厂方法映射为空时输出 `map([])`；整份映射为空时只留空的命名空间块。

## composer 脚本与 CI

把生成与校验挂到 `composer.json`，本地与流水线共用同一入口：

```json
{
    "scripts": {
        "meta": "@php tools/meta.php",
        "meta:check": "@php tools/meta.php --check"
    }
}
```

日常改动映射后执行 `composer meta` 并提交生成的文件；CI 里只跑 `composer meta:check`，退出码非 0 即说明文件与映射漂移，不需要在流水线里重新生成。仓库自身的验收命令见[贡献指南](./contributing)。

## 注意事项

- 工厂方法按名称排序、映射键不排序：排序只为让输出可逐字节比对，键的先后顺序按配置书写顺序保留，便于人工对照。
- `check()` 只认文本一致，行尾差异已被抹平，但多余的空行、缩进变化仍会判定不一致，改完映射请重新生成。
- 映射键是字符串字面量匹配，调用处传变量时编辑器无法推断，属于元数据的固有边界。
- 生成的文件放在项目根目录：PhpStorm 与 Intelephense 都按这个位置自动读取，放到别处不会被加载；该文件由接入项目自行生成并提交，本库不提供现成文件。
