---
"fxri/fxyin": minor
---

- 修改：`Text\Time::change()` 无法解析输入时改为抛 `InvalidArgumentException`，与同页 `parse()` 及文本层「不合法即失败」的口径一致，不再返回 `null`
- 修复：`Time::change()` 在截断结果为 `0` 时被误判为失败返回 `null`
- 修复：`Time::range()` 把逗号串里的空项当作有效值，起止配置的默认值不生效
- 修改：`Time::relative()` 的 `now` 不是整数时抛 `InvalidArgumentException`，不再静默回落到当前时间
- 修改：`Translation\ArrayTranslator` 的占位符规则与 `Translator::format()` 统一，支持 `{:name}` 与 `sprintf` 数字参数
- 修改：`Http\HttpClient\Client::download()` 去掉响应体 UTF-8 BOM 与 `Curl` 对齐；响应缺少状态码或正文方法时抛 `RuntimeException`，不再按 200 放行
- 修改：`fxyin` 入口只分发当前安装可用的命令域，接入项目执行本仓专用命令时以「命令不可用」退出 2
