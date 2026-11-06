---
title: 门面扩展
---

# 门面扩展

旧 `Facade` 目录里的自定义门面在 2.0 没有对应载体，按项目规模与既有代码有下面三种替代；目录职责、配置硬规则与启动钩子见[扩展目录](./extend)。

## 方案一：项目内过渡门面（推荐给 M/L）

在项目里保留 `\fxapp\` 命名空间，Composer 映射到 `app/legacy/fxapp/`；旧调用点不动，门面内部改调 `Fxri\*`。

```json
{
  "autoload": {
    "psr-4": {
      "app\\": "app/",
      "fxapp\\": "app/legacy/fxapp/"
    }
  }
}
```

```php
<?php

declare(strict_types=1);

namespace fxapp;

use App\Support\KernelHolder;

abstract class LegacyFacade
{
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $provider = static::provider();
        if (!is_callable([$provider, $name])) {
            throw new \BadMethodCallException(static::class . '::' . $name . '() 尚未迁移');
        }

        return $provider->{$name}(...$arguments);
    }

    abstract protected static function provider(): object;
}

final class Base extends LegacyFacade
{
    protected static function provider(): object
    {
        return new \App\Support\Legacy\BaseProvider(KernelHolder::get());
    }
}
```

提供者只承接旧方法，内部换成 2.0 入口：

```php
<?php

declare(strict_types=1);

namespace App\Support\Legacy;

use Fxri\Kernel;

final class BaseProvider
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function config(string $name, mixed $default = null): mixed
    {
        return $this->kernel->config()->get($name, $default);
    }

    public function lang(string|array $name, array $vars = []): string
    {
        return $this->kernel->trans(is_array($name) ? implode('', $name) : $name, $vars);
    }
}
```

## 方案二：显式静态类（推荐给 S）

调用量小的项目直接删除 `\fxapp\` 命名空间，把每个门面改成显式类，调用点同步替换：

```php
namespace App\Support;

use Fxri\Kernel;

final class Config
{
    public static function get(Kernel $kernel, string $name, mixed $default = null): mixed
    {
        return $kernel->config()->get($name, $default);
    }
}
```

## 方案三：宿主容器绑定

宿主有容器时，把内核与项目服务绑进容器，调用点改为容器解析。ThinkPHP 用 `ThinkPHPAdapter::bind()`，其他容器用各自的 `instance` / `set` 入口。

## oracle 与自定义定位器

`Base::oracle($name)` 是项目自己的服务定位器，与 fxyin 无关。迁移时保留实现，改成显式工厂：

```php
final class OracleFactory
{
    public static function make(string $name): object
    {
        $class = '\\App\\Oracle\\' . ucfirst(strtolower($name));
        if (!class_exists($class)) {
            throw new \InvalidArgumentException('未定义的 oracle：' . $name);
        }

        return new $class();
    }
}
```

有容器时优先用容器管理单例，避免继续维护静态缓存。
