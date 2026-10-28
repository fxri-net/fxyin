# 文件

把目录读写、路径规范与媒体类型查询收成无状态静态方法，写入走临时文件加改名，路径统一用正斜杠表示层级。

## 定位

`Fxri\File` 是纯静态工具层，直接作用于本地文件系统，不依赖内核、配置或 PSR-7 上传对象。它承接的是「业务代码里散落的 `mkdir` / `file_put_contents` / `pathinfo`」这类重复片段，范围刻意收窄：只做目录与文件的增删读写、路径字符串处理、MIME 与扩展名互查。

四个类职责不重叠：`Filesystem` 负责实际 I/O，读写与目录操作失败统一抛 `RuntimeException`；`Path` 只做字符串层面的拼接与规范化，不碰磁盘；`MediaInfo` 查内置映射表，不读取文件内容，也不依赖 `fileinfo` 扩展。`VideoInfo` 执行外部探测命令解析视频元信息，命令与二进制由调用方传入。

与相邻层的关系：文件大小格式化在 `Fxri\Support\Formatter`（`size()`），不在这一层；这一层不解析 HTTP 请求，也不判断上传文件是否合法，只负责把给定路径写入或读出，来源判定与权限约束留给调用方。

## 最小示例

```php
<?php

declare(strict_types=1);

use Fxri\File\Filesystem;
use Fxri\File\MediaInfo;
use Fxri\File\Path;

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-demo';

// 先清掉上一轮遗留，保证示例可重复执行
Filesystem::deleteDirectory($root);

Filesystem::makeDirectory($root);
Filesystem::write($root . '/a.txt', 'hello');

// hello
echo Filesystem::read($root . '/a.txt') . PHP_EOL;

// true
var_export(Filesystem::exists($root . '/a.txt'));
echo PHP_EOL;

// array(0 => '…/fxri-demo/a.txt')：完整路径随系统变化
var_export(Filesystem::listFiles($root));
echo PHP_EOL;

// /var/www/config：首段前导斜杠保留，.. 由 normalize 消解
echo Path::join('/var/www', 'app', '..', 'config') . PHP_EOL;

// index / png：filename 去掉目录与扩展名，extension 统一小写
echo Path::filename('/var/www/index.php') . ' / ' . Path::extension('IMG.PNG') . PHP_EOL;

// html / image/jpeg / NULL：未收录的扩展名返回 null
var_export(MediaInfo::extension('text/html; charset=utf-8'));
echo ' / ';
var_export(MediaInfo::mime('.jpg'));
echo ' / ';
var_export(MediaInfo::mime('jpeg'));
echo PHP_EOL;

Filesystem::deleteDirectory($root);

// false：目录与其内容一并移除
var_export(Filesystem::exists($root));
echo PHP_EOL;
```

## 参数与返回值

### Filesystem

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `read(string $path)` | 读取整个文件内容，读不到抛 `RuntimeException` | `string` |
| `write(string $path, string $contents)` | 写入内容；自动递归创建父目录，先写同目录临时文件再 `rename` 覆盖，任一步失败抛 `RuntimeException` | `void` |
| `exists(string $path)` | 路径是否存在，等价于 `file_exists`，目录同样算存在 | `bool` |
| `findFiles(string $directory, array $extensions = [], ?int $maxDepth = null)` | 递归列出**文件**（不含目录）；`$extensions` 为空不过滤，匹配不区分大小写、可带点号；`$maxDepth` 为 `null` 不限深度、`0` 只取当前层、正数表示下探层数；符号链接目录不跟随，结果按路径排序 | `array` |
| `listFiles(string $directory)` | 列出目录**当前层**的文件与子目录，返回 `glob` 给出的完整路径；目录不可读或无匹配时返回空数组 | `array` |
| `makeDirectory(string $directory, int $permissions = 0o777)` | 递归创建目录；已存在时直接返回，创建失败且目录仍不存在时抛 `RuntimeException` | `void` |
| `createDirectory(string $directory, int $permissions = 0o777)` | `makeDirectory` 的兼容命名，语义完全一致 | `void` |
| `deleteDirectory(string $directory)` | 递归删除目录及其内容；目录不存在时直接返回，删除文件或目录失败抛 `RuntimeException` | `void` |
| `moveDirectory(string $source, string $destination)` | 移动目录；源不存在抛 `RuntimeException`，目标父目录会自动创建，`rename` 失败抛 `RuntimeException` | `void` |

### Loader

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `load(string $file)` | 执行副作用脚本，同一真实路径只执行一次；文件不存在返回 `false` | `bool` |
| `read(string $file)` | 读取文件返回值，同一路径结果缓存；文件不存在返回 `false` | `mixed` |

### Path

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `join(string ...$segments)` | 逐段把反斜杠换成正斜杠；首段保留前导 `/` 与盘符、协议头，其余段两端去 `/`；丢弃空段后用 `/` 连接并交给 `normalize()` | `string` |
| `normalize(string $path)` | 归一化路径：保留 `scheme://` 与 `C:/` 前缀，折叠 `.`、消解 `..`，保留绝对路径的前导 `/` | `string` |
| `filename(string $path)` | 取不含目录与扩展名的文件名，`pathinfo` 语义 | `string` |
| `extension(string $path)` | 取小写扩展名，无扩展名返回空串 | `string` |

### MediaInfo

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `mime(string $extension)` | 由扩展名查媒体类型；入参小写化并去掉前导点，未收录返回 `null` | `?string` |
| `extension(string $mime)` | 由媒体类型查扩展名；丢弃 `;` 之后的参数、小写化后查表，未收录返回 `null` | `?string` |

内置映射表共 23 组，覆盖常见文档、图片、音视频与 Web 资源：

| 媒体类型 | 扩展名 |
| --- | --- |
| `application/javascript` | `js` |
| `application/json` | `json` |
| `application/msword` | `doc` |
| `application/pdf` | `pdf` |
| `application/vnd.ms-excel` | `xls` |
| `application/vnd.ms-powerpoint` | `ppt` |
| `application/vnd.openxmlformats-officedocument.presentationml.presentation` | `pptx` |
| `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `xlsx` |
| `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | `docx` |
| `application/xml` | `xml` |
| `application/zip` | `zip` |
| `audio/mpeg` | `mp3` |
| `image/gif` | `gif` |
| `image/jpeg` | `jpg` |
| `image/png` | `png` |
| `image/svg+xml` | `svg` |
| `image/vnd.microsoft.icon` | `ico` |
| `image/webp` | `webp` |
| `text/css` | `css` |
| `text/csv` | `csv` |
| `text/html` | `html` |
| `text/plain` | `txt` |
| `video/mp4` | `mp4` |

映射表是唯一的查询源：`mime()` 由 `array_search()` 反查，因此两个方向的结果天然一致。

### VideoInfo

执行外部探测命令并解析输出，承接 1.x `File::getMediaInfo()` 的视频分支。推荐用数组命令 `[二进制, ...参数]`，不经 shell；`inspectTemplate()` 保留 1.x 的 `%s` 命令模板，仅用于迁移。

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `inspect(string $file, array $command)` | 按数组命令执行并解析 `Duration/start/bitrate`、`Video`、`Audio` 三段输出，补充 `seconds`、`play_time` 与 `size` | `array<string, mixed>` |
| `inspectTemplate(string $file, string $template)` | 按 1.x 命令模板执行，`%s` 替换为 `escapeshellarg()` 处理后的文件路径；模板需可信 | `array<string, mixed>` |

文件不存在抛 `InvalidArgumentException`；命令失败且无输出抛 `RuntimeException`。返回字段：`duration`、`seconds`、`start`、`bitrate`、`vcodec`、`vformat`、`resolution`、`width`、`height`、`acodec`、`asamplerate`、`play_time`、`size`；未匹配到的段落不会出现对应键。

## 注意事项

- ⚠️ `Path::join()` 只保留**首段**的前导 `/` 与盘符、协议头：`Path::join('/tmp', 'a')` 得 `/tmp/a`，而 `Path::join('', '/tmp')` 仍是 `tmp`（相对路径）。后续段的斜杠一律当作分隔符，要绝对路径就把绝对前缀放在首段。
- ⚠️ `Path::normalize()` 对超出根目录的 `..` 直接丢弃且不报错：`a/../../b` 归一化为 `b`，无法据此发现路径穿越，安全校验要单独做。
- ⚠️ `normalize()` 只保留协议头与盘符两种前缀；`file://`、`phar://` 这类形式按 `scheme://` 处理，其余自定义前缀（如 `~`）不识别。
- ⚠️ `Path::filename()` 只去掉最后一段扩展名：`/a/b.tar.gz` 得到 `b.tar`；`extension()` 同理得到 `gz`。
- ⚠️ `Path::extension('.env')` 返回空串而不是 `env`，以点开头的隐藏文件名会被 `pathinfo` 视作无扩展名。
- ⚠️ `Filesystem::listFiles()` 不递归，只列一层；`glob('*')` 不匹配以 `.` 开头的隐藏文件，目录也会被一并列出，需要过滤时由调用方处理。
- ⚠️ `Filesystem::findFiles()` 只返回文件，目录本身不会出现；扩展名列表是数组而不是 1.x 的 `|` 分隔字符串，大小写不敏感，`['.PHP']` 与 `['php']` 等价。
- ⚠️ `Filesystem::findFiles()` 的 `$maxDepth` 语义与 1.x `getList()` 的 `$limit` 对齐：`0` 只取当前层、`1` 下探一层、`null` 不限；但 `$limit` 的负数不限深度写法在 v2 用 `null` 表达。
- ⚠️ `Filesystem::findFiles()` 不跟随符号链接目录，避免环形目录；需要跟随时要自行基于 `Filesystem::listFiles()` 实现。
- ⚠️ `Filesystem::read()` 一次性把整个文件读进内存，大文件不要用它；它也不做编码转换，二进制内容原样返回。
- ⚠️ `Filesystem::write()` 依赖目标目录可写，失败时先 `unlink` 临时文件再抛 `RuntimeException`；文件名本身会被临时文件名替换，因此原文件的权限位不会保留。
- ⚠️ `Filesystem::makeDirectory()` 的 `$permissions` 受进程 `umask` 影响，`0o777` 不等于最终权限；并发下目录被其他进程抢先建好时不算失败。
- ⚠️ `Filesystem::deleteDirectory()` 不可逆，也不会跳过只读文件；符号链接到目录时不会被递归进去，而是按普通文件 `unlink`。
- ⚠️ `Filesystem::moveDirectory()` 只调用 `rename()`，跨分区或跨设备时会失败并抛异常，不会自动退化为「复制后删除」。
- ⚠️ `Filesystem::moveDirectory()` 的源必须是目录，`rename()` 语义下目标已存在且非空目录时的行为依赖文件系统，调用前应自行判重。
- ⚠️ `Filesystem::exists()` 不区分文件与目录，判断「是文件」需要另加 `is_file()`。
- ⚠️ `MediaInfo` 查的是内置表而不是文件内容，`mime('jpeg')` 返回 `null`（表里只有 `jpg`），`extension('image/jpg')` 同样返回 `null`（表里只有 `image/jpeg`）。
- ⚠️ `MediaInfo::extension()` 只取 `;` 之前的主类型，参数（如 `charset=utf-8`）被丢弃；未收录的类型返回 `null`，与「扩展名为空串」是两种不同结果。
- ⚠️ `VideoInfo::inspect()` 会执行外部命令：数组形式不经 shell 更安全；字符串模板若来自不可信输入，文件路径虽被转义，模板本身仍可能注入命令。
- ⚠️ `VideoInfo::inspect()` 依赖命令把探测信息输出到标准输出且返回码为 0；命令失败但有部分输出时仍会按已解析字段返回，不视为错误。
- ⚠️ `VideoInfo` 只解析 v1 使用的 `Duration/Video/Audio` 文本格式，ffprobe 的 JSON 输出不在解析范围内。
