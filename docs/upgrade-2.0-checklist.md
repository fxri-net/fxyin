---
title: 升级 2.0：检查清单
outline: [2, 3]
---

# 升级 2.0：检查清单

本页把升级过程压缩成可复制的清单。先跑一次体检，确认自己属于 S、M、L 哪一档，再按阶段勾选；清单不替代[升级总览](./upgrade-2.0)与[扩展目录迁移](./upgrade-2.0-extend)里的判断。

## 一、通用前置

- [ ] 确认实际部署 PHP 版本达到 8.2，`composer.json` 的旧约束已同步
- [ ] 确认项目有 `composer.json` 与 `vendor/autoload.php`；没有则先补接入
- [ ] 为 1.x 目录、部署包与数据库打快照
- [ ] 记录当前入口文件、宿主版本与扩展模块清单
- [ ] 明确 `extend/fxri/safe` 等独立模块不在本次范围

## 二、阶段 0：体检与基线

- [ ] 运行 `profile`，确认宿主框架、入口形态与阻塞项
- [ ] 运行 `scan`，记录 `\fxapp\*::`、`$echo[n]`、点分模式号、`is_*`、`fxy_*`、`\fxyin\` 数量
- [ ] 生成基线文件并入库
- [ ] 确定迁移路线：S 直接改造 / M 过渡门面 / L 过渡门面 + 基线
- [ ] 选一个非生产环境或可快速回滚的实例做试点

```bash
php tools/upgrade-inspect.php profile --root=/path/to/project --markdown
php tools/upgrade-inspect.php scan --root=/path/to/project --markdown
php tools/upgrade-inspect.php baseline --root=/path/to/project --write --baseline=/path/to/project/upgrade-baseline.json
```

## 三、阶段 1：环境与依赖

- [ ] 删除 `extend/fxri/base`
- [ ] `composer require fxri/fxyin:^2.0`
- [ ] 清理为旧目录保留的 PSR-0 / PSR-4 映射
- [ ] 确认没有两套自动加载同时生效
- [ ] `composer install` 与 `composer validate --strict` 通过

## 四、阶段 2：引导与装配

- [ ] 入口改为 `require vendor/autoload.php` + `KernelBuilder`
- [ ] 删除 `extend/fxri/bootstrap.php` 与 `\fxyin\App::init()`
- [ ] 按宿主形态完成容器绑定或 `KernelHolder` 持有
- [ ] CLI 冒烟通过（如 `php think list`）
- [ ] HTTP 冒烟返回 200

## 五、阶段 3：配置

- [ ] `data/app/config/*` 迁到项目 `config/`
- [ ] 配置文件中不再调用 `\fxapp\*`、`fxy_*`、`\fxyin\*`
- [ ] 配置文件中不再读取 `$_SERVER`、`$_COOKIE` 等请求态
- [ ] 配置文件之间不再有加载顺序依赖
- [ ] `facade.data.where` 与 `server.echo.*` 已删除或迁为项目对象
- [ ] 路径拼接改为运行时 `Path::join()` 或项目常量
- [ ] `frame.provider` 迁到宿主 provider 配置

## 六、阶段 4：扩展目录

- [ ] `env.app` 的多应用目录改为项目命名空间
- [ ] `env.facade` 与 `loader` 配置删除
- [ ] `facade_ext` 方法迁入项目服务或过渡门面
- [ ] `oracle` 改为显式工厂或容器
- [ ] `store/common.php` 拆为项目引导与中间件
- [ ] `language/` 迁到项目语言目录并由 `ArrayTranslator` 加载
- [ ] `function/common.php` 已删除或迁入 `autoload.files`
- [ ] `think/Exception.php` 改用统一异常映射

## 七、阶段 5：代码分域

- [ ] 配置与翻译：`Base::config`、`Base::lang`、`Lang::get` 已替换
- [ ] 参数：`Param::define`、`Param::merge`、`Param::array` 已替换
- [ ] SQL：`Data::where*`、`whereMake` 已改为 `WhereBuilder`
- [ ] 数据：`fieldJson`、`fieldText`、`fieldDate`、`paramEmpty` 已替换
- [ ] 文本：`Text::*` 与 `Math::rand` 已替换
- [ ] 文件：`File::getList`、`createDirectory` 已替换
- [ ] 安全：`Safe::*` 已替换，旧密文用 `LegacyCipher`
- [ ] HTTP：`Server::*`、`Client::*`、`View::*` 已替换
- [ ] 客户端：`Service::http` 改为 PSR-18 客户端

## 八、阶段 6：响应与异常

- [ ] 新代码使用 `Envelope`
- [ ] 旧代码的六元数组由项目 `LegacyResponse` 保持
- [ ] 对外字段 `code`、`message`、`data`、`extend`、`time` 未变化
- [ ] 异常统一映射为失败信封，不再直接输出堆栈
- [ ] 新旧响应双跑比对无差异

## 九、阶段 7：验收与清理

- [ ] `baseline --check` 零新增
- [ ] 过渡层调用量归零
- [ ] 删除 `\fxapp\` 命名空间与 PSR-4 映射
- [ ] 删除 `extend/fxri` 残留目录
- [ ] 全仓检索旧 API 与点分模式号归零
- [ ] `composer validate --strict`、静态分析、测试、文档构建全部通过
- [ ] 回滚演练完成

## 十、S / M / L 差异

| 档位 | 判定 | 额外要求 |
| --- | --- | --- |
| S | `\fxapp\*::` 少于 100 | 可跳过过渡门面，直接替换；仍建议保留基线 |
| M | 100–500 | 建项目内过渡门面；按能力域分批替换；每批跑一次基线 |
| L | 大于 500 | 在 M 基础上增加按目录基线、关键接口双跑、分环境灰度 |

## 十一、基线文件

```json
{
  "version": 1,
  "generatedAt": "2026-10-28 03:30",
  "root": "/path/to/project",
  "directories": {
    "app": 1512,
    "extend/fxri/data": 46,
    "public": 1
  },
  "total": 1559
}
```

校验：

```bash
php tools/upgrade-inspect.php baseline --root=/path/to/project --check --baseline=/path/to/project/upgrade-baseline.json
```

规则：任一目录的计数高于基线即失败；低于基线时提示下调基线。迁移完成后基线归零，可删除文件。

## 十二、回滚清单

- [ ] 1.x 目录与部署包仍在备份位置
- [ ] 入口可切回旧 `bootstrap.php`
- [ ] Composer 约束可退回 `^1.6`
- [ ] 数据库未做不可逆变更
- [ ] 旧密文仍可通过 `LegacyCipher` 解密
- [ ] 对外响应协议未与框架升级捆绑

## 十三、常见卡点

| 现象 | 原因 | 处理 |
| --- | --- | --- |
| 入口 500，提示找不到类 | 同时保留了两套自动加载，或旧 `autoload.php` 仍被引用 | 删除旧引导，只保留 Composer |
| 配置加载报错 | 配置文件仍在调用 `\fxapp\*` 或读取 `$_SERVER` | 按配置纯度规则拆分 |
| 异常页输出堆栈 | 仍在使用 `Text::throwable()` 或宿主默认 Handler | 改用统一异常映射 |
| 响应字段缺失 | 内部直接用了 `Envelope::toArray()`，丢了 `extend`、`time` | 用项目 `LegacyResponse` 保持协议 |
| 门面方法找不到 | `facade_ext` 还未迁入过渡层 | 补提供者，或改为显式类 |
| 无 Composer 项目无法装 2.0 | 2.0 只通过 Composer 分发 | 先补 `composer.json` 与自动加载 |
| `safe` 模块报错 | 误把它当成 fxyin 一并删除 | 保留原目录，单独排期 |
