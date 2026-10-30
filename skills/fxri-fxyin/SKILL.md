---
name: fxri-fxyin
description: 风音框架（fxri/fxyin）的接入与日常使用：安装、KernelBuilder 内核装配、配置目录递归与覆盖规则、Envelope 五键响应信封与分页壳、Param/Data/Text/File/Http 常用 API、`.phpstorm.meta.php` 元数据生成，以及统一命令行入口 `fxyin`（skills/upgrade/scan）。用户提到 fxyin、风音框架、fxri/fxyin、KernelBuilder、内核装配、只读内核、配置叠加、点号路径取值、五键信封、Envelope、分页壳、Page、参数定形、Shape、字段表达式、文本与时间、文件路径、Cookie 签名、IDE 补全、phpstorm.meta.php、MetaGenerator、Intelephense、命令行入口、fxyin 命令、vendor/bin/fxyin、技能薄壳、升级体检 时使用；英文说法如 fxyin setup、kernel builder、config loader、response envelope、API cheat sheet、fxyin cli 同样适用。不用于 1.x 升级 2.x（走 fxri-fxyin-upgrade），不用于业务项目的具体取舍、框架源码改造或发版流程。
license: Apache-2.0
metadata:
  version: "1.1.0"
  author: fxri
  source: https://github.com/fxri-net/fxyin
---

# 风音框架接入与使用

> 本技能版本 1.1.0（随 fxri/fxyin 同批分发）。
>
> 本技能随 `fxri/fxyin` 仓库分发，只承载框架通用能力。业务项目的取舍（目录规划、门面去留、响应协议变更）属于项目自身决策，以项目任务记录为准，不写进本技能。

## 何时使用

- 第一次接入框架：装包、装配内核、写入口、接命令行。
- 需要统一响应结构：五键信封、分页壳、格式化器、调试字段。
- 配置目录怎么组织、多目录如何叠加、点号路径怎么取值。
- 想让按字符串名称取对象的工厂方法在编辑器里补全出具体类。
- 只想快速查某个类有哪些方法、参数怎么写。

中英文触发词：`fxyin`、风音框架、`fxri/fxyin`、`KernelBuilder`、kernel、内核、装配、`Envelope`、响应信封、`Page`、分页、`ConfigLoader`、配置叠加、`Shape`、参数定形、`Field`、字段表达式、`Str`、`Time`、`Filesystem`、`MetaGenerator`、`phpstorm.meta.php`、Intelephense、setup、envelope、config loader、API cheat sheet。

## 决策树

```text
要做什么？
├─ 安装、装配内核、入口与命令行、宿主容器绑定   → references/setup.md
├─ 统一响应：信封五键、分页四键、格式化与调试   → references/response.md
├─ 配置目录、递归加载、覆盖规则、纯数组约束     → references/config.md
├─ 编辑器补全、.phpstorm.meta.php 生成与校验     → references/ide.md
├─ 命令行入口：技能薄壳、升级体检、代码扫描     → references/cli.md
└─ 只查某类的方法签名与返回值                   → references/api.md
```

需要完整参数表、注意事项与示例时，回到仓库文档：接入看 `docs/getting-started.md` 与 `docs/kernel.md`，其余按能力页 `docs/param.md`、`docs/data.md`、`docs/text.md`、`docs/file.md`、`docs/http.md`、`docs/config.md`、`docs/ide.md`、`docs/cli.md`。

## 常用命令

```bash
# 安装，PSR-4 自动加载，Fxri\ 对应 src/，不需要额外启动文件
composer require fxri/fxyin

# 统一命令行：接入项目用 vendor/bin/fxyin，仓库内用 php fxyin
php vendor/bin/fxyin help            # 全部命令
php vendor/bin/fxyin skills          # 安装 AI 技能入口薄壳
php vendor/bin/fxyin skills --check  # 只校验，不一致以 1 退出

# 接入项目自定义的 composer 脚本示例：生成与校验 .phpstorm.meta.php
composer meta          # 生成，提交产物
composer meta:check    # 只比对不写盘，CI 看退出码
```

元数据生成器是 `Fxri\Tooling\MetaGenerator`，只有 `render(array $registries)` 与 `check(string $file, array $registries)` 两个静态方法；写盘与退出码由项目脚本决定，用法见 `references/ide.md`。

## 边界声明

- 不接管路由、依赖注入容器、中间件与 MVC 基类；装配结果只读，运行期改配置只进 `configStore()` 覆盖层。
- 参数层只做定形与取值，不做业务校验与标量强转；校验失败自行抛 `Fxri\Exception\ValidationException`。
- 视图只解析模板路径并做越界校验，模板引擎由接入方实现。
- IDE 元数据只有 PhpStorm 与 Intelephense 读取，其它 IDE 仍回退到工厂方法声明的返回类型（一般是 `object`），运行行为不受影响。
- 1.x 升级 2.x 的体检、路线、基线不在此技能，见 `fxri-fxyin-upgrade`。
- 本技能不含任何业务项目的目录约定、门面保留策略或对外协议决策。
