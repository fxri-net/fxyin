# 命令行入口

统一入口是 `fxyin`：仓库内 `php fxyin <命令>`，接入项目 `php vendor/bin/fxyin <命令>`。帮助、参数与退出码只有一份实现（`Fxri\Tooling\Cli`）；新增命令在 `Cli::COMMANDS` 注册一个域，不要另起入口。

## 命令

| 命令 | 随包 | 作用 |
| --- | --- | --- |
| `skills` | 是 | 安装 AI 技能入口薄壳，指向包内真源 |
| `upgrade` | 是 | 1.x 升级体检：`profile` / `scan` / `baseline` |
| `scan` | 是 | 扫描数字码等禁用写法 |
| `package` | 否 | 发布包卫生检查，本仓专用 |
| `parity` | 否 | 对等矩阵审计，本仓专用 |

退出码：`0` 成功、`1` 门禁不通过、`2` 用法错误。

## 常用示例

```bash
php vendor/bin/fxyin help
php vendor/bin/fxyin help upgrade

# 安装技能薄壳；默认 .agents/skills，Trae 读取 .trae/skills
php vendor/bin/fxyin skills
php vendor/bin/fxyin skills --target .trae/skills
php vendor/bin/fxyin skills --check

# 升级体检：接入项目用 vendor/bin/fxyin，仓库内用 php fxyin
php vendor/bin/fxyin upgrade profile --root=. --markdown
php vendor/bin/fxyin upgrade scan --root=. --markdown
php vendor/bin/fxyin upgrade baseline --root=. --write --baseline=upgrade-baseline.json

# 业务项目通常显式传源码目录（缺省扫 src）
php vendor/bin/fxyin scan app
```

## 边界

- 子目录调用要自己指对 `vendor/bin` 路径（`php ../vendor/bin/fxyin ...`），PHP 不会向上找 `vendor`。
- 包无法替接入方注册 `composer fxyin`：Composer 只执行根项目的 scripts；需要时在项目根 `composer.json` 加 `"scripts": {"fxyin": "@php vendor/bin/fxyin"}`。
- `package` / `parity` 依赖本仓库文件，接入项目看不到；随包域的实现文件若被误排除，入口直接报错。
