import { spawn, spawnSync } from "node:child_process"
import { readFileSync } from "node:fs"
import { dirname, join } from "node:path"
import { fileURLToPath } from "node:url"

// 单一门禁真源：本仓库全部验收步骤由本脚本定义并按声明顺序回放，本地与三处流水线（GitHub 矩阵 / GitHub 文档部署 / GitLab）共用同一份清单。
// 目的：消除「各写一份步骤清单、彼此漂移」的结构性问题，使「本地自检通过、CI 才暴露」在清单层面不可能发生
const root = dirname(dirname(fileURLToPath(import.meta.url)))
const isWindows = process.platform === "win32"

const flags = new Set(process.argv.slice(2))
const skipCode = flags.has("--skip-code")
const skipDocs = flags.has("--skip-docs")
// --full 强制全量：忽略本地改动面判断，等价于 CI 口径
const full = flags.has("--full")
// CI 环境一律构建文档：本地按改动面裁剪只服务于开发效率，不能让 CI 漏检内链
const ciValue = process.env.CI ?? ""
const isCI = ciValue !== "" && ciValue !== "0" && ciValue.toLowerCase() !== "false"

// 文档站构建的触发面：站点输入含文档正文与内链，根 CHANGELOG 会同步进 docs，依赖清单决定构建工具版本
const DOC_TOUCH_PREFIXES = ["docs/"]
const DOC_TOUCH_FILES = new Set(["CHANGELOG.md", "README.md", "package.json", "pnpm-lock.yaml"])

// git 只读调用：成功返回行数组，失败返回 null（调用方按「可能触及」保守处理）
function gitLines(args) {
  const result = spawnSync("git", args, { cwd: root, encoding: "utf8" })
  if (result.status !== 0) return null
  return (result.stdout ?? "").split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
}

// 判断是否需要构建文档站：拿不到改动面时保守执行，宁可多跑一次也不漏检
function needDocsBuild() {
  if (isCI || full) return true
  const tracked = gitLines(["diff", "--name-only", "HEAD"])
  const untracked = gitLines(["ls-files", "--others", "--exclude-standard"])
  if (tracked === null || untracked === null) return true
  for (const file of [...tracked, ...untracked]) {
    if (DOC_TOUCH_FILES.has(file)) return true
    if (DOC_TOUCH_PREFIXES.some((prefix) => file.startsWith(prefix))) return true
  }
  return false
}

/**
 * 展开代码门禁步骤。
 *
 * 清单真源仍是 `composer.json` 的 `scripts.check`：这里只把每条脚本展开成可独立并行的命令，
 * 增删门禁步骤仍只改 `composer.json`，本脚本不跟着改，避免出现第二份清单。
 *
 * @return {Array<{label: string, command: string}>} 步骤清单
 */
function codeSteps() {
  const { scripts } = JSON.parse(readFileSync(join(root, "composer.json"), "utf8"))
  const entries = scripts?.check
  if (!Array.isArray(entries) || entries.length === 0) {
    throw new Error("composer.json 的 scripts.check 不是非空数组")
  }
  return entries.map((entry) => {
    const command = entry.replace(/^@/, "")
    return {
      label: `代码门禁（${command}）`,
      // composer 与 php 开头的条目已在 scripts 里写成完整命令，其余是脚本名，交给 composer 转调
      command: /^(composer|php)\s/.test(command) ? command : `composer run-script ${command}`
    }
  })
}

const docsNeeded = needDocsBuild()

// 步骤清单即门禁定义：各步互不依赖，统一并发执行；输出按本数组顺序回放，判定规则与失败口径与串行时一致
const steps = [
  ...(skipCode
    ? [{ label: "代码门禁（composer.json scripts.check）", skip: "--skip-code" }]
    : codeSteps()),
  { label: "变更集规范（.changeset）", command: "node scripts/check-changeset.mjs" },
  { label: "更新日志镜像（CHANGELOG → docs）", command: "node scripts/check-changelog-doc.mjs" },
  { label: "文档站主题一致（toolkit docs theme check）", command: "node scripts/check-docs-theme.mjs" },
  { label: "文档站导航规范（分类 / 命名 / 顶栏）", command: "node scripts/check-docs-nav.mjs" },
  // 该步含 docs:typecheck，本地跳过时文档类型检查一并跳过，由 CI 部署工作流每次推送全量构建兜底；
  // 产物断言必须晚于构建，故与构建串在同一步内执行
  { label: "文档站构建（类型检查 + 内链检查 + 404 排除）", command: "pnpm docs:build && node scripts/check-docs-dist.mjs", skip: skipDocs ? "--skip-docs" : docsNeeded ? "" : "未触及 docs/、CHANGELOG.md、README.md、依赖清单" }
]

/**
 * 并发启动一个步骤并缓冲输出。
 *
 * 并发时若继承 stdio，多步日志会互相穿插、失败信息难以定位，故先缓存再由主流程按声明顺序回放。
 * 命令串只来自本脚本与 `composer.json`，不含外部输入，统一经 shell 调用即可覆盖 Windows 的 .cmd 包装。
 *
 * @param {{label: string, command: string}} step 步骤
 * @return {Promise<{step: object, code: number, stdout: string, stderr: string, seconds: number}>} 执行结果
 */
function runStep(step) {
  return new Promise((resolve) => {
    const startedAt = Date.now()
    const child = spawn(step.command, { cwd: root, shell: true, env: process.env })
    let stdout = ""
    let stderr = ""
    child.stdout.on("data", (chunk) => (stdout += chunk))
    child.stderr.on("data", (chunk) => (stderr += chunk))
    const finish = (code, extra = "") => {
      resolve({
        step,
        code,
        stdout,
        stderr: stderr + extra,
        seconds: (Date.now() - startedAt) / 1000
      })
    }
    child.on("error", (error) => finish(1, String(error)))
    child.on("close", (code) => finish(code ?? 1))
  })
}

// 前置探测：Windows 的 PHP 默认找不到 openssl.cnf，RSA 用例会以「密钥生成失败」收场；
// 这里先探一次并给出可执行指引，避免把环境问题误判成代码缺陷
if (!skipCode) {
  const probe = spawnSync("php", ["-r", "exit(@openssl_pkey_new(['private_key_bits' => 512]) === false ? 1 : 0);"], { cwd: root })
  if (probe.status !== 0) {
    console.error("\n✖ 前置检查未通过：当前 php 无法生成 OpenSSL 密钥")
    if (probe.error) {
      console.error("  未找到 php 命令，请先安装 PHP 并加入 PATH")
    } else if (isWindows) {
      console.error("  Windows 下通常是缺少 openssl.cnf，设置后重跑：")
      console.error('  $env:OPENSSL_CONF = "<PHP 目录>\\extras\\ssl\\openssl.cnf"')
    } else {
      console.error("  请检查 php 的 openssl 扩展与 openssl.cnf 配置")
    }
    process.exit(1)
  }
}

const startedAt = Date.now()
// 一次拉起全部步骤：单步失败只记录不中断，全部跑完再统一汇总，便于一轮看到所有问题。
// 启动之间留 150ms 间隔：部分 PHP 扩展（如 CLI 下开启的 yac）在同一毫秒并发初始化共享内存会失败，
// 错开启动不影响各步重叠执行，检查项与判定规则也不变
const pending = []
for (const step of steps) {
  if (step.skip) {
    pending.push({ step, skipped: step.skip })
    continue
  }
  pending.push(runStep(step))
  await new Promise((resolve) => setTimeout(resolve, 150))
}
const results = await Promise.all(pending)

const failures = []
let executed = 0

for (const result of results) {
  if (result.skipped) {
    console.log(`\n⊘ ${result.step.label}（${result.skipped}，跳过）`)
    continue
  }
  executed += 1
  console.log(`\n▶ ${result.step.label}`)
  if (result.stdout) process.stdout.write(result.stdout)
  if (result.stderr) process.stderr.write(result.stderr)
  const mark = result.code === 0 ? "✔" : "✖"
  console.log(`${mark} ${result.step.label}（${result.seconds.toFixed(1)} 秒）`)
  if (result.code !== 0) failures.push(result.step.label)
}

const seconds = ((Date.now() - startedAt) / 1000).toFixed(1)

if (failures.length > 0) {
  console.error(`\n✖ 门禁未通过：${failures.length} 步失败（用时 ${seconds} 秒）`)
  for (const label of failures) console.error(`  - ${label}`)
  process.exit(1)
}

console.log(`\n✔ 门禁全部通过（共 ${executed} 步，用时 ${seconds} 秒）`)
