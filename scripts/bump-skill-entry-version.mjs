// 薄壳版本半自动抬升：模板正文变化时把 MARKER_VERSION 次版本 +1，并刷新渲染快照
// 结构或协议变更（主版本）仍需人工改：脚本只做「文案变更」这一档
// --no-bump 只刷新快照、不改版本，用于初始化或回填
import { execFileSync } from "node:child_process"
import { mkdirSync, readFileSync, writeFileSync } from "node:fs"
import { dirname, join } from "node:path"
import { fileURLToPath } from "node:url"

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const installer = join(root, "src", "Tooling", "SkillInstaller.php")
const fixture = join(root, "tests", "fixtures", "skill-shell.txt")
const versionRe = /public const MARKER_VERSION = 'v(\d+)\.(\d+)';/

if (!process.argv.includes("--no-bump")) {
  const source = readFileSync(installer, "utf8")
  const matched = versionRe.exec(source)
  if (!matched) {
    console.error("未找到 MARKER_VERSION 常量，无法抬版本")
    process.exit(1)
  }
  const nextMinor = Number(matched[2]) + 1
  writeFileSync(installer, source.replace(versionRe, `public const MARKER_VERSION = 'v${matched[1]}.${nextMinor}';`), "utf8")
  console.log(`薄壳版本抬至 v${matched[1]}.${nextMinor}`)
}

// 快照由真实实现渲染，脚本不复制模板拼装逻辑；统一 LF，保证跨平台比对一致
const code =
  'require "vendor/autoload.php"; echo Fxri\\Tooling\\SkillInstaller::render("demo", "示例描述", "vendor/demo/skills/demo/SKILL.md");'
const rendered = execFileSync("php", ["-r", code], { cwd: root, encoding: "utf8" }).replace(/\r\n/g, "\n")
mkdirSync(dirname(fixture), { recursive: true })
writeFileSync(fixture, rendered.endsWith("\n") ? rendered : `${rendered}\n`, "utf8")
console.log(`已刷新快照：${fixture}`)
