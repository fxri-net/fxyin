# 体检验收工具

`tools/upgrade-inspect.php` 是随仓库分发的 1.x 升级体检工具，不进入 Composer 安装包。它只读：不 `include` 项目文件、不执行 Composer、不修改被测项目。

## 三个子命令

| 子命令 | 作用 | 输出 |
| --- | --- | --- |
| `profile` | 项目画像：PHP 约束、宿主框架、入口候选、Composer 状态、扩展模块、旧门面调用量与档位、阻塞项与建议路线 | Markdown 或 JSON |
| `scan` | 旧 API 统计：按目录与能力域计数，并给出高频方法与替换建议 | Markdown 或 JSON |
| `baseline` | 生成或校验只减不增的基线 | 写文件或退出码 |

## 用法

```bash
# 画像与统计，缺省输出 Markdown；--json 与 --markdown 只能选一个
php tools/upgrade-inspect.php profile --root=<项目> --markdown
php tools/upgrade-inspect.php scan    --root=<项目> --markdown

# 基线：--write 生成，--check 校验，两者必选其一且必须显式给 --baseline
php tools/upgrade-inspect.php baseline --root=<项目> --write --baseline=<文件>
php tools/upgrade-inspect.php baseline --root=<项目> --check --baseline=<文件>
```

| 参数 | 说明 |
| --- | --- |
| `--root=<目录>` | 被检项目根目录，缺省为当前目录；不存在或不是目录时按用法错误退出 |
| `--json` / `--markdown` | 输出格式，缺省 Markdown，两者互斥 |
| `--write` | 生成基线文件，父目录不存在会自动创建 |
| `--check` | 按基线校验当前项目，只报结果不写文件 |
| `--baseline=<文件>` | 基线文件路径，`baseline` 子命令必须显式给出 |

## profile 看点

- `php`：`composer.json` 里解析出的 PHP 约束，以及是否低于 8.2。
- `framework`：从 `composer.lock` 与 `composer.json` 推断的宿主框架。
- `entry`：命中的入口候选列表（如 `index.php`、`public/index.php`）。
- `composer`：`composer.json`、`composer.lock`、`vendor/autoload.php` 是否存在。
- `modules`：旧框架目录下的子目录清单；独立扩展模块会被列为提示项，是否纳入迁移由项目判断。
- `layout`：应用目录、门面命名空间、扩展命名空间、门面类与全局函数数量。
- `usage`：旧门面调用数与体量档位；`recommendedRoute` 给出建议路线。
- `blockers`：硬阻塞项，如 PHP 约束低于 8.2、缺少 Composer 接入。

## scan 统计项

| 统计键 | 含义 |
| --- | --- |
| `fxapp` | 旧门面方法调用数（如 `\fxapp\Base::config(`），是体量档位与基线的依据 |
| `echo_indexes` | 六元响应数组的下标访问（`$echo[0]` 到 `$echo[5]`） |
| `dot_codes` | 点分模式号字面量（形如 `'1.1.2'`） |
| `is_functions` | `is_*` 校验函数调用 |
| `fxy_functions` | `fxy_*` 全局函数调用 |
| `fxyin_refs` | 旧命名空间引用 |
| `facade_extends` | 继承旧门面基类的类 |
| `facade_ext_refs` | 门面扩展命名空间引用 |
| `oracle_refs` | 旧工厂目录引用 |

另有两张清单：`topMethods` 取调用量前 20 的方法并附替换建议；`localOnlyMethods` 列出对等矩阵里没有落点的旧方法，需要项目自行决定去处。扫描范围会排除依赖与运行产物目录（如 `vendor`、`runtime`、`node_modules`、`.git`）。

## baseline 文件

```json
{
  "version": 1,
  "generatedAt": "2026-10-28 03:30",
  "root": "/path/to/project",
  "directories": {
    "app": 120,
    "extend/fxri/data": 46,
    "public": 1
  },
  "total": 167
}
```

- `directories` 的键来自项目自身的 1.x 配置（应用目录名不固定），缺失时才探测常见命名。
- `total` 是旧门面调用总数，用于趋势描述；门禁以 `directories` 逐目录为准。

## 退出码与门禁

| 退出码 | 含义 |
| --- | --- |
| 0 | 执行成功；`--check` 时全部目录未超基线 |
| 1 | `--check` 时有目录高于基线；`--write` 时序列化或写盘失败 |
| 2 | 用法错误：子命令未知、`--root` 不可用、缺少 `--baseline`、`--write` 与 `--check` 未给，或基线文件不存在/不是合法 JSON/缺少 `directories` |

`--check` 的规则：任一目录计数高于基线即失败并列出超出项；低于基线只提示「可下调基线」。迁移完成后基线归零即可删除文件。

## 使用节奏

1. 阶段 0 跑 `profile` 与 `scan`，确定路线并生成基线入库。
2. 每完成一个能力域替换跑一次 `baseline --check`，只增不减。
3. 流水线里把 `--check` 作为门禁，不在流水线里重新生成基线。
4. 阶段 7 确认基线归零，再删除过渡层与旧目录。

工具给出的是线索与计数，不替项目决定某个旧方法该替换成什么；对等矩阵与 API 对照是第二来源，见 `references/mapping.md` 与仓库 `docs/migration-2.0.md`、`docs/parity-1.6.1.md`。
