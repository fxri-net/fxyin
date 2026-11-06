---
title: 行为变更
---

# 行为变更

本页列出 2.0 不再提供的能力与高频调用的行为差异；逐项 API 对照见[API 对照](./api)。

## 不再提供的能力

以下 1.x 入口不再存在；能力大多有替代，但调用方式必须改写：

- 门面静态转发（`Facade::__callStatic`）与 `\fxapp\*` 类名：改由 `Fxri\*` 与 `Application\Services`。
- 全局函数与 `app/function/` 目录约定：`Support\Type`、各服务，或 `Bootstrap::withFunctions()`。
- 配置驱动的六元响应结构（`server.echo.template` / `server.echo.format`）：改为 `Envelope` 固定五字段（`code`、`message`、`data`、`extend`、`debug`）。
- 点分模式号与整型 `type`：改为具名枚举与链式 `Shape`。
- `throwable()` 直接终止进程：改为抛异常，由接入层映射。
- 数据库连接与事务：fxyin 不接管，仍由项目数据库层负责。
- 递归上下级 SQL 与字段表达式：改为应用层 `Data\Hierarchy` 与 `Sql\Expression`，不再生成 MySQL 专有 SQL。

## 高频调用与行为差异

## 高频调用对照

以下条目来自对多个 1.x 接入方的只读统计，按出现频次排序；项目自有方法（如 `oracle()`、`Elven::echo()`）需要项目侧保留实现。

| 旧写法 | 新写法 | 说明 |
| --- | --- | --- |
| `Base::lang($name, $vars)` | `$kernel->trans($id, $parameters)` | 旧的列表拼接与按语言切换连接符需项目侧保留 |
| `Base::oracle($name)` | 项目工厂或容器 | 项目自有定位器，fxyin 不提供 |
| `Param::define([$data, $keys], '1.2.3')` | `Shape` / `Pipeline` | 点分码逐一对照见第四节 |
| `View::echo($data, 'json')` | `Responder`、`JsonFormatter` | XML 与视图类型由项目渲染器承接 |
| `Server::format($echo, 2)` | `Envelope::toArray()` 或项目序列化 | 键名固定，不再由配置映射 |
| `Base::config($name)` | `$kernel->config()->get($name, $default)` | 配置不再支持运行期写入 |
| `Param::json($data, 'encode')` | `Param\Json::encode()`、`Json::decode()` | 模式改为方法 |
| `Param::merge($limit, ...$args)` | `Merger::merge()`、`Merger::mergeKeepBlank()` | 深度与空值语义显式化 |
| `Server::echo()` | `Envelope::success()`、`Envelope::failure()` | 六元数组改为五键信封 |
| `Text::throwable($e)` | 抛异常，由接入层映射 | 不再直接终止进程 |
| `Data::paramEmpty($data, $type)` | `Data\Param::missing($data, NullPolicy::Blank)` | 空值策略改为枚举 |
| `Data::fieldJson(...)` | `Field::json(...)` | 见 7.3 |
| `View::log($data, $type)` | PSR-3 `LoggerInterface` | 通过 `KernelBuilder::withLogger()` 注入 |
| `Service::http(...)` | `HttpClient\Curl::request()` / `send()` | 需要 PSR-7 与 PSR-18 实现时改用 `HttpClient\Client::send()` |
| `File::getList($path, $ext)` | `Filesystem::findFiles($directory, $extensions)` | 扩展名过滤与深度由参数表达 |
| `Data::whereMake($data, $type)` | `WhereBuilder::build()`、`Expression` | 条件与绑定值对象化 |
| `Data::fieldEscape(...)` | `Field::escape()` | 见 7.3 |
| `Service::httpDown(...)` | `HttpClient\Curl::download()` | 非 2xx 不写文件 |
| `Math::rand($length, $type)` | `Random::string($length, RandomCharset::Alnum, RandomCharset::Alpha)` | 字符集改为枚举，首字符集单独指定 |
| `Text::timeMilli($mtime)` | `Time::milli()` | 见 7.10 |
| `Text::explode()`、`Text::implode()` | `Text\Str::explode()`、`Str::implode()` | 去重与限长参数调整 |
| `Client::ip($type)` | `ClientInfo::ip()`、`Text\Ip::toLong()` | 整型地址由 `Ip` 转换 |
| `Db::startTrans()` 等 | 项目数据库层 | fxyin 不接管连接与事务 |

## 行为差异

安装、加载、入口、响应结构与错误处理的总览见[API 对照](./api) 的「改动概览」；下表只列细节口径的差异：

| 维度 | 1.x | 2.0 |
| --- | --- | --- |
| 业务码类型 | 整型（如 `200`） | 整型，成功缺省 `200` |
| 扩展位与时间位 | `extend`、`time` 两个位置 | `extend` 为正式扩展字段，时间等调试信息并入 `debug` |
| 条件构造 | 字符串片段与白名单过滤 | 绑定参数与 `Sql` 对象 |
| 配置 | 允许加载期执行代码 | 只接受纯数组 |
| 门面 | `__callStatic` 按目录优先级查找 | 无门面层，改用显式类与内核 |

## 相关页面

- 参考：[API 对照](./api) · [升级总览](./) · [检查清单](./checklist)
