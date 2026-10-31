# AI 技能入口

技能真源（`SKILL.md` 与 `references/`）随包进入 `vendor/fxri/fxyin/skills/`，而 agent 默认不扫描 vendor 目录。`Fxri\Tooling\SkillInstaller` 在项目面技能目录写一份同名**入口薄壳**：只含 `name`、`description` 与真源位置，不复制正文。

## 定位

- **真源唯一**：技能内容只存在于本包内，升级包即生效，项目侧薄壳不需要随之改写。
- **薄壳可删**：入口带固定标记，删除后执行 `php vendor/bin/fxyin skills` 即可补齐，不影响任何运行行为。
- **不写全局**：只在项目内的目标目录生成文件；全局技能目录由各 agent 自行管理，本工具不触碰。
- **不覆盖项目自有技能**：同名文件若不带本包标记，视为项目自己写的技能，安装时跳过并报告。

## 命令行

接入项目直接用统一入口，不需要自己写脚本：

```bash
# 安装到默认目录 .agents/skills
php vendor/bin/fxyin skills

# 同时安装到 Trae 读取的目录
php vendor/bin/fxyin skills --target .trae/skills

# CI 里只校验不写盘，不一致以 1 退出
php vendor/bin/fxyin skills --check
```

仓库内把 `vendor/bin/fxyin` 换成 `php fxyin`。命令总览见[命令行](./cli)。需要定制目标目录以外的场景，可直接调用 `SkillInstaller::install()` / `check()`，签名见下表。

## 参数与返回值

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `install(string $projectRoot, array $targets = ['.agents/skills'])` | 为包内每个技能在每个目标目录写一份薄壳；带本包标记的同名文件直接覆盖，不带标记的跳过 | `array{installed: list<string>, skipped: list<string>}` |
| `check(string $projectRoot, array $targets = ['.agents/skills'])` | 逐个校验薄壳存在且内容与当前真源一致；两侧行尾按 LF 归一再比较 | `bool` |
| `render(string $name, string $description, string $source)` | 渲染单个薄壳的文本，便于直接落盘 | `string` |

约束：

- 目标目录必须是**相对项目根**的路径，空串、绝对路径、带盘符或含 `..` 段一律抛 `InvalidArgumentException`；
- 真源路径写在薄壳里，落在项目根之下时用相对路径（可随仓库提交），否则退回实际路径；
- 包内技能缺 `name` 或 `description` 时抛 `InvalidArgumentException`，正常发版不会出现。

## 注意事项

- `check()` 只认文本一致：行尾差异已抹平，但多余空行、缩进变化仍判定漂移，改完真源重新安装即可。
- 薄壳里的 `description` 是触发匹配的原文拷贝；本包升级改了描述而项目没重跑安装时，`check()` 会报不一致，属于预期提醒。
- 本仓库自带 `php fxyin skills`（`--target`、`--check`、`--root`），通过 Composer bin 在接入项目里对应 `php vendor/bin/fxyin skills`。本仓库自身的验收命令见[贡献指南](./contributing)。
