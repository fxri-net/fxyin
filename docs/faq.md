# 常见问题

装配、迁移与日常使用里最容易卡住的地方，逐条给结论，并指到对应参考页。

## 定位

本页只回答「为什么这样设计」「这一步该用哪个入口」这类判断题；具体签名与参数表看对应的参考页，升级细节看[2.0 迁移说明](./migration-2.0)。

## 装配

### 内核里为什么没有容器和路由

风音框架只提供辅助能力。`Kernel` 持有配置、日志、翻译器、HTTP 客户端与接入方适配器，不解析路由、不做依赖注入、不接管请求生命周期。容器与路由属于宿主框架，需要互通时用 `Fxri\Integration\ThinkPHP\ThinkPHPAdapter` 把内核绑进容器，见[接入已有框架](./integration)。

### 不传任何装配项可以直接构建吗

可以。`KernelBuilder::make()->build()` 是合法用法：配置为空 `Config`，日志为 `NullLogger`，翻译器为内置 `ArrayTranslator`，`httpClient()` 与 `adapter()` 返回 `null`。按需注入即可，不必凑齐全部装配项。

### 多个配置目录谁覆盖谁

按 `withConfigDir()` 的追加顺序，后者覆盖前者；同一目录内按文件名排序加载，文件名为顶层键，文件返回非数组时视为该文件没有配置。嵌套数组是递归合并，不是整块替换。目录不存在时会在装配阶段抛 `InvalidArgumentException`，不会静默跳过，见[配置](./config)。

### 怎么替换默认翻译器

`withTranslator()` 接受任意 `Symfony\Contracts\Translation\TranslatorInterface` 实现。内置的 `ArrayTranslator::fromDirectory()` 按目录加载「词条 => 译文」的 PHP 文件，未命中时返回词条本身而不是抛出异常。

### 配置项怎么读

`$kernel->config()->get('app.name', '默认值')`，点号表示层级；`has()` 判存在，`all()` 取全量。读取发生在运行期，构建内核后配置不再变动。

## 迁移

### 1.x 的门面和全局函数去哪了

2.0 移除了 `\fxapp\*` 门面与全局函数，统一改成 `Fxri\*` 下的静态类与 `Kernel` 实例方法。逐项对照见[2.0 API 对照](./migration-2.0)，升级步骤见[1.x 升级 2.0 指南](./upgrade-2.0)。

### 原来的数字 type 参数怎么改

全部改为具名枚举或链式方法，例如文本处理用 `FieldTextMode`、UUID 用 `UuidMode`、时间粒度用 `DateGranularity`。旧的 `1.1.2` 这类点分模式号不再可用，`composer scan` 会拦截新增的数字枚举写法。

### 旧密文还能解密吗

能，仅解密：`DesCipher::decrypt($payload, $password)`。新数据一律用 `Cipher::make($key)` 加密，不要再用旧格式写新数据，见[安全](./security)。

### 之前返回的六元响应数组呢

改为 `Envelope` 的固定五键 `code` / `message` / `data` / `extend` / `debug`：旧数组的 `time` 位移除，耗时等调试信息并入 `debug`；分页接口的 `data` 用 `Data\Page` 的四键壳。见[HTTP](./http)。

### 1.x 还会继续发版吗

不会。1.x 只保留历史标签与更新日志，`v1.6.1` 是对终点的标记，未单独发版；正式发版从 2.0.0 开始。升级路线见[1.x 升级 2.0 指南](./upgrade-2.0)。

### PHP 7.x 或 8.0 / 8.1 能直接升 2.0 吗

不能。2.0 要求 PHP 8.2 起。先完成 PHP 升级，再做框架迁移，两件事不要放进同一次变更。

### 没有 Composer 的项目怎么升级

先补 `composer.json` 与自动加载，再按[接入形态手册](./upgrade-2.0-runtime)处理；2.0 只通过 Composer 分发，没有 `vendor/autoload.php` 就无法加载内核。

### `extend/fxri/data` 需要整个删除吗

不是。配置、门面扩展、启动钩子与语言包要分别迁到项目配置、项目服务与项目引导，不能整目录照搬。逐项处理见[扩展目录迁移](./upgrade-2.0-extend)。

### `safe` 模块会一起升级吗

不会。`extend/fxri/safe` 与 `safe_local` 是独立模块，自成命名空间，不在 fxyin 迁移范围内；按原状保留或单独排期。

## 使用

### `Fxri\Param` 和 `Fxri\Data\Param` 有什么区别

`Fxri\Param` 是入参定义与整形：`Definition`、`Shape`、`Pipeline`、`Merger`、`Picker`，管「补默认值、定形状、按需挑选」。`Fxri\Data\Param` 是数据整理工具：`exist()` 判断是否有非空值、`filter()` 去掉空值、`missing()` 找出空值键、`repair()` 补全多行结构的键。前者面向入口，后者面向整理，见[参数](./param)与[数据](./data)。

### SQL 构造器会连接数据库执行吗

不会。`WhereBuilder`、`StatementBuilder`、`MysqlDialect` 只产出 `Sql`（SQL 片段加 `bindings()`），执行与事务交给调用方的 PDO 或 ORM，见[SQL](./sql)。

### `Responder::send()` 会输出响应头吗

会按信封结果设置状态码与 `Content-Type` 并输出正文；如果响应头已经发出，则只输出正文。需要完全自定义响应头时，用 `format()` 取格式化后的字符串自行输出，见[HTTP](./http)。

### 文档站的「更新日志」能直接改吗

不能。`docs/changelog.md` 由 `pnpm sync:changelog-doc` 从根 `CHANGELOG.md` 生成，页面内也注明了请勿手改；要改内容请改根文件后重新同步，见[贡献指南](./contributing)。

### 一定要安装 PSR 相关包吗

不是。只有用到 `Http\HttpClient\Client`、`ClientFactory` 或 `fromPsrRequest()` 系列时才需要 `psr/http-client`、`psr/http-message` 等包；不安装也能使用其余能力，缺失时相关入口会明确报错，不会静默降级。

### 支持哪些 PHP 版本

PHP 8.2 起，CI 覆盖 8.2 至 8.5。`mbstring` 与 `openssl` 是必装扩展，其余依赖按需安装，见[快速开始](./getting-started)。

## 注意事项

- 本页描述的是当前 2.x 行为；历史版本的实际行为以[更新日志](./changelog)中的归档条目为准。
- 若实际表现与本页或参考页不符，先跑一次 `composer check` 确认基线，再回查对应页的签名表，避免把环境差异当成框架缺陷。
