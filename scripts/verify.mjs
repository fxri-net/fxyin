import { spawnSync } from "node:child_process"
import { dirname } from "node:path"
import { fileURLToPath } from "node:url"

// 单一门禁真源：本仓库全部验收步骤由本脚本定义并按序执行，本地与三处流水线（GitHub 矩阵 / GitHub 文档部署 / GitLab）共用同一份清单。
// 目的：消除「各写一份步骤清单、彼此漂移」的结构性问题，使「本地自检通过、CI 才暴露」在清单层面不可能发生
const root = dirname(dirname(fileURLToPath(import.meta.url)))
const isWindows = process.platform === "win32"

const flags = new Set(process.argv.slice(2))
const skipCode = flags.has("--skip-code")
const skipDocs = flags.has("--skip-docs")

// 步骤清单即门禁定义：PHP 侧沿用 composer check 作为唯一代码门禁，文档站构建兼做内链死链检查
const steps = [
  { label: "代码门禁（composer check）", command: "composer", args: ["check"], skip: skipCode },
  { label: "文档站构建（内链检查）", command: "pnpm", args: ["docs:build"], skip: skipDocs }
]

const failures = []
let executed = 0

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

for (const step of steps) {
  if (step.skip) continue
  console.log(`\n▶ ${step.label}`)
  executed += 1
  // composer / pnpm 在 Windows 上是 .cmd，需经 shell 调用；其余平台直接调用可避免路径含空格时被 shell 拆断
  const result = spawnSync(step.command, step.args, { cwd: root, stdio: "inherit", shell: isWindows })

  if (result.status === 0) continue
  failures.push(step.label)
}

if (failures.length > 0) {
  console.error(`\n✖ 门禁未通过：${failures.length} 步失败`)
  for (const label of failures) console.error(`  - ${label}`)
  process.exit(1)
}

console.log(`\n✔ 门禁全部通过（共 ${executed} 步）`)
