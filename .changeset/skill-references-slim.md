---
"fxri/fxyin": patch
---

- 修复：`fxri-fxyin` 技能的环境要求把 `openssl` 写成「供安全相关能力」，实际已随 `composer.json` 声明为必需扩展
- 优化：技能 `SKILL.md` 的 composer 脚本示例改为指向 `references/ide.md`，不再与技能参考页重复
- 优化：`references/config.md` 删去与加载规则表重复的条目，技能版本升至 1.1.2
