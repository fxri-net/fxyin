# 常用 API 速查

所有能力都是 `Fxri\*` 下的纯静态类，直接 `use` 即可，不经过内核；构造函数私有，实例一律由 `make()` 或链式方法产出，链式方法返回新实例。

## Param（请求侧入参）

| 类 | 关键方法 | 要点 |
| --- | --- | --- |
| `Definition` | `make(array $defaults, ?Shape $shape = null)`、`resolve(array $input)` | 键默认值即键清单，`$shape` 缺省 `Shape::merge()` |
| `Shape` | `merge()`、`only()`、`csv()`、`json()`、`defaultsFirst()`、`fillWhenAbsent()`、`fillWhenBlank()`、`nullWhenAbsent()`、`nullWhenBlank()` | 定形规则；`apply()` 先定结果集起点，再逐键判默认值，最后转换 |
| `Pipeline` | `make(Definition ...$definitions)`、`add()`、`resolve()` | 多环串联，前一环结果作为后一环输入 |
| `Merger` | `append(...$arrays)`、`merge($base, $overlay, $depth = -1)`、`mergeKeepBlank(...)` | 递归合并；`$depth = 0` 为整体覆盖 |
| `Picker` | `only(array $source, array $keys)`、`pluck(array $source, string $path, $default = null)` | 点号路径取值；缺失键跳过不补 `null` |
| `Json` | `decode($value, $associative = true)`、`encode($value, $flags = ...)`、`toArray($value)` | 前两者失败抛异常，`toArray()` 非法输入返回 `null` |
| `Url` | `build($path, $query = [], $fragment = null)`、`query($url)`、`withQuery($url, $query)` | RFC3986 编码；`withQuery()` 传 `null` 才删除参数 |

```php
use Fxri\Param\Definition;
use Fxri\Param\Pipeline;
use Fxri\Param\Shape;

// 只保留声明的键，空串按缺失处理，再把 with 转成数组
$clean = Pipeline::make(
    Definition::make(['page' => 1, 'with' => 'id'], Shape::only()->nullWhenBlank()),
    Definition::make(['with' => 'id'], Shape::merge()->csv()),
)->resolve($input);
```

## Data（存储侧数据）

| 类 | 关键方法 | 要点 |
| --- | --- | --- |
| `Field` | `make(?DialectInterface $dialect = null)`、`date()`、`json()`、`text()`、`alias()`、`escape()`、`distance()`、`division()`、`initial()` | 生成 SQL 片段并携带绑定值；列名由方言加引用符 |
| `Tree` | `fromList($list, $rootId = 0, $idKey = 'id', $parentKey = 'parent_id', $childKey = '_child')`、`toList($tree, $childKey)` | 扁平列表与树互转 |
| `Hierarchy` | `ancestors($list, $id, $idKey, $parentKey)`、`descendants(...)` | 分别返回祖先、后代主键，成环自动截断 |
| `Cast` | `toArray($value, $limit = -1)`、`emptyToObject(...)`、`toObject(...)` | 对象转数组、空数组转对象；`emptyToObject()` 承接旧 `Param::object()` |
| `Page` | `make($records, $total, $page = null, $size = null)`、`toArray()` | 分页四键壳 |
| `Param` | `exist()`、`filter()`、`missing($data, NullPolicy $policy = NullPolicy::Blank)`、`repair()` | 空值判定统一走 `NullPolicy` |
| `Diff` | `make($new, $old)`、`changes()`、`keys()` | 无变化抛 `SameDataException`，入参非数组抛 `ValidationException` |
| `Html` | `escape($value)`、`strip($value, $allowed = null)` | 数组按元素递归，非字符串原样返回 |

## Text（文本与时间）

| 类 | 关键方法 | 要点 |
| --- | --- | --- |
| `Str` | `explode($separator, $value, $unique = true)`、`implode($glue, $values, $unique = true)`、`isLength()`、`length()`、`splice()` | 按 UTF-8 计长；`explode()` 分段 trim 后丢弃空段 |
| `Encode` | `base64Encode/Decode`、`hexEncode/Decode`、`htmlEncode/Decode`、`queryEncode/Decode`、`urlEncode/Decode`、`convert()`、`encodeAll()`、`decodeAll()`、`letters()` | 查询串固定 RFC3986；`letters()` 中文按 GBK 区间取首字母 |
| `Ip` | `toLong()`、`fromLong()`、`toBinary()`、`fromBinary()` | `toLong()` 只接受 IPv4 |
| `Time` | `format()`、`parse()`、`milli()`、`milliFormat()`、`milliParse()`、`decompose()`、`duration()`、`truncate()`、`change()`、`range()`、`relative()` | 粒度用 `DateGranularity`，输出形态用 `TimeOutput` |
| `Uuid` | `make(UuidMode $mode = UuidMode::Standard, ?string $seed = null, ?UuidStyle $style = null)`、`format($value, ?UuidStyle $style = null)` | `$seed` 为空取 `random_bytes`，否则用 `md5($seed)` |

## File（文件与路径）

| 类 | 关键方法 | 要点 |
| --- | --- | --- |
| `Filesystem` | `read()`、`write()`、`exists()`、`findFiles($directory, $extensions = [], ?int $maxDepth = null)`、`listFiles()`、`makeDirectory()`、`deleteDirectory()`、`moveDirectory()` | `write()` 先写临时文件再 `rename`，自动建父目录 |
| `Loader` | `load($file)`、`read($file)` | `load()` 执行副作用脚本且同一路径只执行一次；`read()` 取返回值并缓存 |
| `Path` | `join(string ...$segments)`、`normalize()`、`filename()`、`extension()` | 保留协议头与盘符，折叠 `.`、消解 `..` |
| `MediaInfo` | `mime($extension)`、`extension($mime)` | 内置 23 组映射，未收录返回 `null` |
| `VideoInfo` | `inspect($file, array $command)`、`inspectTemplate($file, $template)` | 用数组命令执行，不经 shell；模板版仅用于迁移 |

## Http（请求与响应）

| 类 | 关键方法 | 要点 |
| --- | --- | --- |
| `Envelope` | `success()`、`failure()`、`toArray()` | 五键：`code`、`message`、`data`、`extend`、`debug` |
| `Responder` | `make(?Formatter $formatter = null)`、`send()`、`format()`、`withFormatter()` | 只设 `Content-Type` 与正文，不设 HTTP 状态码 |
| `RequestContext` | `fromGlobals()`、`fromPsrRequest()`、`fromServer()`、`make()`、`method()`、`url()`、`header()` | 只读请求快照，`fromGlobals()` 每次都重读 `php://input` |
| `RequestInput` | `fromGlobals()`、`fromRequest()`、`make()`、`all()`、`get()`、`only()` | 查询串、表单、JSON 体、命令行依次合并，后者覆盖 |
| `ClientInfo` | `fromGlobals(IpSource $ipSource = IpSource::Remote, array $trustedProxies = [])`、`ip()`、`browser()`、`system()` | 可信代理只认 CIDR，裸 IP 永远匹配不上 |
| `CookieJar` | `fromGlobals(?string $secret = null)`、`get()`、`set()`、`delete()`、`signed()`、`signedValue()` | 签名是名字与值的 HMAC-SHA256，未配密钥抛 `LogicException` |
| `CookieOptions` | `make($expires = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true, SameSite $sameSite = SameSite::Lax)` | `SameSite::None` 必须配 `secure` |
| `HttpClient\Curl` | `request()`、`send()`、`download()` | URL 级入口，需 `ext-curl`；GET/HEAD 的数组数据拼进查询串 |
| `HttpClient\Client` | `make($client)`、`send($request)`、`download()` | 包装 PSR-18 客户端，需对应接口已安装 |
| `View` | `make($directory, Renderer $renderer)`、`render($template, $data = [])` | 只解析路径并校验越界，模板引擎由接入方实现 |

## 其它常用

| 命名空间 | 类与方法 |
| --- | --- |
| `Support` | `Type` 的 `isBlank`、`isJson`、`isTime`、`isEmail`、`isIp`、`toInt`、`toString`；`Random::string()`；`Formatter::number()` / `size()`；`Dump::render()` / `write()` |
| `Security` | `Cipher::encrypt()` / `decrypt()`、`Rsa` 的签名与加解密、`Hash::md5()`、`Token::make()` / `verify()`；旧密文与旧令牌分别用 `DesCipher`、`DesToken` |
| `Sql` | `WhereBuilder` 链式条件、`StatementBuilder` 拼 insert/update、`Expression` 原生片段 |
| `Config` | `Config::get()`、`Store::set()`、`ConfigLoader::load()`、`Environment::fromFile()` |
| `Translation` | `ArrayTranslator::make()` / `fromDirectory()`、`Language`、`PhpFileLoader::loadDirectory()` |

各能力的完整参数表、枚举分支与注意事项回仓库文档：`docs/param.md`、`docs/data.md`、`docs/sql.md`、`docs/text.md`、`docs/file.md`、`docs/security.md`、`docs/http.md`、`docs/support.md`、`docs/config.md`、`docs/language.md`。
