import { existsSync, readdirSync, readFileSync } from "node:fs"
import { dirname, join } from "node:path"
import { fileURLToPath } from "node:url"

// 变更集规范校验：包名键必须与 package.json 的 name 一致，文件名与条目格式符合 .changeset/README.md 的约定。
// 纳入 pnpm verify，避免「包名键写错」直到 toolkit changelog version 才暴露
const root = dirname(dirname(fileURLToPath(import.meta.url)))
const changesetDir = join(root, ".changeset")
const packageName = JSON.parse(readFileSync(join(root, "package.json"), "utf8")).name

// 2–4 个 kebab-case 英文单词
const NAME_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+){1,3}$/
// 条目：- 类型：描述
const ENTRY_PATTERN = /^- (新增|修改|修复|优化|清理|文档)：\S/m

if (!existsSync(changesetDir)) {
  console.log("✔ 无 .changeset 目录，跳过变更集检查")
  process.exit(0)
}

const files = readdirSync(changesetDir)
  .filter((name) => name.endsWith(".md") && name !== "README.md")
  .sort()

const issues = []
for (const file of files) {
  const stem = file.slice(0, -3)
  if (!NAME_PATTERN.test(stem)) {
    issues.push(`${file}：文件名须为 2–4 个 kebab-case 英文单词`)
  }
  if (/^(fix|feat)-/.test(stem)) {
    issues.push(`${file}：文件名不带 fix- / feat- 类型前缀，类型写进条目前缀`)
  }

  // 行尾归一后再解析：Windows 检出为 CRLF 时，frontmatter 的换行匹配会失效
  const text = readFileSync(join(changesetDir, file), "utf8").replace(/\r\n/g, "\n")
  const match = /^---\n([\s\S]*?)\n---\n?([\s\S]*)$/.exec(text)
  if (null === match) {
    issues.push(`${file}：缺少 --- 包裹的 frontmatter`)
    continue
  }

  const [, head, body] = match
  const entries = [...head.matchAll(/^\s*"([^"]+)"\s*:\s*(patch|minor|major)\s*$/gm)]
  if (0 === entries.length) {
    issues.push(`${file}：frontmatter 未声明包名键与 patch / minor / major`)
  }
  for (const [, name] of entries) {
    if (name !== packageName) {
      issues.push(`${file}：包名键 ${name} 与 package.json 的 ${packageName} 不一致`)
    }
  }
  if (!ENTRY_PATTERN.test(body)) {
    issues.push(`${file}：正文须至少有一条「- 类型：描述」，类型取 新增/修改/修复/优化/清理/文档`)
  }
}

if (issues.length > 0) {
  console.error(`\n✖ 变更集检查失败：${issues.length} 项`)
  for (const issue of issues) console.error(`  - ${issue}`)
  process.exit(1)
}

console.log(0 === files.length ? "✔ 无待发布变更集" : `✔ 变更集检查通过（${files.length} 个）`)
