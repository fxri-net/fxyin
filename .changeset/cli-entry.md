---
"fxri/fxyin": minor
---

- 新增：统一命令行入口 `fxyin`，仓库内用 `php fxyin <命令>`，接入项目经 Composer bin 用 `php vendor/bin/fxyin <命令>`
- 新增：命令域 `skills`、`upgrade`、`scan` 随包提供；`package`、`parity` 保留为本仓内部命令
- 新增：`skills` 改为原生实现，替代原先不随包的 `tools/install-skills.php`
- 修改：升级体检工具与对等矩阵快照随包分发，接入项目可直接运行 `vendor/bin/fxyin upgrade`
- 修改：`composer.json` 声明 `bin`，`scan`、`package`、`parity`、`upgrade:inspect` 脚本改走统一入口
