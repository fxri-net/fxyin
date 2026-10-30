# 命令行

仓库与接入项目共用一个入口 **`fxyin`**：仓库内执行 `php fxyin <命令>`，接入项目执行 `php vendor/bin/fxyin <命令>`。帮助、参数解析与退出码只有一份实现（`Fxri\Tooling\Cli`），各命令的实现脚本只负责自己的业务逻辑。

## 调用方式

| 场景 | 命令 |
| --- | --- |
| fxyin 仓库 | `php fxyin help` |
| 接入项目（项目根） | `php vendor/bin/fxyin help` |
| 接入项目（子目录） | `php ../vendor/bin/fxyin help`（路径要指对，PHP 不会自动往上找 `vendor`） |
| 可选：项目 composer 脚本 | 根 `composer.json` 加 `"scripts": {"fxyin": "@php vendor/bin/fxyin"}`，之后可用 `composer fxyin ...` |

⚠️ Composer 只执行**根项目**的 `scripts`，依赖包无法替接入方注册 `composer fxyin`；需要时自行加上面那一行。

## 命令一览

| 命令 | 随包 | 作用 |
| --- | --- | --- |
| `skills` | 是 | 安装 AI 技能入口薄壳，指向包内真源 |
| `upgrade` | 是 | 1.x 升级体检：`profile` / `scan` / `baseline` |
| `scan` | 是 | 扫描数字码等禁用写法 |
| `package` | 否 | 发布包卫生检查，本仓专用 |
| `parity` | 否 | 对等矩阵审计，本仓专用 |

`package` 与 `parity` 依赖本仓库文件，不会出现在接入项目的命令列表里；随包域的实现文件若被误排除，入口会直接报错，而不是静默少一条命令。

## 常用示例

```bash
# 总帮助与单个命令帮助
php fxyin help
php fxyin help upgrade

# 接入项目里安装 AI 技能薄壳
php vendor/bin/fxyin skills
php vendor/bin/fxyin skills --check

# 升级体检
php fxyin upgrade profile --root=<项目> --markdown
php fxyin upgrade scan --root=<项目> --markdown

# 数字码扫描：缺省扫 src，业务项目通常显式传自己的源码目录
php fxyin scan app
```

## 退出码

| 码 | 含义 |
| --- | --- |
| `0` | 成功 |
| `1` | 门禁不通过（存在违规、薄壳不一致等） |
| `2` | 用法错误（未知命令、缺参数、路径非法） |

## 新增命令

在 `src/Tooling/Cli::COMMANDS` 注册一个域（摘要、实现脚本、是否随包），实现脚本放 `tools/`（本仓专用）或 `src/Tooling/`（随包），并按需提供 `--help`。入口只允许一个，不再新增第二套命令行。

## 打包约束

- 随包命令的实现文件不得被 `export-ignore` 排除，`php fxyin package` 会校验；
- 接入项目出现「安装包缺少随包命令的实现文件」即说明打包配置回退，需要修正 `.gitattributes`。
