// 版本同步：changelog version 只改 package.json，这里把版本回写到 PHP 版本常量
// 两处版本由 scripts/check-version-sync.mjs 与 tests/PackageTest.php 双重把关
import { readFileSync, writeFileSync } from "node:fs"
import { dirname, join } from "node:path"
import { fileURLToPath } from "node:url"

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const sourceFile = join(root, "src", "Package.php")
const version = JSON.parse(readFileSync(join(root, "package.json"), "utf8")).version
const source = readFileSync(sourceFile, "utf8")
// 只替换常量赋值行，其余源码原样保留
const pattern = /(public const VERSION = ')([^']*)(';)/
const matched = pattern.exec(source)

if (!matched) {
  console.error("未找到 Package::VERSION 常量，跳过版本同步")
  process.exit(1)
}
if (matched[2] === version) {
  console.log(`版本一致：Package::VERSION 已是 ${version}`)
} else {
  writeFileSync(sourceFile, source.replace(pattern, `$1${version}$3`), "utf8")
  console.log(`已同步 Package::VERSION：${matched[2]} → ${version}`)
}
